<?php

declare(strict_types=1);

namespace App\Modules\Locations\Services;

use App\Modules\Equipment\Models\Equipment;
use App\Modules\Equipment\Models\EquipmentType;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Carga el checklist de soporte: una fila por aula, una columna por equipo.
 *
 * POR QUE UN IMPORTADOR APARTE Y NO EL DE CATALOGO
 *
 * El importador de catalogo espera dos archivos —uno de aulas y otro de
 * equipos, con una fila por equipo—. El checklist real de soporte tiene otra
 * forma: una sola fila por aula, y los equipos son columnas que dicen "OK" o
 * se quedan vacias. Obligar a soporte a partir su documento en dos y a
 * transponerlo seria pedirle que haga a mano el trabajo que hace esta clase,
 * y cada transposicion manual es una oportunidad de equivocarse.
 *
 * LO QUE SIGNIFICA UNA CELDA VACIA
 *
 * No es un dato que falte: es informacion. El checklist solo marca "OK" lo
 * que existe y soporte puede atender. Una celda vacia significa que ese
 * equipo no esta, o que lo lleva soporte por su cuenta, y en los dos casos
 * el resultado para el docente es el mismo: ese boton no le sirve. Por eso
 * la ausencia se registra como ausencia del equipo, y la pantalla del
 * docente la respeta.
 *
 * LA JERARQUIA SALE DEL CODIGO DEL AULA
 *
 * El checklist no trae columna de piso. Se deduce del codigo: G802 esta en
 * el piso 8, y GS03 en el sotano. Es la unica deduccion que hace esta clase,
 * y cualquier codigo que no encaje en el patron se reporta como error en
 * lugar de adivinarse.
 *
 * VISTA PREVIA POR TRANSACCION
 *
 * En vista previa se escribe de verdad y se deshace al final. Suena raro y
 * es lo correcto: simular la escritura obligaria a duplicar toda la logica
 * de "esto ya existia" con datos que no existen, y esa copia se
 * desincronizaria de la real en el primer cambio. Asi el recuento que se le
 * enseña a quien carga es exactamente el que va a ocurrir.
 */
final class ChecklistImporter
{
    /**
     * Sede por defecto: la del piloto.
     *
     * El checklist no trae columna de sede, asi que cada archivo se carga
     * contra UNA sede, indicada al importar. Sin indicarla se asume la del
     * piloto, para que la carga de siempre no cambie. Otra sede es otro
     * checklist cargado con su propio codigo: sus pabellones quedan
     * separados aunque se llamen igual que los de Huancayo.
     */
    public const DEFAULT_SITE_CODE = 'HYO';

    private const DEFAULT_SITE_NAME = 'Huancayo';

    /**
     * Columna del checklist => codigo del tipo de equipo.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'PC\AIO\NUC' => 'COMPUTER',
        'MOUSE' => 'MOUSE',
        'TECLADO' => 'KEYBOARD',
        'PARLANTE' => 'SPEAKER',
        'PROYECTOR' => 'PROJECTOR',
        'CONTROL DEL PROYECTOR' => 'PROJECTOR_REMOTE',
        'TIPO DE ECRAM' => 'SCREEN',
        'HDMI' => 'HDMI_CABLE',
    ];

    /**
     * Ambientes que el docente no puede diagnosticar por su cuenta.
     *
     * Las aulas HYFLEX fallan por software y este proyecto solo aborda
     * hardware, asi que quien reporta desde una de ellas va directo a
     * soporte. Se cargan igual, y con el motivo escrito: si no existieran,
     * el docente de C305 no encontraria su aula, concluiria que el sistema
     * esta roto y volveria al canal informal — que es exactamente lo que el
     * piloto quiere medir que desaparece.
     */
    private const SUPPORT_ONLY_TYPES = ['HYFLEX'];

    private const SUPPORT_ONLY_REASON = 'Las aulas HYFLEX las atiende soporte técnico directamente.';

