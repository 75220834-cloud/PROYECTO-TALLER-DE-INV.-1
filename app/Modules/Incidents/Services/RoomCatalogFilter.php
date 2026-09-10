<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Services;

use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Locations\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Que problemas se le ofrecen al docente de UN aula concreta.
 *
 * POR QUE NO SE LE MUESTRAN SIEMPRE LOS MISMOS
 *
 * El checklist de soporte registra que equipos tiene cada aula. Si en C201-A
 * no hay parlante, ofrecerle el boton "No se escucha el audio" tiene dos
 * consecuencias malas y ninguna buena: el docente pierde el tiempo en un
 * diagnostico sobre un equipo que no existe, y si acaba escalando, la
 * investigacion se queda con una incidencia registrada contra un parlante
 * inexistente. Ese es un dato falso, y un dato falso vale menos que ninguno.
 *
 * LO QUE UNA CELDA VACIA SIGNIFICA DE VERDAD
 *
 * El checklist marca "OK" lo que existe Y soporte atiende. Una celda vacia
 * puede querer decir que no hay equipo (G802, G803 y G804 tienen pizarra
 * corrediza en lugar de ecran) o que lo hay pero es de un tipo que solo
 * soporte toca (los 30 parlantes del pabellon C y del D). Para el docente el
 * resultado es el mismo en los dos casos: no es algo que pueda intentar
 * resolver solo.
 *
 * "OTRO PROBLEMA" SIEMPRE ESTA
 *
 * Es la unica categoria que no depende del inventario, y por eso mismo es la
 * mas importante de la lista: sin ella, el docente cuyo problema no encaja
 * en ningun boton se queda sin canal y vuelve al camino informal — que es
 * exactamente lo que el piloto quiere medir que desaparece.
 */
final class RoomCatalogFilter
{
    /** Categoria que no corresponde a ningun equipo y siempre se ofrece. */
    public const FALLBACK = 'OTHER';

    /**
     * Categorias que se le muestran al docente en esta aula.
     *
     * @return Collection<int, IncidentCategory>
     */
    public function categoriesFor(?Room $room): Collection
    {
        $all = IncidentCategory::active()->orderBy('sort_order')->get();

        if ($room === null) {
            return $all;
        }

        $present = $this->categoryIdsWithEquipment($room);

        // Un aula sin NINGUN equipo registrado no significa "no tiene nada":
        // casi siempre significa que el inventario aun no llego. Ocultarle
        // todos los botones al docente lo dejaria mirando una pantalla vacia
        // sin saber que hacer, asi que en ese caso se muestran todos.
        if ($present->isEmpty()) {
            return $all;
        }

        return $all
            ->filter(fn (IncidentCategory $c): bool => $c->code === self::FALLBACK || $present->contains($c->id))
            ->values();
    }

    /**
     * Categorias correspondientes a los equipos registrados en el aula.
     *
     * @return Collection<int, int>
     */
    private function categoryIdsWithEquipment(Room $room): Collection
    {
        return DB::table('equipment')
            ->join('equipment_types as t', 't.id', '=', 'equipment.equipment_type_id')
            ->where('equipment.room_id', $room->id)
            ->where('equipment.is_active', true)
            ->whereNull('equipment.deleted_at')
            ->whereNotNull('t.default_category_id')
            ->distinct()
            ->pluck('t.default_category_id')
            ->map(static fn ($id): int => (int) $id);
    }
}
