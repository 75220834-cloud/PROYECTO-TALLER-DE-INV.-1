<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Models\Incident;
use Illuminate\View\View;

/**
 * Seguimiento de la incidencia para el docente (plan CU-D-11).
 *
 * EL PROBLEMA QUE RESUELVE: el docente pide soporte y se queda a ciegas. No
 * sabe si alguien vio su solicitud, si va en camino o si se olvidaron de él.
 * Esa incertidumbre es la mitad de la sensación de lentitud — y es la que
 * provoca la llamada de «¿ya viene?» que el sistema existía para evitar.
 *
 * SE ENTRA POR UUID Y SIN CONTRASEÑA, igual que todo lo del docente. El uuid
 * no es adivinable y solo abre ESTA incidencia: no permite listar, ni buscar,
 * ni tocar nada. Pedirle una cuenta al docente para ver su propio ticket
 * contradiría la decisión D-2 del plan, que es que no se autentica nunca.
 *
 * MUESTRA MENOS DE LO QUE SABE. El nombre del técnico asignado no aparece:
 * el docente necesita saber que alguien se hizo cargo, no quién es. Poner el
 * nombre invita a buscarlo por WhatsApp y salta el canal que la
 * investigación está midiendo.
 */
class TrackingController extends Controller
{
    public function __invoke(string $uuid): View
    {
        $incident = Incident::query()
            ->where('uuid', $uuid)
            ->where('is_draft', false)
            ->with(['room', 'category', 'status', 'priority'])
            ->firstOrFail();

        return view('teacher.tracking', [
            'incident' => $incident,

            /*
             * La línea de tiempo se arma con las marcas de la propia
             * incidencia y no leyendo el historial de eventos: el docente
             * necesita cuatro hitos, no la traza técnica completa. Cada
             * entrada existe solo si su marca existe, así que la lista crece
             * conforme avanza la atención.
             */
            'hitos' => collect([
                ['t' => $incident->reported_at, 'titulo' => 'Enviaste la solicitud', 'icono' => 'send'],
                ['t' => $incident->first_response_at, 'titulo' => 'Soporte la recibió', 'icono' => 'visibility'],
                ['t' => $incident->assigned_at, 'titulo' => 'Un técnico se hizo cargo', 'icono' => 'engineering'],
                ['t' => $incident->arrived_at, 'titulo' => 'El técnico llegó al aula', 'icono' => 'meeting_room'],
                ['t' => $incident->resolved_at, 'titulo' => 'Problema resuelto', 'icono' => 'check_circle'],
            ])->filter(fn (array $h): bool => $h['t'] !== null)->values(),
        ]);
    }
}
