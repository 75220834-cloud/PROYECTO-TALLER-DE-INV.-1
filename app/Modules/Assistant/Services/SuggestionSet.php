<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Services;

use App\Modules\Incidents\Models\Incident;
use App\Modules\Retrieval\Services\RetrievedChunk;
use Illuminate\Support\Collection;

/** Lo que la IA sugiere para una incidencia. */
final readonly class SuggestionSet
{
    /**
     * @param  Collection<int, array{incident: Incident, score: float, sameRoom: bool}>  $cases
     * @param  Collection<int, RetrievedChunk>  $guides
     * @param  bool  $semantic  si el parecido se midió por significado (con la IA) o por palabras
     */
    public function __construct(
        public Collection $cases,
        public Collection $guides,
        public bool $semantic,
    ) {}
}
