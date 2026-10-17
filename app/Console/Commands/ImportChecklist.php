<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Locations\Services\ChecklistImporter;
use App\Modules\Locations\Services\ChecklistReport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Carga el checklist de aulas y equipos de soporte.
 *
 * VISTA PREVIA POR DEFECTO. No escribe nada hasta que se pasa `--confirmar`.
 * Quien carga el catalogo lo hace con un archivo que preparo otra persona, y
 * descubrir los errores DESPUES de escribir obliga a limpiar antes de
 * reintentar. El ciclo correcto es: previsualizar, corregir el checklist,
 * previsualizar otra vez, y solo entonces confirmar.
 */
class ImportChecklist extends Command
{
    protected $signature = 'import:checklist
        {archivo : Ruta al CSV exportado del checklist}
        {--confirmar : Escribir de verdad (sin esto solo simula)}
        {--sede=HYO : Código de la sede a la que pertenece este checklist}
        {--nombre-sede= : Nombre de la sede, obligatorio si todavía no existe}';

    protected $description = 'Carga las aulas y sus equipos desde el checklist de soporte';

    public function handle(ChecklistImporter $importer): int
    {
        $confirmar = (bool) $this->option('confirmar');

        if (! $confirmar) {
            $this->warn('VISTA PREVIA — no se escribirá nada. Añade --confirmar para cargar de verdad.');
            $this->newLine();
        }

        try {
            $report = $importer->import(
                $this->argument('archivo'),
                ! $confirmar,
                (string) $this->option('sede'),
                $this->option('nombre-sede') !== null ? (string) $this->option('nombre-sede') : null,
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return $this->render($report, $confirmar);
    }

    private function render(ChecklistReport $report, bool $confirmar): int
    {
        if ($report->hasErrors()) {
            $this->error('No se cargó NADA. Corrige estas filas y vuelve a intentarlo:');
            $this->newLine();

            foreach ($report->errors() as $error) {
                $this->line(sprintf('  Fila %d: %s', $error['linea'], $error['mensaje']));
            }

            return self::FAILURE;
        }

        $this->info('Sede: '.mb_strtoupper((string) $this->option('sede'), 'UTF-8'));
        $this->info(sprintf(
            'Aulas: %d (%d nuevas, %d actualizadas)',
            $report->roomsTotal(),
            $report->roomsCreated(),
            $report->roomsUpdated()
        ));

        $supportOnly = $report->supportOnlyRooms();

        if ($supportOnly !== []) {
            $this->newLine();
            $this->line(sprintf(
                '  %d aulas van directo a soporte y no llevan inventario:',
                count($supportOnly)
            ));
            $this->line('  '.implode(', ', $supportOnly));
        }

        $this->newLine();
        $this->line('Equipos registrados:');

        foreach ($report->equipment() as $tipo => $total) {
            $this->line(sprintf('  %-18s %4d', $tipo, $total));
        }

        $missing = $report->missing();

        if ($missing !== []) {
            $this->newLine();

            // Lo mas importante que hay que revisar antes de confirmar: cada
            // ausencia es un boton que el docente NO va a ver, y eso no
            // produce ningun error visible.
            $this->warn('Equipos que el checklist NO registra (el docente no verá ese botón):');

            foreach ($missing as $tipo => $aulas) {
                $this->line(sprintf('  %-18s %4d aulas', $tipo, count($aulas)));
                $this->line('    '.implode(', ', $aulas));
            }
        }

        $this->newLine();

        if ($confirmar) {
            $this->info('Cargado.');
        } else {
            $this->warn('Nada de esto se escribió. Repite con --confirmar cuando los números cuadren.');
        }

        return self::SUCCESS;
    }
}
