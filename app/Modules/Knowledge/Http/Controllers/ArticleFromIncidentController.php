<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Knowledge\Services\SolutionToArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Convertir una solución registrada en artículo (plan CU-S-16).
 *
 * Solo se ofrece sobre incidencias YA resueltas y con notas: un artículo
 * sacado de un ticket abierto documentaría algo que todavía no se sabe si
 * funcionó.
 */
class ArticleFromIncidentController extends Controller
{
    public function __construct(private readonly SolutionToArticle $articulos) {}

    public function create(Incident $incident, Request $request): View|RedirectResponse
    {
        if (($guard = $this->guard($incident)) !== null) {
            return $guard;
        }

        return view('admin.knowledge.from-incident', [
            'incident' => $incident->load(['room', 'category']),
            'titulo' => $this->tituloSugerido($incident),
            'cuerpo' => $this->articulos->borrador($incident),
        ]);
    }

    public function store(Incident $incident, Request $request): RedirectResponse
    {
        if (($guard = $this->guard($incident)) !== null) {
            return $guard;
        }

        $datos = $request->validate([
            'title' => ['required', 'string', 'min:10', 'max:200'],
            // 80 caracteres mínimo: por debajo de eso lo que se está
            // guardando es la nota de resolución tal cual, y eso no le
            // sirve a quien no estuvo ahí.
            'body' => ['required', 'string', 'min:80', 'max:20000'],
            'summary' => ['nullable', 'string', 'max:500'],
        ], [], [
            'title' => 'título',
            'body' => 'contenido',
            'summary' => 'resumen',
        ]);

        $document = $this->articulos->crear(
            $incident,
            $request->user(),
            $datos['title'],
            $datos['body'],
            $datos['summary'] ?? null
        );

        return redirect()
            ->route('admin.knowledge.show', $document)
            ->with('status', 'Artículo creado como borrador. Revísalo y publícalo para que el asistente pueda usarlo.');
    }

    /**
     * Motivos por los que este ticket no puede convertirse en artículo.
     * Se explica el porqué: un botón que rebota sin decir nada se lee como
     * un error del sistema.
     */
    private function guard(Incident $incident): ?RedirectResponse
    {
        if ($incident->resolved_at === null) {
            return redirect()->route('support.incidents.show', $incident)
                ->with('error', 'Todavía no está resuelta. Un artículo sacado de un ticket abierto documenta algo que aún no se sabe si funcionó.');
        }

        if ($incident->resolution_notes === null || trim($incident->resolution_notes) === '') {
            return redirect()->route('support.incidents.show', $incident)
                ->with('error', 'Este ticket se cerró sin explicar qué se hizo, así que no hay nada que documentar.');
        }

        if ($incident->knowledgeArticle()->exists()) {
            return redirect()->route('support.incidents.show', $incident)
                ->with('error', 'De este ticket ya salió un artículo.');
        }

        return null;
    }

    private function tituloSugerido(Incident $incident): string
    {
        return trim(sprintf(
            '%s — cómo se resolvió',
            $incident->category->name ?? 'Incidencia'
        ));
    }
}
