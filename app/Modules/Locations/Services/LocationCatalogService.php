<?php

declare(strict_types=1);

namespace App\Modules\Locations\Services;

use App\Modules\Locations\Models\Building;
use App\Modules\Locations\Models\Floor;
use App\Modules\Locations\Models\Room;
use App\Modules\Locations\Models\Site;
use Illuminate\Support\Collection;

/**
 * Catalogo de ubicaciones para la seleccion en cascada del docente.
 *
 * Todas las consultas devuelven SOLO elementos activos y SOLO los que
 * cuelgan del padre elegido. Es lo que hace imposible que la interfaz
 * llegue a ofrecer una combinacion inexistente; CascadeValidator se ocupa
 * despues de que tampoco se pueda forzar por HTTP.
 *
 * Aqui vive tambien la optimizacion de UX mas rentable del sistema: si un
 * nivel tiene una sola opcion, esa pantalla no se muestra. Con una sola
 * sede en el piloto, el docente se ahorra un toque sin que nadie tenga que
 * programar un caso especial. Cuando haya mas sedes, la pantalla aparece
 * sola. No hay nada que recordar cambiar.
 */
final class LocationCatalogService
{
    /** @return Collection<int, Site> */
    public function sites(): Collection
    {
        return Site::query()->active()->orderBy('name')->get();
    }

    /** @return Collection<int, Building> */
    public function buildingsOf(int $siteId): Collection
    {
        return Building::query()
            ->active()
            ->where('site_id', $siteId)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    /** @return Collection<int, Floor> */
    public function floorsOf(int $buildingId): Collection
    {
        return Floor::query()
            ->active()
            ->where('building_id', $buildingId)
            ->orderBy('number')
            ->get();
    }

    /** @return Collection<int, Room> */
    public function roomsOf(int $floorId): Collection
    {
        return Room::query()
            ->active()
            ->where('floor_id', $floorId)
            ->orderBy('code')
            ->get();
    }

    /**
     * Si solo hay una opcion, no hay nada que preguntar.
     *
     * Devuelve el unico elemento cuando la coleccion tiene exactamente uno,
     * o null cuando hay cero o varios. El controlador lo usa para saltarse
     * la pantalla entera.
     */
    /**
     * Todos los pabellones activos, con su sede.
     *
     * Para la pantalla unica: se envian todos y el navegador filtra. Ver el
     * porque en TeacherLocationController::start().
     *
     * @return Collection<int, Building>
     */
    public function allBuildings(): Collection
    {
        return Building::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
    }

    /**
     * Todos los pisos activos, con su pabellon.
     *
     * @return Collection<int, Floor>
     */
    public function allFloors(): Collection
    {
        return Floor::query()
            ->active()
            ->orderBy('number')
            ->get();
    }

    /**
     * Todas las aulas activas, con su piso.
     *
     * @return Collection<int, Room>
     */
    public function allRooms(): Collection
    {
        return Room::query()
            ->active()
            ->orderBy('code')
            ->get();
    }

    public function autoSelect(Collection $options): ?object
    {
        return $options->count() === 1 ? $options->first() : null;
    }

    /**
     * Busqueda directa por codigo de aula: la via alternativa a la cascada.
     *
     * Deliberadamente conservadora en lo que revela. Con una coincidencia
     * exacta devuelve el aula; si no, ofrece unas pocas sugerencias por
     * prefijo. No permite listar el catalogo entero con una consulta vacia
     * ni con un comodin, porque eso convertiria el buscador en una via
     * comoda para volcar todas las aulas de la universidad.
     *
     * @return Collection<int, Room>
     */
    public function searchByCode(string $term, int $limit = 8): Collection
    {
        $term = trim($term);

        // Menos de dos caracteres devolveria media universidad.
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $exact = Room::query()
            ->active()
            ->with(['floor.building.site'])
            ->where('code', $term)
            ->first();

        if ($exact !== null) {
            return collect([$exact]);
        }

        // Solo prefijo: "C30" sugiere C301..C309, pero "305" no arrastra
        // todas las aulas que contengan ese numero en cualquier pabellon.
        return Room::query()
            ->active()
            ->with(['floor.building.site'])
            ->where('code', 'like', $this->escapeLike($term).'%')
            ->orderBy('code')
            ->limit($limit)
            ->get();
    }

    /** Neutraliza los comodines de LIKE para que no se puedan inyectar. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
