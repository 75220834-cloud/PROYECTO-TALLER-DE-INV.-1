<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Locations\Models\Room;
use App\Modules\Risk\Models\RiskScore;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Historial de un aula (plan CU-S-12).
 *
 * PARA QUÉ SIRVE DE VERDAD: que el técnico salga con la pieza correcta en la
 * mano. Si en C305 las últimas tres veces fue el cable HDMI, ir sin cable es
 * garantizar un segundo viaje — y el segundo viaje es exactamente el coste
 * que el proyecto existe para reducir.
 *
 * Por eso lo primero que muestra no es la lista de incidencias sino QUÉ
 * FALLA MÁS y CÓMO SE RESOLVIÓ. La lista viene después, para el que quiera
 * el detalle.
 */
class RoomHistoryController extends Controller
{
    public function __invoke(Room $room): View
    {
        $incidencias = Incident::query()
            ->where('room_id', $room->id)
            ->where('is_draft', false)
            ->with(['category', 'status', 'priority', 'assignee'])
            ->orderByDesc('reported_at')
            ->paginate(20);

        return view('support.room-history', [
            'room' => $room->load('floor.building'),
            'incidencias' => $incidencias,

            // Qué falla más en esta aula, con qué frecuencia y cuántas de
            // esas veces hizo falta ir.
            'porCategoria' => DB::table('incidents')
                ->leftJoin('incident_categories as c', 'c.id', '=', 'incidents.category_id')
                ->where('incidents.room_id', $room->id)
                ->where('incidents.is_draft', false)
                ->selectRaw('
                    COALESCE(c.name, "Sin clasificar") AS categoria,
                    COUNT(*) AS total,
                    SUM(CASE WHEN incidents.resolution_type = "onsite" THEN 1 ELSE 0 END) AS presenciales
                ')
                ->groupBy('categoria')
                ->orderByDesc('total')
                ->get(),

            // Lo que de verdad se hizo la última vez. Es el dato que evita el
            // segundo viaje, y por eso se muestra el texto tal cual lo
            // escribió el técnico en lugar de un resumen.
            'ultimasSoluciones' => Incident::query()
                ->where('room_id', $room->id)
                ->whereNotNull('resolution_notes')
                ->with('category')
                ->orderByDesc('resolved_at')
                ->limit(5)
                ->get(),

            'riesgo' => RiskScore::query()
                ->where('scope_type', 'room')
                ->where('scope_id', $room->id)
                ->latest('computed_at')
                ->first(),

            'equipos' => DB::table('equipment')
                ->leftJoin('equipment_types as t', 't.id', '=', 'equipment.equipment_type_id')
                ->where('equipment.room_id', $room->id)
                ->select('equipment.asset_code', 'equipment.status', 'equipment.brand', 'equipment.model', 't.name as tipo')
                ->orderBy('t.name')
                ->get(),
        ]);
    }
}
