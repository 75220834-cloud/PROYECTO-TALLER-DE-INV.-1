<?php

declare(strict_types=1);

namespace App\Modules\Risk\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Room;
use App\Modules\Risk\Services\RiskEngine;
use Illuminate\View\View;

/**
 * Señales de riesgo para el equipo de soporte (plan 15.5, CU-S-14).
 *
 * La pantalla muestra el score SIEMPRE junto a sus factores. No hay una
 * vista "solo el numero": un score sin explicacion acaba ignorandose, y un
 * modulo que se ignora no sirve para nada.
 */
class RiskController extends Controller
{
    public function __construct(private readonly RiskEngine $engine) {}

    public function index(): View
    {
        $rooms = $this->engine->current('room', 15);
        $pairs = $this->engine->current('room_category', 15);

        // Se resuelven los nombres en bloque para no consultar el catalogo
        // dentro del bucle de la vista.
        $roomCodes = Room::query()
            ->whereIn('id', $rooms->pluck('scope_id')->merge($pairs->pluck('scope_id'))->unique())
            ->pluck('code', 'id');

        $categoryNames = IncidentCategory::query()
            ->whereIn('id', $pairs->pluck('category_id')->filter()->unique())
            ->pluck('name', 'id');

        return view('support.risk', [
            'rooms' => $rooms,
            'pairs' => $pairs,
            'roomCodes' => $roomCodes,
            'categoryNames' => $categoryNames,
            'horizon' => (int) config('incidencias.risk.horizon_days'),
        ]);
    }
}
