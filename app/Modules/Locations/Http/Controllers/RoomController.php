<?php

declare(strict_types=1);

namespace App\Modules\Locations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Services\LocationDependencyGuard;
use App\Modules\Locations\Services\LocationEntityActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request, LocationDependencyGuard $guard): View
    {
        $search = trim((string) $request->query('q', ''));

        $rooms = Room::query()
            ->with('floor.building.site')
            ->withCount('equipment')
            ->when($request->integer('floor_id'), fn ($q, $id) => $q->where('floor_id', $id))
            ->when($search !== '', fn ($q) => $q->where('code', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('admin.rooms.index', [
            'rooms' => $rooms,
            'floors' => Floor::with('building')->orderBy('building_id')->orderBy('number')->get(),
            'guard' => $guard,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.rooms.form', [
            'room' => new Room,
            'floors' => Floor::active()->with('building.site')->orderBy('building_id')->orderBy('number')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);

        $room = Room::create($data + ['is_demo' => false]);
        $audit->record('locations.create', $room, $data);

        return redirect()->route('admin.rooms.index')->with('status', 'Aula creada.');
    }

    public function edit(Room $room): View
    {
        return view('admin.rooms.form', [
            'room' => $room,
            'floors' => Floor::active()->with('building.site')->orderBy('building_id')->orderBy('number')->get(),
        ]);
    }

    public function update(Request $request, Room $room, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $room);

        $audit->record('locations.update', $room, ['from' => $room->only(array_keys($data)), 'to' => $data]);
        $room->update($data);

        return redirect()->route('admin.rooms.index')->with('status', 'Aula actualizada.');
    }

    public function destroy(Room $room, LocationEntityActions $actions): RedirectResponse
    {
        return $actions->delete($room, 'Aula', 'admin.rooms.index');
    }

    public function toggle(Room $room, LocationEntityActions $actions): RedirectResponse
    {
        return $actions->toggle($room, 'Aula');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Room $room = null): array
    {
        return $request->validate([
            'floor_id' => ['required', 'integer', Rule::exists('floors', 'id')->whereNull('deleted_at')],

            // El codigo se ESCRIBE, no se deriva. Es lo que permite que
            // "LAB-2" conviva con "C305" en el mismo piso sin casos
            // especiales (plan 11.3).
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('rooms', 'code')
                    ->where('floor_id', $request->integer('floor_id'))
                    ->ignore($room?->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['nullable', 'string', 'max:150'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:999'],

            // Alimenta el calculo de prioridad y el modelo de riesgo.
            'criticality' => ['required', 'integer', 'min:1', 'max:3'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'floor_id.required' => 'Selecciona el piso.',
            'floor_id.exists' => 'El piso seleccionado no existe.',
            'code.required' => 'El código del aula es obligatorio.',
            'code.unique' => 'Ese piso ya tiene un aula con ese código.',
            'criticality.required' => 'Indica la criticidad del aula.',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
