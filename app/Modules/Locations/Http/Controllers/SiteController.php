<?php

declare(strict_types=1);

namespace App\Modules\Locations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Locations\Models\Site;
use App\Modules\Locations\Services\LocationDependencyGuard;
use App\Modules\Locations\Services\LocationEntityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(LocationDependencyGuard $guard): View
    {
        $sites = Site::query()
            ->withCount('buildings')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.sites.index', compact('sites', 'guard'));
    }

    public function create(): View
    {
        return view('admin.sites.form', ['site' => new Site]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);

        $site = Site::create($data + ['is_demo' => false]);
        $audit->record('locations.create', $site, $data);

        return redirect()->route('admin.sites.index')->with('status', 'Sede creada.');
    }

    public function edit(Site $site): View
    {
        return view('admin.sites.form', compact('site'));
    }

    public function update(Request $request, Site $site, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $site);

        $audit->record('locations.update', $site, ['from' => $site->only(array_keys($data)), 'to' => $data]);
        $site->update($data);

        return redirect()->route('admin.sites.index')->with('status', 'Sede actualizada.');
    }

    public function destroy(Site $site, LocationEntityActions $actions): RedirectResponse
    {
        return $actions->delete($site, 'Sede', 'admin.sites.index');
    }

    public function toggle(Site $site, LocationEntityActions $actions): RedirectResponse
    {
        return $actions->toggle($site, 'Sede');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                // El codigo es lo que compara la logica de negocio: si se
                // duplicara, dos sedes distintas serian indistinguibles.
                Rule::unique('sites', 'code')->ignore($site?->id)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'El código es obligatorio.',
            'code.unique' => 'Ya existe una sede con ese código.',
            'name.required' => 'El nombre es obligatorio.',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
