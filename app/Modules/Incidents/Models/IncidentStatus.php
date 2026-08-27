<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Models;

use App\Shared\Enums\IncidentStatus as StatusCode;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalogo de estados. El `code` es lo que compara la logica; el `name` es
 * editable por el administrador.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_open
 * @property bool $is_terminal
 * @property bool $is_resolved
 * @property int $sort_order
 */
class IncidentStatus extends Model
{
    protected $fillable = ['code', 'name', 'is_open', 'is_terminal', 'is_resolved', 'sort_order', 'color', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'is_terminal' => 'boolean',
            'is_resolved' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function enum(): StatusCode
    {
        return StatusCode::from($this->code);
    }

    /**
     * Id del estado a partir de su codigo.
     *
     * Falla con un mensaje claro si el catalogo no tiene ese codigo. Antes
     * devolvia (int) null = 0 en silencio, y el fallo aparecia mucho mas
     * tarde como una violacion de clave foranea ilegible. Un catalogo
     * incompleto es un problema de instalacion y merece decirse asi.
     *
     * @throws \RuntimeException
     */
    public static function idFor(StatusCode $code): int
    {
        $id = static::query()->where('code', $code->value)->value('id');

        if ($id === null) {
            throw new \RuntimeException(
                "El catálogo de estados no contiene '{$code->value}'. ".
                'Ejecuta el CatalogSeeder: sin catálogos, el sistema no puede operar.'
            );
        }

        return (int) $id;
    }
}
