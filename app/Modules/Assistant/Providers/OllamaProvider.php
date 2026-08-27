<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Providers;

use App\Modules\Assistant\Contracts\Classification;
use App\Modules\Assistant\Contracts\LlmProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Modelo de lenguaje local vía Ollama (plan 10.5).
 *
 * Todo ocurre dentro de la red institucional: ningun dato sale a Internet.
 *
 * Dos decisiones que conviene tener presentes:
 *
 *  - `temperature = 0` al clasificar. No se busca variedad: se busca que
 *    el mismo sintoma produzca siempre la misma categoria, porque de eso
 *    depende que el recorrido sea reproducible y la investigacion pueda
 *    atribuir resultados a la intervencion.
 *
 *  - CUALQUIER fallo devuelve "desconocido" en lugar de propagarse. Si
 *    Ollama esta caido, tarda demasiado o responde algo que no se entiende,
 *    el sistema cae al modo determinista y el docente no se entera. La IA
 *    es una comodidad, no una dependencia (plan 13.1).
 */
final class OllamaProvider implements LlmProvider
{
    public function classify(string $text, array $allowedLabels): Classification
    {
        if ($allowedLabels === []) {
            return Classification::unknown();
        }

        $labels = implode(', ', $allowedLabels);

        $prompt = <<<PROMPT
        Eres un clasificador de incidencias tecnológicas en aulas.

        Clasifica el siguiente reporte en UNA de estas categorías exactas:
        {$labels}

        Reglas:
        - Responde SOLO con un JSON: {"label":"CATEGORIA","confidence":0.0}
        - "label" debe ser exactamente una de las categorías listadas.
        - Si el texto es ambiguo o no corresponde a ninguna, usa "label": null.
        - "confidence" es un número entre 0 y 1.
        - No expliques nada. No inventes categorías.

        Reporte del docente:
        """
        {$text}
        """
        PROMPT;

        try {
            $response = Http::timeout((int) config('incidencias.llm.timeout'))
                ->post(rtrim((string) config('incidencias.llm.base_url'), '/').'/api/generate', [
                    'model' => config('incidencias.llm.model'),
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                    'options' => ['temperature' => 0],
                ]);

            if (! $response->successful()) {
                return Classification::unknown();
            }

            $payload = json_decode((string) ($response->json('response') ?? ''), true);

            if (! is_array($payload)) {
                return Classification::unknown();
            }

            $label = $payload['label'] ?? null;

            // Validacion dura contra el catalogo: si el modelo devuelve algo
            // que no esta en la lista, se descarta. Nunca puede introducir
            // una categoria inexistente (plan 13.4).
            if (! is_string($label) || ! in_array($label, $allowedLabels, true)) {
                return Classification::unknown();
            }

            $confidence = (float) ($payload['confidence'] ?? 0.0);

            return new Classification(
                label: $label,
                confidence: max(0.0, min(1.0, $confidence)),
                method: 'ollama',
            );
        } catch (Throwable $e) {
            Log::warning('Ollama no disponible al clasificar', ['error' => $e->getMessage()]);

            return Classification::unknown();
        }
    }

    public function rephrase(string $text, string $context = ''): ?string
    {
        try {
            $prompt = <<<PROMPT
            Reescribe la siguiente instrucción para un docente que no tiene
            conocimientos técnicos.

            Reglas estrictas:
            - NO añadas pasos, datos ni consejos que no estén en el original.
            - NO cambies el significado.
            - Máximo 2 frases cortas, en español, tuteando.
            - Responde SOLO con la instrucción reescrita.

            Contexto: {$context}

            Instrucción original:
            """
            {$text}
            """
            PROMPT;

            $response = Http::timeout((int) config('incidencias.llm.timeout'))
                ->post(rtrim((string) config('incidencias.llm.base_url'), '/').'/api/generate', [
                    'model' => config('incidencias.llm.model'),
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => ['temperature' => 0.3],
                ]);

            $result = trim((string) $response->json('response', ''));

            // Una reformulacion vacia o desproporcionadamente larga suele
            // significar que el modelo se invento contenido. Se descarta y
            // se usa el texto original, que siempre es valido.
            if ($result === '' || mb_strlen($result) > mb_strlen($text) * 3) {
                return null;
            }

            return $result;
        } catch (Throwable) {
            return null;
        }
    }

    public function isAvailable(): bool
    {
        try {
            // Sondeo corto: si Ollama no contesta rapido, para el caso de uso
            // da igual que este "arrancando". Se considera no disponible.
            return Http::timeout(3)
                ->get(rtrim((string) config('incidencias.llm.base_url'), '/').'/api/tags')
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function identifier(): string
    {
        return 'ollama:'.config('incidencias.llm.model');
    }
}
