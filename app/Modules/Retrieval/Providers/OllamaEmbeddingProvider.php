<?php

declare(strict_types=1);

namespace App\Modules\Retrieval\Providers;

use App\Modules\Retrieval\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Vectores generados por el modelo de embeddings local de Ollama.
 *
 * Todo ocurre dentro de la red institucional: el contenido de los
 * documentos de soporte no sale a ningún servicio externo (plan 34).
 */
final class OllamaEmbeddingProvider implements EmbeddingProvider
{
    public function embed(string $text): ?array
    {
        try {
            $response = Http::timeout((int) config('incidencias.llm.timeout'))
                ->post(rtrim((string) config('incidencias.llm.base_url'), '/').'/api/embeddings', [
                    'model' => config('incidencias.llm.embedding_model'),
                    'prompt' => $text,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $vector = $response->json('embedding');

            if (! is_array($vector) || $vector === []) {
                return null;
            }

            return array_map(static fn ($v): float => (float) $v, array_values($vector));
        } catch (Throwable $e) {
            Log::warning('No se pudo generar el vector', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function embedBatch(array $texts): array
    {
        // Ollama no expone un endpoint por lotes, así que se va uno a uno.
        // Es lento, y por eso la ingesta corre EN COLA y no en la petición
        // web: un manual de 80 páginas puede tardar varios minutos y nadie
        // debería quedarse mirando un formulario mientras tanto.
        return array_map(fn (string $text): ?array => $this->embed($text), $texts);
    }

    public function dimensions(): int
    {
        return (int) config('incidencias.llm.embedding_dimensions');
    }

    public function modelIdentifier(): string
    {
        return (string) config('incidencias.llm.embedding_model');
    }

    public function isAvailable(): bool
    {
        try {
            return Http::timeout(3)
                ->get(rtrim((string) config('incidencias.llm.base_url'), '/').'/api/tags')
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
