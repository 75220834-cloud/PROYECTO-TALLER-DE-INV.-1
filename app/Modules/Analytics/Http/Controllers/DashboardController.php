<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\DatasetExporter;
use App\Modules\Analytics\Services\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tablero de métricas operativas (plan 15).
 *
 * Muestra también los indicadores incómodos —abandono, rechazos del
 * antiabuso— y no solo los que favorecen al sistema. Un tablero que solo
 * enseña lo que salió bien no sirve para tomar decisiones ni para sostener
 * una investigación.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly MetricsService $metrics) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->period($request);

        return view('support.dashboard', [
            'metrics' => $this->metrics->dashboard($from, $to),
            'days' => $request->integer('days', 30),
        ]);
    }

    public function export(DatasetExporter $exporter): StreamedResponse
    {
        return $exporter->stream();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        // Se acota a un año: un rango arbitrario permitiría lanzar una
        // consulta que recorra toda la base desde la barra de direcciones.
        $days = max(1, min(365, $request->integer('days', 30)));

        return [now()->subDays($days)->startOfDay(), now()->endOfDay()];
    }
}
