<?php

declare(strict_types=1);

namespace App\Modules\Locations\Services;

/**
 * Lo que ocurrio al cargar el checklist.
 *
 * Cuenta tambien los equipos AUSENTES, y esa es la parte que de verdad
 * importa revisar antes de confirmar: una celda vacia de mas en el checklist
 * se traduce en un boton que el docente no vera, y eso no da ningun error —
 * simplemente el sistema deja de ofrecer algo que si existia. Verlo aqui,
 * antes de escribir, es la unica oportunidad de detectarlo.
 */
final class ChecklistReport
{
    private int $roomsCreated = 0;

    private int $roomsUpdated = 0;

    /** @var array<string, int> */
    private array $equipment = [];

    /** @var array<string, list<string>> */
    private array $missing = [];

    /** @var list<string> */
    private array $supportOnly = [];

    /** @var list<array{linea: int, mensaje: string}> */
    private array $errors = [];

    public function roomCreated(): void
    {
        $this->roomsCreated++;
    }

    public function roomUpdated(): void
    {
        $this->roomsUpdated++;
    }

    public function equipmentCounted(string $type): void
    {
        $this->equipment[$type] = ($this->equipment[$type] ?? 0) + 1;
    }

    public function addMissing(string $type, string $room): void
    {
        $this->missing[$type][] = $room;
    }

    public function supportOnly(string $room): void
    {
        $this->supportOnly[] = $room;
    }

    public function addError(int $line, string $message): void
    {
        $this->errors[] = ['linea' => $line, 'mensaje' => $message];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function roomsCreated(): int
    {
        return $this->roomsCreated;
    }

    public function roomsUpdated(): int
    {
        return $this->roomsUpdated;
    }

    public function roomsTotal(): int
    {
        return $this->roomsCreated + $this->roomsUpdated;
    }

    /** @return array<string, int> */
    public function equipment(): array
    {
        return $this->equipment;
    }

    /** @return array<string, list<string>> */
    public function missing(): array
    {
        return $this->missing;
    }

    /** @return list<string> */
    public function supportOnlyRooms(): array
    {
        return $this->supportOnly;
    }

    /** @return list<array{linea: int, mensaje: string}> */
    public function errors(): array
    {
        return $this->errors;
    }
}
