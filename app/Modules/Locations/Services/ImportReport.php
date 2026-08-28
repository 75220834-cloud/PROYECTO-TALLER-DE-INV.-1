<?php

declare(strict_types=1);

namespace App\Modules\Locations\Services;

/**
 * Resultado de una importacion: que se creo, que se actualizo y que fallo.
 *
 * Los errores llevan SIEMPRE el numero de linea. Quien corrige el Excel
 * necesita saber cual fila esta mal, no cuantas: un informe que dice
 * "3 errores" obliga a revisar el archivo entero a mano.
 */
final class ImportReport
{
    /** @var list<string> */
    public array $createdCodes = [];

    /** @var list<string> */
    public array $updatedCodes = [];

    /** @var list<array{line: int, message: string}> */
    public array $errors = [];

    public function created(string $code): void
    {
        $this->createdCodes[] = $code;
    }

    public function updated(string $code): void
    {
        $this->updatedCodes[] = $code;
    }

    public function error(int $line, string $message): void
    {
        $this->errors[] = ['line' => $line, 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function total(): int
    {
        return count($this->createdCodes) + count($this->updatedCodes);
    }
}
