<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Console;

use App\Modules\Analytics\Services\SnapshotBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Regenera los agregados diarios del tablero.
 *
 * Por defecto reprocesa los ultimos dias en lugar de solo ayer: una
 * incidencia puede cerrarse varios dias despues de reportarse, y el agregado
 * de aquel dia cambia cuando eso ocurre. Recalcular solo el ultimo dia
 * dejaria los anteriores congelados con datos incompletos.
 */
final class BuildSnapshotsCommand extends Command
{
    protected $signature = 'metrics:snapshot {--days=7 : Cuántos días hacia atrás reprocesar}';

    protected $description = 'Recalcula los agregados diarios de métricas';

    public function handle(SnapshotBuilder $builder): int
    {
        $days = max(1, (int) $this->option('days'));
        $written = 0;

        for ($i = 0; $i < $days; $i++) {
            $written += $builder->buildDay(Carbon::today()->subDays($i));
        }

        $this->info("Se actualizaron {$written} filas de agregados en {$days} día(s).");

        return self::SUCCESS;
    }
}