    public function import(
        string $path,
        bool $dryRun = true,
        string $siteCode = self::DEFAULT_SITE_CODE,
        ?string $siteName = null,
    ): ChecklistReport {
        $siteCode = mb_strtoupper(trim($siteCode), 'UTF-8');
        $rows = $this->readCsv($path);
        $report = new ChecklistReport;

        DB::beginTransaction();

        try {
            $site = $this->site($siteCode, $siteName);
            $types = EquipmentType::query()->pluck('id', 'code');

            foreach ($rows as $line => $row) {
                $this->importRow($row, $line, $site, $types, $report);
            }
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        // Media carga es peor que ninguna: deja un catalogo incompleto que
        // parece completo, y nadie sabe por donde iba.
        if ($dryRun || $report->hasErrors()) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        return $report;
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, int>  $types
     */
    private function importRow(array $row, int $line, Site $site, Collection $types, ChecklistReport $report): void
    {
        $buildingCode = trim($row['PABELLÓN'] ?? '');
        $roomCode = trim($row['AMBIENTE'] ?? '');
        $roomType = mb_strtolower(trim($row['TIPO DE AMBIENTE'] ?? ''), 'UTF-8');

        if ($buildingCode === '' || $roomCode === '') {
            $report->addError($line, 'Falta el pabellón o el código del aula.');

            return;
        }

        $floor = $this->floorFromCode($roomCode);

        if ($floor === null) {
            $report->addError($line, sprintf(
                'El código «%s» no permite deducir el piso. Se esperaba algo como G802 o GS03.',
                $roomCode
            ));

            return;
        }

        $floorModel = $this->floor($this->building($site, $buildingCode), $floor);

        $selfService = ! in_array(mb_strtoupper($roomType, 'UTF-8'), self::SUPPORT_ONLY_TYPES, true);

        $room = Room::query()->firstOrNew(['floor_id' => $floorModel->id, 'code' => $roomCode]);
        $existia = $room->exists;

        $room->fill([
            'name' => $roomCode,
            'room_type' => $roomType !== '' ? $roomType : 'aula',
            'criticality' => $this->criticality($roomType),
            'self_service' => $selfService,
            'support_only_reason' => $selfService ? null : self::SUPPORT_ONLY_REASON,
            'is_active' => true,
            'is_demo' => false,
        ])->save();

        $existia ? $report->roomUpdated() : $report->roomCreated();

        // Las aulas que no se autoatienden no necesitan inventario: el
        // docente nunca va a elegir un equipo en ellas.
        if (! $selfService) {
            $report->supportOnly($roomCode);

            return;
        }

        $this->syncEquipment($row, $room, $site, $types, $report);
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, int>  $types
     */
    private function syncEquipment(array $row, Room $room, Site $site, Collection $types, ChecklistReport $report): void
    {
        // El codigo de activo es unico en toda la base, y dos sedes pueden
        // tener un aula A101. En la sede del piloto se conserva el formato
        // de siempre, para no renombrar los equipos ya cargados; en las
        // demas se antepone el codigo de la sede.
        $prefix = $site->code === self::DEFAULT_SITE_CODE ? '' : $site->code.'-';

        foreach (self::COLUMNS as $column => $typeCode) {
            $value = trim($row[$column] ?? '');
            $typeId = $types[$typeCode] ?? null;

            if ($typeId === null) {
                $report->addError(0, sprintf('Falta el tipo de equipo «%s» en el catálogo.', $typeCode));

                continue;
            }

            if ($value === '') {
                $report->addMissing($typeCode, $room->code);

                continue;
            }

            /*
             * El codigo de activo se compone del aula y del tipo. El
             * checklist no trae numeros de inventario, e inventarlos seria
             * fabricar un dato institucional que nadie ha registrado: este
             * codigo es explicitamente derivado, no un numero de patrimonio.
             */
            Equipment::query()->updateOrCreate(
                ['room_id' => $room->id, 'equipment_type_id' => $typeId],
                [
                    'asset_code' => $prefix.$room->code.'-'.$typeCode,

                    // "AIO", "PC", "NUC" o "LAPTOP" en la columna de
                    // computadora. En las demas el checklist solo dice "OK",
                    // que no es un modelo y por eso no se guarda como tal.
                    'model' => $value !== 'OK' ? mb_substr($value, 0, 80) : null,
                    'status' => 'operational',
                    'is_active' => true,
                    'is_demo' => false,
                ]
            );

            $report->equipmentCounted($typeCode);
        }
    }

    /**
     * Piso a partir del codigo del aula.
     *
     * G802 -> 8.  GS03 -> sotano.  C201-A -> 2.
     *
     * @return array{numero: int, etiqueta: string}|null
     */
    private function floorFromCode(string $code): ?array
    {
        $normalized = mb_strtoupper(trim($code), 'UTF-8');

        // El sotano se comprueba primero: en "GS03" la letra del pabellon y
        // la S van pegadas, y un patron generico se comeria la S.
        if (preg_match('/^[A-Z]S\d/', $normalized) === 1) {
            // Cero y no negativo: asi se lista antes del piso 1 sin tener
            // que ordenar al reves en ningun sitio.
            return ['numero' => 0, 'etiqueta' => 'Sótano'];
        }

        if (preg_match('/^[A-Z]+(\d)/', $normalized, $m) === 1) {
            $numero = (int) $m[1];

            return ['numero' => $numero, 'etiqueta' => 'Piso '.$numero];
        }

        return null;
    }

    /**
     * La criticidad pondera CONSECUENCIAS, no probabilidad de averia.
     *
     * Un laboratorio detenido deja sin practica a un grupo entero y suele
     * tener el horario mas apretado que un aula. Es el mismo criterio que ya
     * usa el calculo de prioridad.
     */
    private function criticality(string $roomType): int
    {
        return str_contains($roomType, 'laboratorio') ? 2 : 1;
    }

    private function site(string $code, ?string $name): Site
    {
        if ($code === '') {
            throw new InvalidArgumentException('El código de la sede no puede estar vacío.');
        }

        $site = Site::query()->firstOrNew(['code' => $code]);
        $name = $name !== null ? trim($name) : null;

        if ($name === null || $name === '') {
            $name = $site->exists ? $site->name : null;
            $name ??= $code === self::DEFAULT_SITE_CODE ? self::DEFAULT_SITE_NAME : null;
        }

        // Una sede nueva sin nombre se rechaza en lugar de inventarle uno:
        // ese nombre es lo que el docente ve al elegir donde esta.
        if ($name === null) {
            throw new InvalidArgumentException(
                "La sede {$code} no existe todavía. Indica su nombre con --nombre-sede=\"...\"."
            );
        }

        $site->fill(['name' => $name, 'is_active' => true, 'is_demo' => false])->save();

        return $site;
    }

    private function building(Site $site, string $code): Building
    {
        $building = Building::query()->firstOrNew(['site_id' => $site->id, 'code' => $code]);

        $building->fill([
            'name' => 'Pabellón '.$code,

            // Alfabetico por el codigo, que es como el docente los busca en
            // la fachada: A, B, C... No hay otro orden que le sirva.
            'sort_order' => ord($code[0]),
            'is_active' => true,
            'is_demo' => false,
        ])->save();

        return $building;
    }

    /** @param  array{numero: int, etiqueta: string}  $floor */
    private function floor(Building $building, array $floor): Floor
    {
        $model = Floor::query()->firstOrNew([
            'building_id' => $building->id,
            'number' => $floor['numero'],
        ]);

        $model->fill(['label' => $floor['etiqueta'], 'is_active' => true, 'is_demo' => false])->save();

        return $model;
    }

    /**
     * Lee el CSV del checklist.
     *
     * La primera fila es una cabecera de grupos (AMBIENTE / EQUIPO DOCENTE /
     * EQUIPO MULTIMEDIA) que Excel deja al exportar y que no contiene datos.
     * La segunda es la de columnas de verdad.
     *
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException('No se puede leer el archivo: '.$path);
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo: '.$path);
        }

        $rows = [];
        $headers = null;
        $line = 0;

        while (($fields = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $line++;

            if ($line === 1) {
                continue; // Cabecera de grupos.
            }

            if ($headers === null) {
                // El BOM que escribe Excel se pega a la primera celda y
                // rompe la comparacion del nombre de columna.
                $headers = array_map(
                    static fn (?string $h): string => trim(str_replace("\u{FEFF}", '', (string) $h)),
                    $fields
                );

                continue;
            }

            if (implode('', array_map('trim', $fields)) === '') {
                continue; // Fila vacia al final del archivo.
            }

            $row = [];

            foreach ($headers as $i => $header) {
                if ($header !== '') {
                    $row[$header] = (string) ($fields[$i] ?? '');
                }
            }

            $rows[$line] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
