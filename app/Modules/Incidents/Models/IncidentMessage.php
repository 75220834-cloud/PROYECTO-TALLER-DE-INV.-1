<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $incident_id
 * @property string $author_type
 * @property string $body
 * @property bool $is_internal
 */
class IncidentMessage extends Model
{
    protected $fillable = ['incident_id', 'author_type', 'author_id', 'body', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }
}
