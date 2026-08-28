<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Incidents\Models\IncidentPriority;
use App\Modules\Media\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogo de categorías de incidencia (plan CU-A-04).
 *
 * EL CÓDIGO NO SE PUEDE CAMBIAR una vez creada la categoría, y esta es la
 * regla que sostiene todo lo demás. El `code` es lo que usan la lógica del
 * sistema, el clasificador de intención y las consultas de la
 * investigación; el `name` es solo lo que se lee en pantalla. Separarlos
 * permite renombrar «Proyector» a «Proyector multimedia» sin romper nada.
 * Si el código fuera editable, cambiarlo dejaría huérfanos los tickets ya
 * cerrados que el análisis va a leer después.
 *
 * TAMPOCO SE BORRAN. Una categoría retirada sigue clasificando incidencias
 * históricas; borrarla dejaría huecos en los datos. Se desactiva, que la
 * esconde del docente sin tocar el pasado.
 */
class CategoryController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => IncidentCategory::with('defaultPriority')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new IncidentCategory,
            'priorities' => IncidentPriority::orderBy('level')->get(),
            'assets' => $this->assets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $category = IncidentCategory::create($data + ['is_active' => true]);

        $this->audit->record('category.created', $category, ['code' => $category->code]);

        return redirect()->route('admin.categories.index')
            ->with('status', "Categoría «{$category->name}» creada.");
    }

    public function edit(IncidentCategory $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'priorities' => IncidentPriority::orderBy('level')->get(),
            'assets' => $this->assets(),
        ]);
    }

    public function update(Request $request, IncidentCategory $category): RedirectResponse
    {
        $data = $this->validated($request, $category);

        // El código se descarta del formulario de edición: aunque alguien lo
        // enviara manipulando la petición, aquí no se aplica.
        unset($data['code']);

        $category->update($data);

        $this->audit->record('category.updated', $category, ['name' => $category->name]);

        return redirect()->route('admin.categories.index')
            ->with('status', "Categoría «{$category->name}» actualizada.");
    }

    public function toggle(IncidentCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        $this->audit->record('category.toggled', $category, ['is_active' => $category->is_active]);

        return back()->with('status', $category->is_active
            ? "«{$category->name}» vuelve a ofrecerse al docente."
            : "«{$category->name}» ya no se ofrece al docente. Las incidencias antiguas la conservan.");
    }

    /**
     * Imágenes disponibles para representar la categoría en la pantalla del
     * docente. Un icono ayuda a reconocer «proyector» sin leer, que importa
     * en la pantalla más usada del sistema.
     */
    private function assets()
    {
        return MediaAsset::where('is_active', true)
            ->whereIn('scope_type', ['category', 'equipment_type', 'generic'])
            ->orderBy('code')
            ->get();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?IncidentCategory $category): array
    {
        return $request->validate([
            'code' => [
                $category === null ? 'required' : 'nullable',
                'string', 'max:40', 'regex:/^[A-Z0-9_]+$/',
                Rule::unique('incident_categories', 'code')->ignore($category?->id),
            ],
            'name' => ['required', 'string', 'max:120'],

            // Lo que de verdad lee el docente. Puede ser más largo y más
            // llano que el nombre interno: «No se ve la imagen» comunica
            // mucho mejor que «Proyector».
            'teacher_label' => ['nullable', 'string', 'max:120'],

            'default_priority_id' => ['nullable', 'integer', 'exists:incident_priorities,id'],
            'media_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'code.regex' => 'El código solo admite MAYÚSCULAS, números y guion bajo. Por ejemplo: PROJECTOR',
            'code.unique' => 'Ya existe una categoría con ese código.',
        ]);
    }
}
