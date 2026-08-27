<?php

declare(strict_types=1);

namespace App\Modules\Locations\Services;

use App\Modules\Locations\Models\Room;
use App\Shared\Exceptions\InvalidLocationException;

/**
 * Validacion de la cadena sede -> pabellon -> piso -> aula EN SERVIDOR.
 *
 * Este es el punto donde el requisito "el sistema debe impedir seleccionar
 * combinaciones inexistentes" se cumple de verdad. La interfaz tambien
 * limita las opciones, pero eso es comodidad para el docente, no seguridad:
 * cualquiera puede enviar una peticion HTTP a mano con un room_id que no
 * corresponda al floor_id. Por eso aqui NO se confia en nada de lo que
 * llega del cliente y se vuelve a comprobar la cadena entera contra el
 * catalogo.
 *
 * Que se comprueba, en este orden:
 *   1. El aula existe.
 *   2. El aula pertenece al piso declarado.
 *   3. El piso pertenece al pabellon declarado.
 *   4. El pabellon pertenece a la sede declarada.
 *   5. Ningun eslabon de la cadena esta desactivado.
 *
 * El paso 5 importa mas de lo que parece: un aula puede seguir existiendo
 * pero estar fuera de servicio, o su pabellon entero puede estarlo. Aceptar
 * un reporte ahi generaria un ticket que nadie deberia atender.
 */
final class CascadeValidator
{
    /**
     * Resuelve y valida la seleccion completa.
     *
     * @throws InvalidLocationException si cualquier eslabon no encaja.
     */
    public function validate(int $siteId, int $buildingId, int $floorId, int $roomId): Room
    {
        $room = Room::query()
            ->with(['floor.building.site'])
            ->find($roomId);

        if ($room === null) {
            throw InvalidLocationException::roomNotFound($roomId);
        }

        $floor = $room->floor;
        $building = $floor?->building;
        $site = $building?->site;

        // Si falta cualquier eslabon, el catalogo esta inconsistente. Las FK
        // lo impiden, pero un soft-delete de un padre si puede producirlo.
        if ($floor === null || $building === null || $site === null) {
            throw InvalidLocationException::brokenChain($roomId);
        }

        if ($floor->id !== $floorId) {
            throw InvalidLocationException::mismatch('El aula no pertenece al piso indicado.');
        }

        if ($building->id !== $buildingId) {
            throw InvalidLocationException::mismatch('El piso no pertenece al pabellon indicado.');
        }

        if ($site->id !== $siteId) {
            throw InvalidLocationException::mismatch('El pabellon no pertenece a la sede indicada.');
        }

        $this->assertActive($site->is_active, 'La sede no esta activa.');
        $this->assertActive($building->is_active, 'El pabellon no esta activo.');
        $this->assertActive($floor->is_active, 'El piso no esta activo.');
        $this->assertActive($room->is_active, 'Esta aula no esta disponible para reportes.');

        return $room;
    }

    /**
     * Valida a partir del aula sola, deduciendo el resto de la cadena.
     *
     * Se usa en la busqueda directa por codigo ("C305"), donde el docente
     * nunca eligio pabellon ni piso: no hay nada que contrastar, pero si
     * hay que comprobar que toda la cadena este activa.
     *
     * @throws InvalidLocationException
     */
    public function validateRoomOnly(int $roomId): Room
    {
        $room = Room::query()->with(['floor.building.site'])->find($roomId);

        if ($room === null) {
            throw InvalidLocationException::roomNotFound($roomId);
        }

        $floor = $room->floor;
        $building = $floor?->building;
        $site = $building?->site;

        if ($floor === null || $building === null || $site === null) {
            throw InvalidLocationException::brokenChain($roomId);
        }

        return $this->validate($site->id, $building->id, $floor->id, $room->id);
    }

    private function assertActive(bool $isActive, string $message): void
    {
        if (! $isActive) {
            throw InvalidLocationException::inactive($message);
        }
    }
}
