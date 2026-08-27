<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Console;

use App\Modules\Incidents\Models\Incident;
use App\Shared\Enums\IncidentStatus;
use Illuminate\Console\Command;

/**
 * Cierra los borradores que el docente nunca completó.
 *
 * Un borrador es un docente que confirmó su aula y se fue sin reportar
 * nada. No son basura: son EL indicador de fricción del QR genérico, y por
 * eso NO se borran de la base (plan 26.bis). Lo que hace este comando es
 * marcarlos como cancelados para que dejen de contar como "en curso".
 *
 * Distinguir "abandonado" de "en curso" importa porque un docente que
 * dejó el móvil bloqueado a mitad del reporte no debe aparecer para
 * siempre como una incidencia viva en las métricas.
 */
class PurgeAbandonedDrafts extends Command
{
    protected $signature = 'incidents:purge-drafts {--dry-run : Solo informar, sin modificar nada}';

    protected $description = 'Marca como abandonados los borradores que nadie completó';

    public function handle(): int
    {
        $hours = (int) config('incidencias.abuse.draft_purge_hours');
        $cutoff = now()->subHours($hours);

        $query = Incident::query()
            ->where('is_draft', true)
            ->where('created_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No hay borradores abandonados.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("Se marcarían {$count} borrador(es) anteriores a {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $cancelled = \App\Modules\Incidents\Models\IncidentStatus::idFor(
            IncidentStatus::Cancelled
        );

        // Se actualiza en bloque y sin pasar por la maquina de estados: no
        // es una decision de negocio de nadie, es mantenimiento. El evento
        // que SI importa (location_confirmed) ya quedo registrado cuando el
        // docente entro, y es el que alimenta la metrica de abandono.
        $query->update([
            'status_id' => $cancelled,
            'is_draft' => false,
            'updated_at' => now(),
        ]);

        $this->info("Marcados {$count} borrador(es) abandonado(s).");

        return self::SUCCESS;
    }
}
