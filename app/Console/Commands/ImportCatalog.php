<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Equipment\Services\EquipmentImporter;
use App\Modules\Locations\Services\ImportReport;
use App\Modules\Locations\Services\LocationImporter;
use Illuminate\Console\Command;

/**
 * Carga del catálogo real desde CSV (plan 22.2, CU-A-01 y CU-A-03).
 *
 * VISTA PREVIA POR DEFECTO. El comando no escribe nada hasta que se pasa
 * `--confirmar`. Es deliberado: quien carga el catálogo suele hacerlo una
 * vez, con un archivo que ha preparado a mano, y descubrir los errores
 * DESPUÉS de escribir en la base obliga a limpiar antes de reintentar. Así
 * el ciclo es: previsualizar, corregir el Excel, previsualizar otra vez, y
 * solo entonces confirmar.
 */
class ImportCatalog extends Command
{
    protected $signature = 'import:catalogo
        {tipo : aulas|equipos}
        {archivo : Ruta al CSV}
        {--confirmar : Escribir de verdad (sin esto solo simula)}';

    protected $description = 'Importa el catálogo real de aulas o equipos desde un CSV';

    public function handle(LocationImporter $locations, EquipmentImporter $equipment): int
    {
        $tipo = $this->argument('tipo');
        $archivo = $this->argument('archivo');
        $confirmar = (bool) $this->option('confirmar');

        if (! in_array($tipo, ['aulas', 'equipos'], true)) {
            $this->error('El tipo debe ser «aulas» o «equipos».');

            return self::FAILURE;
        }

        if (! $confirmar) {
            $this->warn('VISTA PREVIA — no se escribirá nada. Añade --confirmar para cargar de verdad.');
            $this->newLine();
        }

        $report = $tipo === 'aulas'
            ? $locations->import($archivo, ! $confirmar)
            : $equipment->import($archivo, ! $confirmar);

        return $this->render($report, $confirmar);
    }

    private function render(ImportReport $report, bool $confirmar): int
    {
        if ($report->hasErrors()) {
            $this->error('No se cargó NADA. Corrige estas filas y vuelve a intentarlo:');
            $this->newLine();

            foreach ($report->errors as $error) {
                $linea = $error['line'] === 0 ? 'archivo' : 'línea '.$error['line'];
                $this->line("  <fg=red>{$linea}</>: {$error['message']}");
            }

            $this->newLine();

            // Todo o nada: media carga deja un catálogo incompleto que parece
            // completo, y nadie sabe por dónde iba.
            $this->warn('La importación es todo o nada: una sola fila mal impide cargar el resto.');

            return self::FAILURE;
        }

        $nuevos = count($report->createdCodes);
        $actualizados = count($report->updatedCodes);

        if ($confirmar) {
            $this->info("Cargado: {$nuevos} nuevos, {$actualizados} actualizados.");
        } else {
            $this->info("Se cargarían: {$nuevos} nuevos, {$actualizados} actualizados.");
            $this->line('Todo correcto. Repite el comando con <options=bold>--confirmar</> para cargarlo.');
        }

        if ($nuevos > 0) {
            $muestra = array_slice($report->createdCodes, 0, 8);
            $this->line('  Nuevos: '.implode(', ', $muestra).($nuevos > 8 ? ', …' : ''));
        }

        return self::SUCCESS;
    }
}
