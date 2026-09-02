<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Analytics\Services\ReportBuilder;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Building;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes por periodo, pabellon, aula, categoria, equipo y tecnico
 * (plan CU-S-15).
 *
 * El tablero responde "como va el servicio". Esto responde "donde se
 * concentra el problema", que es lo que sostiene una decision: cambiar los
 * proyectores del pabellon C, reforzar el turno de la tarde, comprar
 * cables.
 *
 * Se puede descargar en CSV porque el reporte va a acabar pegado en un
 * informe o en la tesis, y copiarlo a mano de la pantalla es como se
 * introducen los errores que nadie detecta despues.
 */
class ReportsController extends Controller
{
    public function __construct(private readonly ReportBuilder $reportes) {}

    public function index(Request $request)
    {
        $filtros = $this->filtros($request);
        $por = $this->agrupacion($request);

        return view('support.reports', [
            'filas' => $this->reportes->agrupar($por, $filtros),
            'totales' => $this->reportes->totales($filtros),
            'por' => $por,
            'filtros' => $filtros,
            'pabellones' => Building::query()->orderBy('code')->get(),
            'categorias' => IncidentCategory::query()->orderBy('sort_order')->get(),
            'tecnicos' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Mismo reporte que se ve en pantalla, en CSV. */
    public function export(Request $request): StreamedResponse
    {
        $filtros = $this->filtros($request);
        $por = $this->agrupacion($request);
        $filas = $this->reportes->agrupar($por, $filtros);

        $nombre = sprintf(
            'reporte-%s-%s-a-%s.csv',
            $por,
            $filtros['desde']->format('Y-m-d'),
            $filtros['hasta']->format('Y-m-d')
        );

        return response()->streamDownload(function () use ($filas, $por, $filtros): void {
            $salida = fopen('php://output', 'wb');

            // BOM: sin el, Excel en Windows abre "Pabellón" como "PabellÃ³n".
            fwrite($salida, "\xEF\xBB\xBF");

            // El periodo va DENTRO del archivo. Un CSV suelto sin su rango
            // de fechas no se puede citar: no se sabe de que habla.
            fputcsv($salida, ['Reporte agrupado por', $por]);
            fputcsv($salida, ['Desde', $filtros['desde']->format('d/m/Y')]);
            fputcsv($salida, ['Hasta', $filtros['hasta']->format('d/m/Y')]);
            fputcsv($salida, ['Generado', now()->timezone(config('incidencias.display_timezone'))->format('d/m/Y H:i')]);
            fputcsv($salida, []);

            fputcsv($salida, [
                ucfirst($por), 'Incidencias', 'Impidieron la clase', 'Resueltas sin visita',
                'Requirieron visita', 'Todavia abiertas', 'Minutos promedio hasta resolver',
            ]);

            foreach ($filas as $fila) {
                fputcsv($salida, [
                    $fila->etiqueta,
                    (int) $fila->total,
                    (int) $fila->bloquearon_clase,
                    (int) $fila->resueltas_sin_visita,
                    (int) $fila->requirieron_visita,
                    (int) $fila->abiertas,
                    $fila->minutos_promedio !== null ? round((float) $fila->minutos_promedio) : '',
                ]);
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function agrupacion(Request $request): string
    {
        $por = (string) $request->query('por', 'pabellon');

        return in_array($por, ReportBuilder::AGRUPACIONES, true) ? $por : 'pabellon';
    }

    /**
     * @return array{desde:Carbon,hasta:Carbon,building_id:?int,category_id:?int,assigned_to:?int}
     */
    private function filtros(Request $request): array
    {
        $desde = $this->fecha($request->query('desde')) ?? now()->subDays(30);
        $hasta = $this->fecha($request->query('hasta')) ?? now();

        // Fechas invertidas: el usuario quiso ese rango, no un resultado
        // vacio sin explicacion.
        if ($desde->greaterThan($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'desde' => $desde->startOfDay(),
            'hasta' => $hasta->endOfDay(),
            'building_id' => $request->integer('pabellon') ?: null,
            'category_id' => $request->integer('categoria') ?: null,
            'assigned_to' => $request->integer('tecnico') ?: null,
        ];
    }

    private function fecha(mixed $valor): ?Carbon
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor) ?: null;
        } catch (\Throwable) {
            // Una fecha ilegible en la barra de direcciones no debe tumbar
            // la pagina: se cae al rango por defecto.
            return null;
        }
    }
}
