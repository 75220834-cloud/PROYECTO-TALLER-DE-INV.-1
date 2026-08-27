<?php

declare(strict_types=1);

namespace App\Modules\Locations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Services\LocationDependencyGuard;
use App\Modules\Locations\Services\LocationEntityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FloorController extends Controller
{
    public function index(Request $request, LocationDependencyGuard $guard): View
    {
        $floors = Floor::query()
            ->with('building.site')
            ->withCount('rooms')
            ->when($request->integer('building_id'), fn ($q, $id) => $q->where('building_id', $id))
            ->orderBy('building_id')
            ->orderBy('number')
            ->paginate(20)
            ->withQueryString();

        return view('admin.floors.index', [
            'floors' => $floors,
            'buildings' => Building::with('site')->orderBy('code')->get(),
            'guard' => $guard,
        ]);
    }

    public function create(): View
    {
        return view('admin.floors.form', [
            'floor' => new Floor,
            'buildings' => Building::active()->with('site')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);

        $floor = Floor::create($data + ['is_demo' => false]);
        $audit->record('locations.create', $floor, $data);

        return redirect()->route('admin.floors.index')->with('status', 'Piso creado.');
    }

    public function edit(Floor $floor): View
    {
        return view('admin.floors.form', [
            'floor' => $floor,
            'buildings' => Building::active()->with('site')->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Floor $floor, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $floor);

        $audit->record('locations.update', $floor, ['from' => $floor->only(array_keys($data)), 'to' => $data]);
        $floor->update($data);

        return redirect()->route('admin.floors.index')->with('status', 'Piso actualizado.');
    }

    public function destroy(Floor $floor, LocationEntityActions $actions): RedirectResponse
    {
        return $actions->delete($floor, 'Piso', 'admin.floors.index');
    }

    public function toggle(Floor $floor, LocationEntityActions $actions): RedirectResponse
    {
        return $actions->toggle($floor, 'Piso');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Floor $floor = null): array
    {
        return $request->validate([
            'building_id' => ['required', 'integer', Rule::exists('buildings', 'id')->whereNull('deleted_at')],

            // Rango con negativos: los sotanos existen y no merecen un caso
            // especial mas adelante.
            'number' => [
                'required', 'integer', 'min:-5', 'max:99',
                Rule::unique('floors', 'number')
                    ->where('building_id', $request->integer('building_id'))
                    ->ignore($floor?->id)
                    ->whereNull('deleted_at'),
            ],

            // Se guarda porque no todo piso se nombra con su numero
            // ("Semisótano", "Mezanine").
            'label' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'building_id.required' => 'Selecciona el pabellón.',
            'building_id.exists' => 'El pabellón seleccionado no existe.',
            'number.unique' => 'Ese pabellón ya tiene un piso con ese número.',
            'label.required' => 'La etiqueta del piso es obligatoria.',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
