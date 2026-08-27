<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Models;

use App\Shared\Enums\IncidentPriority as PriorityCode;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $level
 * @property string|null $color
 */
class IncidentPriority extends Model
{
    protected $fillable = ['code', 'name', 'level', 'sla_response_minutes', 'sla_resolution_minutes', 'color', 'is_active'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'is_active' => 'boolean'];
    }

    public function enum(): PriorityCode
    {
        return PriorityCode::from($this->code);
    }

    /**
     * Id de la prioridad a partir de su codigo. Falla con un mensaje claro
     * si el catalogo no la tiene, en lugar de devolver 0 en silencio.
     *
     * @throws \RuntimeException
     */
    public static function idFor(PriorityCode $code): int
    {
        $id = static::query()->where('code', $code->value)->value('id');

        if ($id === null) {
            throw new \RuntimeException(
                "El catálogo de prioridades no contiene '{$code->value}'. ".
                'Ejecuta el CatalogSeeder: sin catálogos, el sistema no puede operar.'
            );
        }

        return (int) $id;
    }
}
