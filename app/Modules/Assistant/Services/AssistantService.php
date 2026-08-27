<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Services;

use App\Modules\Assistant\Contracts\Classification;
use App\Modules\Assistant\Contracts\LlmProvider;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Retrieval\Services\RetrievalService;
use App\Modules\Retrieval\Services\RetrievedChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta la respuesta del asistente a una pregunta abierta (plan 14.4).
 *
 * JERARQUÍA DE FUENTES, en orden estricto:
 *   1. Árboles de diagnóstico vigentes  (los sirve DiagnosticEngine)
 *   2. Procedimientos institucionales publicados
 *   3. Base de conocimiento general
 *   4. Conocimiento general del modelo -> SOLO para redactar, NUNCA como
 *      fuente de un procedimiento
 *
 * Si los niveles 2 y 3 no aportan nada, el sistema ESCALA. No completa con
 * el nivel 4. Esa es la diferencia entre un asistente que reconoce sus
 * límites y uno que improvisa con la voz de la institución.
 *
 * Toda interacción queda registrada con su confianza, su decisión y sus
 * citas, para que la política de escalamiento sea auditable y recalibrable
 * con datos reales (plan 13.5).
 */
final class AssistantService
{
    public function __construct(
        private readonly RetrievalService $retrieval,
        private readonly ConfidenceEvaluator $confidence,
        private readonly AnswerVerifier $verifier,
        private readonly LlmProvider $llm,
        private readonly IntentClassifier $classifier,
    ) {}

    public function answer(string $question, ?int $categoryId = null, ?Incident $incident = null): AssistantAnswer
    {
        $startedAt = microtime(true);

        $classification = $this->classifier->classify($question);
        $retrieved = $this->retrieval->search($question, $categoryId ?? $incident?->category_id);

        // Sin fuentes no hay respuesta posible. Es el caso más importante de
        // toda la clase: aquí es donde el sistema podría inventarse algo y
        // donde decide no hacerlo (plan 44).
        if ($retrieved->isEmpty()) {
            $verdict = $this->confidence->evaluate($classification, $retrieved);

            return $this->record(
                AssistantAnswer::escalate($verdict),
                $question, $classification, $retrieved, $incident, $startedAt
            );
        }

        $passages = $retrieved->map(fn ($r) => $r->content())->all();
        $generated = $this->llm->answerGrounded($question, $passages);

        // VERIFICACIÓN. La defensa no se confía al prompt: si la respuesta
        // no está anclada, es insegura o promete certezas, se descarta y se
        // cae al respaldo, que son los pasajes tal cual.
        if ($generated !== null && ! $this->passesChecks($generated, $passages)) {
            Log::warning('Respuesta del modelo descartada por verificación.', [
                'question' => mb_substr($question, 0, 120),
            ]);

            $generated = null;
        }

        // Cobertura: cuántos de los pasajes recuperados quedan realmente
        // citados. Con el respaldo se citan todos, porque se muestran todos.
        $cited = $generated !== null ? $this->citedCount($generated, $retrieved) : $retrieved->count();

        $verdict = $this->confidence->evaluate($classification, $retrieved, $cited);

        if ($verdict->shouldEscalate()) {
            return $this->record(
                AssistantAnswer::escalate($verdict),
                $question, $classification, $retrieved, $incident, $startedAt
            );
        }

        $answer = $generated !== null
            ? AssistantAnswer::generated($generated, $retrieved, $verdict)
            // Sin modelo o con respuesta descartada: se muestran los pasajes
            // literales con su cita. Menos cómodo de leer, exactamente igual
            // de fiable, y sin ninguna posibilidad de invención.
            : AssistantAnswer::fromPassages($retrieved, $verdict);

        return $this->record($answer, $question, $classification, $retrieved, $incident, $startedAt);
    }

    /**
     * @param  list<string>  $passages
     */
    private function passesChecks(string $answer, array $passages): bool
    {
        return $this->verifier->isGrounded($answer, $passages)
            && $this->verifier->isSafe($answer)
            && ! $this->verifier->hasOverconfidentClaim($answer);
    }

    /**
     * @param  Collection<int, RetrievedChunk>  $retrieved
     */
    private function citedCount(string $answer, $retrieved): int
    {
        $verifier = $this->verifier;

        return $retrieved
            ->filter(fn ($r) => $verifier->isGrounded($answer, [$r->content()]))
            ->count();
    }

    /**
     * Registra la interacción y sus citas.
     *
     * @param  Collection<int, RetrievedChunk>  $retrieved
     */
    private function record(
        AssistantAnswer $answer,
        string $question,
        Classification $classification,
        $retrieved,
        ?Incident $incident,
        float $startedAt,
    ): AssistantAnswer {
        $interactionId = DB::table('assistant_interactions')->insertGetId([
            'incident_id' => $incident?->id,
            'query' => mb_substr($question, 0, 2000),
            'intent' => $classification->label,
            'response' => mb_substr($answer->text, 0, 4000),
            'confidence_score' => $answer->verdict->confidence,
            'confidence_band' => $answer->verdict->band,
            'escalated' => $answer->escalated,
            'llm_model' => $this->llm->identifier(),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Trazabilidad completa: qué fragmento sustentó la respuesta. Sin
        // esto no se puede comprobar si el asistente citó de verdad.
        $rank = 1;

        foreach ($retrieved as $item) {
            DB::table('retrieval_citations')->insert([
                'interaction_id' => $interactionId,
                'chunk_id' => $item->chunk->id,
                'rank' => $rank++,
                'score' => round($item->score, 6),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $answer;
    }
}
