<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Assistant\Services\IncidentSuggestions;
use App\Modules\Incidents\Models\Incident;
use Illuminate\View\View;

/**
 * Sugerencias de la IA para la ficha de una incidencia.
 *
 * Va en una dirección aparte y la ficha la pide después de mostrarse: si la
 * IA tarda —el modelo puede estar arrancando—, el técnico ya está leyendo la
 * incidencia en lugar de mirar una página en blanco.
 */
class IncidentSuggestionsController extends Controller
{
    public function __invoke(Incident $incident, IncidentSuggestions $suggestions): View
    {
        $incident->load('category');

        return view('support.incidents.sugerencias', [
            'incident' => $incident,
            'sugerencias' => $suggestions->for($incident),
        ]);
    }
}
