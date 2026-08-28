<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Models\Incident;
use App\Shared\Enums\IncidentStatus as S;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Aviso al panel de que entró una incidencia (plan 12, CU-S-02).
 *
 * EL PROBLEMA QUE RESUELVE: un técnico no está mirando la bandeja todo el
 * día. Sin aviso, una incidencia urgente puede esperar veinte minutos a que
 * alguien recargue la página — y el docente está de pie frente a su clase.
 * El sistema mide «tiempo hasta la primera respuesta»; ese tiempo muerto
 * ensucia el indicador central de la investigación además de al docente.
 *
 * POR QUÉ SONDEO Y NO TIEMPO REAL. La decisión D-9 del plan lo fija: para el
 * MVP, sondeo. WebSockets exigirían otro proceso corriendo en el servidor
 * institucional, y para decenas de incidencias al día no compensa. Una
 * consulta cada 20 segundos sobre una tabla indexada es barata y no añade
 * nada que mantener.
 *
 * Devuelve SOLO lo que el panel necesita para avisar: cuántas y cuál es la
 * última. Ni la descripción libre ni el contacto del docente salen por aquí
 * — es un endpoint que se llama cientos de veces al día y no tiene por qué
 * pasear datos que nadie va a leer en un aviso.
 */
class AlertController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /*
         * `desde` viene del navegador y por eso NO se confía en él: se acota
         * a una hora hacia atrás. Sin el tope, una marca manipulada haría
         * recorrer la tabla entera de incidencias en cada sondeo.
         */
        $desde = $this->parseSince($request->query('desde'));

        $nuevas = Incident::query()
            ->visibleToSupport()
            ->where('reported_at', '>', $desde)
            ->orderByDesc('reported_at')
            ->with(['room:id,code', 'category:id,name', 'priority:id,code,name'])
            ->limit(10)
            ->get();

        return response()->json([
            'total' => $nuevas->count(),

            // Las críticas se cuentan aparte para que el panel pueda dar un
            // aviso distinto: no es lo mismo un proyector en un aula vacía
            // que un equipo echando humo.
            'criticas' => $nuevas->filter(
                fn (Incident $i): bool => $i->hazard_reported || $i->priority?->code === 'CRITICAL'
            )->count(),

            'incidencias' => $nuevas->map(fn (Incident $i): array => [
                'id' => $i->id,
                'ticket' => $i->ticket_number,
                'aula' => $i->room?->code,
                'categoria' => $i->category?->name,
                'prioridad' => $i->priority?->name,
                'riesgo' => (bool) $i->hazard_reported,
                'bloquea_clase' => (bool) $i->blocks_class,
                'url' => route('support.incidents.show', $i),
            ])->values(),

            // El servidor manda su reloj: si el navegador va desfasado, el
            // panel volvería a avisar de lo mismo una y otra vez.
            'ahora' => now()->toIso8601String(),
        ]);
    }

    private function parseSince(mixed $valor): Carbon
    {
        $tope = now()->subHour();

        if (! is_string($valor) || $valor === '') {
            return $tope;
        }

        try {
            $fecha = Carbon::parse($valor);
        } catch (\Throwable) {
            return $tope;
        }

        return $fecha->lessThan($tope) ? $tope : $fecha;
    }
}
