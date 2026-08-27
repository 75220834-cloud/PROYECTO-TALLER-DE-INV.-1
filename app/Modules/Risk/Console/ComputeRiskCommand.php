<?php

declare(strict_types=1);

namespace App\Modules\Risk\Console;

use App\Modules\Risk\Services\RiskEngine;
use Illuminate\Console\Command;

/**
 * Recalculo periodico de las señales de riesgo (plan 15, tarea programada).
 *
 * Se ejecuta de madrugada porque recorre el historial de todas las aulas y
 * no hay ninguna razon para hacerlo mientras alguien esta reportando una
 * incidencia.
 */
final class ComputeRiskCommand extends Command
{
    protected $signature = 'risk:compute';

    protected $description = 'Calcula las señales de riesgo por aula y por aula+categoría';

    public function handle(RiskEngine $engine): int
    {
        $written = $engine->computeAll();

        $this->info("Se calcularon {$written} señales de riesgo.");

        // Recordatorio deliberado en la salida del comando: quien lea estos
        // numeros debe saber de entrada que ordenan prioridades, no que
        // predicen averias (plan 15.2).
        $this->line('Recuerda: el baseline ordena prioridades de revisión; no es una probabilidad calibrada.');

        return self::SUCCESS;
    }
}
