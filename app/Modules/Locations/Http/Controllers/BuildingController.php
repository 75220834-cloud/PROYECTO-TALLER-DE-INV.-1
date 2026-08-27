<?php

declare(strict_types=1);

namespace App\Modules\Locations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Site;
use App\Modules\Locations\Services\LocationDependencyGuard;
use App\Modules\Locations\Services\LocationEntityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BuildingController extends Controller
{
    public function index(Request $request, LocationDependencyGuard $guard): View
    {
        $buildings = Building::query()
            ->with('site')
            ->withCount('floors')
            ->when($request->integer('site_id'), fn ($q, $id) => $q->where('site_id', $id))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('admin.buildings.index', [
            'buildings' => $buildings,
            'sites' => Site::orderBy('name')->get(),
            'guard' => $guard,
        ]);
    }

    public function create(): View
    {
        return view('admin.buildings.form', [
            'building' => new Building,
            'sites' => Site::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);

        $building = Building::create($data + ['is_demo' => false]);
        $audit->record('locations.create', $building, $data);

        return redirect()->route('admin.buildings.index')->with('status', 'Pabellón creado.');
    }

    public function edit(Building $building): View
    {
        return view('admin.buildings.form', [
            'building' => $building,
            'sites' => Site::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Building $building, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $building);

        $audit->record('locations.update', $building, ['from' => $building->only(array_keys($data)), 'to' => $data]);
        $building->update($data);

        return redirect()->route('admin.buildings.index')->with('status', 'Pabellón actualizado.');
    }

    public function destroy(Building $building, LocationEntityActions $actions): RedirectResponse
    {
        return $actions->delete($building, 'Pabellón', 'admin.buildings.index');
    }

    public function toggle(Building $building, LocationEntityActions $actions): RedirectResponse
    {
        return $actions->toggle($building, 'Pabellón');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Building $building = null): array
    {
        return $request->validate([
            // exists garantiza que la sede exista de verdad: sin esto se
            // podria enviar un site_id inventado y la FK fallaria con un
            // error de motor en lugar de un mensaje util.
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')->whereNull('deleted_at')],
            'code' => [
                'required', 'string', 'max:50',
                // Unico DENTRO de la sede, no globalmente: dos sedes pueden
                // tener cada una su pabellon "A".
                Rule::unique('buildings', 'code')
                    ->where('site_id', $request->integer('site_id'))
                    ->ignore($building?->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'site_id.required' => 'Selecciona la sede.',
            'site_id.exists' => 'La sede seleccionada no existe.',
            'code.unique' => 'Ya existe un pabellón con ese código en esa sede.',
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $request->integer('sort_order'),
        ];
    }
}
