<?php

declare(strict_types=1);

namespace App\Modules\Equipment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Equipment\Models\EquipmentType;
use App\Modules\Locations\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Inventario de equipos (plan 40).
 *
 * Los equipos NO se eliminan: se marcan fuera de servicio. Un equipo con
 * incidencias en el historial es la unidad de analisis del modulo de riesgo
 * ("este proyector concreto falla cada dos semanas"). Borrarlo dejaria esas
 * incidencias huerfanas y volveria inutil ese analisis.
 */
class EquipmentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $equipment = Equipment::query()
            ->with(['room.floor.building', 'type'])
            ->when($request->integer('room_id'), fn ($q, $id) => $q->where('room_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $escaped = '%'.addcslashes($search, '%_\\').'%';
                $sub->where('asset_code', 'like', $escaped)
                    ->orWhere('serial_number', 'like', $escaped);
            }))
            ->orderBy('asset_code')
            ->paginate(25)
            ->withQueryString();

        return view('admin.equipment.index', [
            'equipment' => $equipment,
            'statuses' => Equipment::statusLabels(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.equipment.form', [
            'item' => new Equipment(['status' => 'operational', 'is_active' => true]),
            'rooms' => $this->roomOptions(),
            'types' => EquipmentType::active()->orderBy('sort_order')->get(),
            'statuses' => Equipment::statusLabels(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);

        $item = Equipment::create($data + ['is_demo' => false]);
        $audit->record('equipment.create', $item, $data);

        return redirect()->route('admin.equipment.index')->with('status', 'Equipo registrado.');
    }

    public function edit(Equipment $equipment): View
    {
        return view('admin.equipment.form', [
            'item' => $equipment,
            'rooms' => $this->roomOptions(),
            'types' => EquipmentType::active()->orderBy('sort_order')->get(),
            'statuses' => Equipment::statusLabels(),
        ]);
    }

    public function update(Request $request, Equipment $equipment, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $equipment);

        // El traslado de un equipo entre aulas se audita aparte: es lo que
        // explica que un aula deje de fallar y otra empiece a hacerlo.
        if ((int) $data['room_id'] !== $equipment->room_id) {
            $audit->record('equipment.move', $equipment, [
                'from_room_id' => $equipment->room_id,
                'to_room_id' => (int) $data['room_id'],
            ]);
        }

        $audit->record('equipment.update', $equipment, ['to' => $data]);
        $equipment->update($data);

        return redirect()->route('admin.equipment.index')->with('status', 'Equipo actualizado.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Equipment $equipment = null): array
    {
        return $request->validate([
            // Solo aulas activas: registrar un equipo en un aula fuera de
            // servicio casi siempre es un error de captura.
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'equipment_type_id' => ['required', 'integer', Rule::exists('equipment_types', 'id')],
            'asset_code' => [
                'required', 'string', 'max:60',
                Rule::unique('equipment', 'asset_code')->ignore($equipment?->id)->whereNull('deleted_at'),
            ],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],

            // Sin unique: en la practica muchas series vienen vacias o mal
            // transcritas, y exigir unicidad bloquearia la carga real.
            'serial_number' => ['nullable', 'string', 'max:120'],

            'status' => ['required', Rule::in(array_keys(Equipment::statusLabels()))],
            'commissioned_at' => ['nullable', 'date', 'before_or_equal:today'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'room_id.required' => 'Selecciona el aula.',
            'room_id.exists' => 'El aula seleccionada no existe o está desactivada.',
            'equipment_type_id.required' => 'Selecciona el tipo de equipo.',
            'asset_code.required' => 'El código de inventario es obligatorio.',
            'asset_code.unique' => 'Ya existe un equipo con ese código de inventario.',
            'commissioned_at.before_or_equal' => 'La fecha de incorporación no puede ser futura.',
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    /** @return Collection<int, Room> */
    private function roomOptions()
    {
        return Room::active()->with('floor.building')->orderBy('code')->get();
    }
}
