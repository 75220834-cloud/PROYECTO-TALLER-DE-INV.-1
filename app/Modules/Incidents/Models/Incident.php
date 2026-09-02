<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Models;

use App\Models\User;
use App\Modules\Equipment\Models\Equipment;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Locations\Models\Room;
use App\Shared\Enums\IncidentStatus as StatusCode;
use App\Shared\Enums\ResolutionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Incidencia tecnologica en un aula.
 *
 * Nace como BORRADOR en cuanto el docente confirma la ubicacion, no cuando
 * escala (plan 7.1). Esto es decision de investigacion, no de comodidad:
 * permite medir cuantos docentes entran y abandonan, y medir el tiempo
 * real desde que empieza el reporte. Si el reloj arrancara al crear el
 * ticket, el indicador de tiempo quedaria sesgado a favor del sistema y la
 * comparacion contra el proceso tradicional no serviria de nada.
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $ticket_number
 * @property int $room_id
 * @property int|null $equipment_id
 * @property int|null $category_id
 * @property int $status_id
 * @property int|null $priority_id
 * @property string|null $reported_description
 * @property bool $blocks_class
 * @property string|null $reporter_photo_path
 * @property bool $hazard_reported
 * @property string|null $hazard_term
 * @property bool $is_draft
 * @property int|null $assigned_to
 * @property string|null $resolution_type
 * @property string|null $device_key
 * @property string|null $ip_hash
 * @property int $reopened_count
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $reported_at
 * @property Carbon|null $first_response_at
 * @property Carbon|null $assigned_at
 * @property Carbon|null $arrived_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property-read Room|null $room
 * @property-read IncidentCategory|null $category
 * @property-read IncidentStatus|null $status
 * @property-read IncidentPriority|null $priority
 */
class Incident extends Model
{
    protected $fillable = [
        'uuid', 'ticket_number', 'room_id', 'equipment_id', 'category_id',
        'status_id', 'priority_id', 'reported_description', 'classified_by',
        'classification_confidence', 'blocks_class', 'hazard_reported',
        'hazard_term', 'assigned_to',
        'resolution_type', 'resolution_notes', 'technical_diagnosis',
        'reporter_photo_path',
        'reporter_hint', 'device_key', 'ip_hash', 'is_draft', 'merged_into_id',
        'reopened_count', 'confirmed_at', 'reported_at', 'first_response_at',
        'assigned_at', 'arrived_at', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'blocks_class' => 'boolean',
            'hazard_reported' => 'boolean',
            'is_draft' => 'boolean',
            'classification_confidence' => 'float',
            'reopened_count' => 'integer',
            'confirmed_at' => 'datetime',
            'reported_at' => 'datetime',
            'first_response_at' => 'datetime',
            'assigned_at' => 'datetime',
            'arrived_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $incident) {
            $incident->uuid ??= (string) Str::uuid();
        });
    }

    // ------------------------------------------------------- relaciones

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncidentCategory::class, 'category_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(IncidentStatus::class, 'status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(IncidentPriority::class, 'priority_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(IncidentEvent::class)->orderBy('occurred_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(IncidentMessage::class)->orderBy('created_at');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function satisfaction(): HasOne
    {
        return $this->hasOne(SatisfactionResponse::class);
    }

    /**
     * Artículo de conocimiento redactado a partir de esta incidencia
     * (plan CU-S-16). Sirve para no duplicarlo y para poder rastrear de
     * dónde salió lo que el asistente le afirma a un docente.
     *
     * @return HasOne<KnowledgeDocument, $this>
     */
    public function knowledgeArticle(): HasOne
    {
        return $this->hasOne(KnowledgeDocument::class, 'source_incident_id');
    }

    // ------------------------------------------------------------ estado

    public function statusCode(): StatusCode
    {
        return StatusCode::from($this->status->code);
    }

    /**
     * Se llama hasStatus() y no is(): Eloquent ya define Model::is() para
     * comparar dos modelos entre si, y sobrescribirlo con otra firma rompe
     * ese contrato ademas de confundir a quien lea el codigo.
     */
    public function hasStatus(StatusCode $code): bool
    {
        return $this->statusCode() === $code;
    }

    public function resolutionType(): ?ResolutionType
    {
        return $this->resolution_type !== null
            ? ResolutionType::from($this->resolution_type)
            : null;
    }

    // ------------------------------------------------------------ ambitos

    /** Visible en la bandeja de soporte: excluye borradores y diagnostico. */
    public function scopeVisibleToSupport(Builder $query): Builder
    {
        return $query->where('is_draft', false);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('is_open', true));
    }

    // ------------------------------------------------------------ métricas

    /**
     * Minutos desde que el docente confirmo la ubicacion hasta la
     * resolucion. Se mide desde confirmed_at y no desde la creacion del
     * ticket a proposito (ver la nota de clase).
     */
    public function minutesToResolution(): ?int
    {
        if ($this->resolved_at === null || $this->confirmed_at === null) {
            return null;
        }

        return (int) $this->confirmed_at->diffInMinutes($this->resolved_at);
    }

    public function minutesToFirstResponse(): ?int
    {
        if ($this->first_response_at === null || $this->reported_at === null) {
            return null;
        }

        return (int) $this->reported_at->diffInMinutes($this->first_response_at);
    }
}
