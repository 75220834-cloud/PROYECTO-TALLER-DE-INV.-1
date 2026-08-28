<?php

declare(strict_types=1);

namespace App\Modules\Equipment\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Equipment\Models\EquipmentType;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Services\DryRunSignal;
use App\Modules\Locations\Services\ImportReport;
use Illuminate\Support\Facades\DB;

/**
 * Importación del inventario real de equipos desde CSV (plan 22.2).
 *
 * Mismas garantías que el importador de aulas: transacción todo-o-nada,
 * idempotente por código de activo, y con vista previa.
 *
 * DEPENDE DE QUE LAS AULAS YA ESTÉN CARGADAS. Un equipo sin aula no tiene
 * sentido —la clave foránea es RESTRICT a propósito (plan 26)— y por eso una
 * fila que apunte a un aula inexistente se rechaza con su número de línea en
 * lugar de crear el aula al vuelo. Crear ubicaciones desde el inventario
 * llenaría el catálogo de aulas fantasma nacidas de erratas.
 */
final class EquipmentImporter
{
    /** @var list<string> */
    public const COLUMNS = ['aula', 'tipo', 'codigo_activo', 'marca', 'modelo', 'serie', 'estado'];

    public function import(string $path, bool $dryRun = false): ImportReport
    {
        $report = new ImportReport;
        $rows = $this->readCsv($path, $report);

        if ($rows === []) {
            return $report;
        }

        try {
            DB::transaction(function () use ($rows, $report, $dryRun): void {
                foreach ($rows as [$line, $row]) {
                    $this->importRow($line, $row, $report);
                }

                if ($dryRun || $report->hasErrors()) {
                    throw new DryRunSignal;
                }
            });
        } catch (DryRunSignal) {
            // Esperado: revierte y no es un fallo.
        }

        return $report;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(int $line, array $row, ImportReport $report): void
    {
        $roomCode = trim($row['aula'] ?? '');
        $typeCode = mb_strtoupper(trim($row['tipo'] ?? ''));
        $assetCode = trim($row['codigo_activo'] ?? '');

        if ($roomCode === '' || $typeCode === '' || $assetCode === '') {
            $report->error($line, 'Faltan «aula», «tipo» o «codigo_activo».');

            return;
        }

        $room = Room::where('code', $roomCode)->first();

        if ($room === null) {
            $report->error($line, "El aula «{$roomCode}» no existe. Carga primero el catálogo de aulas.");

            return;
        }

        $type = EquipmentType::where('code', $typeCode)->first();

        if ($type === null) {
            $valid = EquipmentType::orderBy('code')->pluck('code')->implode(', ');
            $report->error($line, "El tipo «{$typeCode}» no existe. Válidos: {$valid}");

            return;
        }

        $attributes = [
            'room_id' => $room->id,
            'equipment_type_id' => $type->id,
            'brand' => trim($row['marca'] ?? '') ?: null,
            'model' => trim($row['modelo'] ?? '') ?: null,
            'serial_number' => trim($row['serie'] ?? '') ?: null,
            'status' => $this->status($row['estado'] ?? ''),
            'is_active' => true,
            'is_demo' => false,
        ];

        $existing = Equipment::where('asset_code', $assetCode)->first();

        if ($existing !== null) {
            $existing->update($attributes);
            $report->updated($assetCode);

            return;
        }

        Equipment::create(['asset_code' => $assetCode] + $attributes);
        $report->created($assetCode);
    }

    /**
     * Un estado desconocido cae a `operational` en lugar de bloquear la
     * carga. Si el inventario dice "OK" o "bueno", lo que se quiere decir
     * está claro, y rechazar el archivo entero por eso sería absurdo.
     */
    private function status(string $value): string
    {
        // Los cuatro valores de la derecha son los ÚNICOS que acepta la
        // columna. A la izquierda, cómo se escribe de verdad en un inventario
        // hecho en Perú: nadie va a teclear "out_of_service" en un Excel.
        return match (mb_strtolower(trim($value))) {
            'baja', 'retirado', 'averiado', 'malogrado', 'roto', 'no funciona' => 'out_of_service',
            'reparacion', 'reparación', 'mantenimiento', 'en mantenimiento' => 'in_maintenance',
            'regular', 'con fallas', 'a veces falla', 'degradado' => 'degraded',
            default => 'operational',
        };
    }

    /**
     * @return list<array{0: int, 1: array<string, string>}>
     */
    private function readCsv(string $path, ImportReport $report): array
    {
        if (! is_readable($path)) {
            $report->error(0, "No se puede leer el archivo: {$path}");

            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $report->error(0, 'No se pudo abrir el archivo.');

            return [];
        }

        $header = fgetcsv($handle, 0, ';');
        $separator = ';';

        if ($header !== false && count($header) === 1) {
            rewind($handle);
            $header = fgetcsv($handle, 0, ',');
            $separator = ',';
        }

        if ($header === false) {
            $report->error(0, 'El archivo está vacío.');
            fclose($handle);

            return [];
        }

        $header = array_map(
            static fn ($h): string => mb_strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $h))),
            $header,
        );

        foreach (['aula', 'tipo', 'codigo_activo'] as $required) {
            if (! in_array($required, $header, true)) {
                $report->error(0, "Falta la columna «{$required}». Se esperaban: ".implode(', ', self::COLUMNS));
                fclose($handle);

                return [];
            }
        }

        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle, 0, $separator)) !== false) {
            $line++;

            // Salta filas en blanco. Excel deja unas cuantas al final de
            // casi cualquier archivo, y una fila vacia no es un error del
            // usuario: es ruido del formato.
            $vacia = array_filter($data, static fn ($v): bool => trim((string) $v) !== '');

            if ($vacia === []) {
                continue;
            }

            $padded = array_pad($data, count($header), '');
            $rows[] = [$line, array_combine($header, array_slice($padded, 0, count($header)))];
        }

        fclose($handle);

        return $rows;
    }
}
