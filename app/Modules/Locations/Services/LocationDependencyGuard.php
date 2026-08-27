<?php

declare(strict_types=1);

namespace App\Modules\Locations\Services;

use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Guarda de dependencias del catalogo de ubicaciones (plan 39).
 *
 * Distingue dos operaciones que se suelen confundir:
 *
 *   DESACTIVAR  siempre se permite. Es reversible y no pierde nada: la
 *               ubicacion deja de ofrecerse al docente y CascadeValidator
 *               la rechaza, pero el historico sigue intacto y se puede
 *               volver a activar. Es la operacion que soporte usara a
 *               diario ("este pabellon esta en obras").
 *
 *   ELIMINAR    solo si NO cuelga nada de ella. Un aula con incidencias
 *               registradas no se borra jamas: esas incidencias son datos
 *               de la investigacion y perder su ubicacion las volveria
 *               inutilizables para el analisis por aula y para el modelo
 *               de riesgo.
 *
 * Las claves foraneas con RESTRICT ya impedirian el borrado a nivel de base,
 * pero fallarian con un error de motor incomprensible. Esta clase existe
 * para que el administrador vea ANTES por que no puede borrar y que tendria
 * que hacer primero.
 */
final class LocationDependencyGuard
{
    /**
     * Dependencias que impiden eliminar el elemento.
     *
     * @return array<string, int> etiqueta legible => cantidad
     */
    public function blockers(Model $model): array
    {
        return match (true) {
            $model instanceof Site => $this->siteBlockers($model),
            $model instanceof Building => $this->buildingBlockers($model),
            $model instanceof Floor => $this->floorBlockers($model),
            $model instanceof Room => $this->roomBlockers($model),
            default => [],
        };
    }

    public function canDelete(Model $model): bool
    {
        return $this->blockers($model) === [];
    }

    /**
     * Explicacion en lenguaje llano de por que no se puede eliminar.
     */
    public function explain(Model $model): string
    {
        $blockers = $this->blockers($model);

        if ($blockers === []) {
            return '';
        }

        $parts = [];
        foreach ($blockers as $label => $count) {
            $parts[] = "{$count} {$label}";
        }

        return 'No se puede eliminar porque tiene '.implode(', ', $parts).
            '. Desactívalo en lugar de eliminarlo: se deja de usar sin perder el historial.';
    }

    /** @return array<string, int> */
    private function siteBlockers(Site $site): array
    {
        $count = Building::withTrashed()->where('site_id', $site->id)->count();

        return $count > 0 ? ['pabellones asociados' => $count] : [];
    }

    /** @return array<string, int> */
    private function buildingBlockers(Building $building): array
    {
        $count = Floor::withTrashed()->where('building_id', $building->id)->count();

        return $count > 0 ? ['pisos asociados' => $count] : [];
    }

    /** @return array<string, int> */
    private function floorBlockers(Floor $floor): array
    {
        $count = Room::withTrashed()->where('floor_id', $floor->id)->count();

        return $count > 0 ? ['aulas asociadas' => $count] : [];
    }

    /** @return array<string, int> */
    private function roomBlockers(Room $room): array
    {
        $blockers = [];

        $equipment = DB::table('equipment')->where('room_id', $room->id)->count();
        if ($equipment > 0) {
            $blockers['equipos registrados'] = $equipment;
        }

        // Aunque no queden equipos, el historial de incidencias basta para
        // bloquear el borrado: es dato de la investigacion.
        $incidents = DB::table('incidents')->where('room_id', $room->id)->count();
        if ($incidents > 0) {
            $blockers['incidencias en el historial'] = $incidents;
        }

        return $blockers;
    }

    /**
     * Aviso al desactivar: no bloquea, pero el administrador debe saber que
     * arrastra consigo. Desactivar un pabellon deja fuera de servicio todas
     * sus aulas sin tocar ni una sola fila de aulas, y esa consecuencia no
     * se ve en la pantalla del pabellon.
     */
    public function deactivationWarning(Model $model): ?string
    {
        $affected = match (true) {
            $model instanceof Site => Room::whereHas('floor.building', fn ($q) => $q->where('site_id', $model->id))->where('is_active', true)->count(),
            $model instanceof Building => Room::whereHas('floor', fn ($q) => $q->where('building_id', $model->id))->where('is_active', true)->count(),
            $model instanceof Floor => Room::where('floor_id', $model->id)->where('is_active', true)->count(),
            default => 0,
        };

        if ($affected === 0) {
            return null;
        }

        return "Al desactivarlo, {$affected} aula(s) dejarán de aceptar reportes hasta que se vuelva a activar.";
    }
}
