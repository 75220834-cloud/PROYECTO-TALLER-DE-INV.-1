<?php

declare(strict_types=1);

namespace App\Modules\Locations\Services;

use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Importación del catálogo real de aulas desde CSV (plan 22.2).
 *
 * POR QUÉ NO SE CARGA CON INSERT EN phpMyAdmin
 *
 * El plan lo prohíbe expresamente (§10.3) y no es burocracia: un INSERT
 * directo salta la validación de la aplicación —unicidad de códigos,
 * coherencia de la jerarquía, campos obligatorios— y deja el catálogo en un
 * estado que el sistema no sabe manejar. Un aula colgando de un piso que no
 * existe no da error al insertarla: da error semanas después, cuando un
 * docente la elige desde el aula y la cascada revienta.
 *
 * TODO O NADA. La importación entera va en una transacción. Media carga es
 * peor que ninguna: deja un catálogo incompleto que parece completo, y nadie
 * sabe por dónde iba.
 *
 * IDEMPOTENTE. Volver a importar el mismo archivo no duplica: actualiza. Es
 * la propiedad que permite corregir el Excel y reintentar sin limpiar antes,
 * que es exactamente como se trabaja con datos reales.
 */
final class LocationImporter
{
    /** @var list<string> */
    public const COLUMNS = ['sede', 'pabellon', 'piso', 'aula', 'nombre', 'capacidad', 'criticidad'];

    /**
     * @return ImportReport Qué se creó, qué se actualizó y qué filas fallaron.
     */
    public function import(string $path, bool $dryRun = false): ImportReport
    {
        $report = new ImportReport;
        $rows = $this->readCsv($path, $report);

        if ($rows === []) {
            return $report;
        }

        // La transacción envuelve TODO, incluida la simulación: en modo
        // vista previa se revierte al final, de modo que el informe refleja
        // exactamente lo que pasaría sin dejar rastro.
        try {
            DB::transaction(function () use ($rows, $report, $dryRun): void {
                foreach ($rows as [$line, $row]) {
                    $this->importRow($line, $row, $report);
                }

                if ($dryRun || $report->hasErrors()) {
                    // Si una sola fila falló, no se carga ninguna. Un catálogo
                    // a medias es más peligroso que uno vacío, porque parece
                    // completo.
                    throw new DryRunSignal;
                }
            });
        } catch (DryRunSignal) {
            // Esperado: la señal solo sirve para revertir. No es un fallo y
            // no debe escapar de aquí.
        }

        return $report;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(int $line, array $row, ImportReport $report): void
    {
        $siteCode = trim($row['sede'] ?? '');
        $buildingCode = trim($row['pabellon'] ?? '');
        $floorNumber = trim($row['piso'] ?? '');
        $roomCode = trim($row['aula'] ?? '');

        foreach (['sede' => $siteCode, 'pabellon' => $buildingCode, 'piso' => $floorNumber, 'aula' => $roomCode] as $field => $value) {
            if ($value === '') {
                $report->error($line, "Falta «{$field}».");

                return;
            }
        }

        if (! is_numeric($floorNumber)) {
            $report->error($line, "El piso «{$floorNumber}» no es un número.");

            return;
        }

        $site = Site::firstOrCreate(
            ['code' => $siteCode],
            ['name' => $siteCode, 'is_active' => true, 'is_demo' => false],
        );

        $building = Building::firstOrCreate(
            ['site_id' => $site->id, 'code' => $buildingCode],
            ['name' => "Pabellón {$buildingCode}", 'is_active' => true, 'is_demo' => false],
        );

        $floor = Floor::firstOrCreate(
            ['building_id' => $building->id, 'number' => (int) $floorNumber],
            ['label' => "Piso {$floorNumber}", 'is_active' => true, 'is_demo' => false],
        );

        /*
         * El código de aula se busca en TODA la sede, no solo en este piso.
         * Un mismo código en dos pisos distintos casi siempre es un error de
         * transcripción en el Excel, y detectarlo aquí es mucho más barato
         * que descubrirlo cuando dos docentes de aulas distintas reportan
         * sobre la misma fila.
         */
        $duplicate = Room::query()
            ->whereHas('floor.building', fn ($q) => $q->where('site_id', $site->id))
            ->where('code', $roomCode)
            ->where('floor_id', '!=', $floor->id)
            ->first();

        if ($duplicate !== null) {
            $report->error($line, "El aula «{$roomCode}» ya existe en otro piso de esta sede.");

            return;
        }

        $existing = Room::where('floor_id', $floor->id)->where('code', $roomCode)->first();

        $attributes = [
            'name' => trim($row['nombre'] ?? '') ?: "Aula {$roomCode}",
            'capacity' => is_numeric($row['capacidad'] ?? '') ? (int) $row['capacidad'] : null,
            'criticality' => $this->criticality($row['criticidad'] ?? ''),
            'is_active' => true,
            'is_demo' => false,
        ];

        if ($existing !== null) {
            $existing->update($attributes);
            $report->updated($roomCode);

            return;
        }

        Room::create(['floor_id' => $floor->id, 'code' => $roomCode] + $attributes);
        $report->created($roomCode);
    }

    /**
     * Criticidad 1–3. Un valor fuera de rango o vacío cae a 1 en lugar de
     * fallar: es un dato de matiz, y bloquear la carga entera de un catálogo
     * por una celda mal escrita sería desproporcionado.
     */
    private function criticality(string $value): int
    {
        $n = (int) trim($value);

        return ($n >= 1 && $n <= 3) ? $n : 1;
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

        // Excel en Windows guarda con BOM y con punto y coma. Si el archivo
        // llega con comas, se reintenta: pelear con el separador es la causa
        // más común de que una importación "no lea nada".
        if ($header !== false && count($header) === 1) {
            rewind($handle);
            $header = fgetcsv($handle, 0, ',');
            $separator = ',';
        } else {
            $separator = ';';
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

        foreach (['sede', 'pabellon', 'piso', 'aula'] as $required) {
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
