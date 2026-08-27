<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Pipeline;

/**
 * Fragmenta el texto extraído para poder indexarlo (plan 14.2).
 *
 * REGLA QUE MANDA SOBRE TODAS LAS DEMÁS: no partir un procedimiento
 * numerado. Un procedimiento de ocho pasos se queda entero aunque exceda el
 * tamaño objetivo.
 *
 * No es un detalle de calidad: en un dominio de instrucciones, entregar
 * "los pasos 1 al 4" como si fueran el procedimiento completo hace que el
 * docente crea que terminó cuando va por la mitad. Es la causa más común de
 * respuestas incompletas y peligrosas en sistemas como este.
 *
 * Cada fragmento conserva además el título del documento y su sección,
 * prefijados antes de generar el vector: mejora la recuperación y es lo que
 * permite citar la fuente (plan 42).
 */
final class Chunker
{
    /** Tamaño objetivo en "tokens" aproximados. */
    private const TARGET_TOKENS = 450;

    /** Solapamiento entre fragmentos consecutivos. */
    private const OVERLAP_TOKENS = 75;

    /** Un fragmento más corto que esto no aporta contexto suficiente. */
    private const MIN_TOKENS = 20;

    /**
     * @param  list<array{page: int|null, section: string|null, text: string}>  $blocks
     * @return list<array{content: string, section: string|null, page: int|null, tokens: int}>
     */
    public function chunk(array $blocks, string $documentTitle): array
    {
        $chunks = [];

        foreach ($blocks as $block) {
            foreach ($this->splitBlock($block['text']) as $piece) {
                $tokens = $this->estimateTokens($piece);

                if ($tokens < self::MIN_TOKENS) {
                    continue;
                }

                $chunks[] = [
                    // El encabezado va DENTRO del contenido, no solo en los
                    // metadatos: al generar el vector, saber que un texto
                    // pertenece a "Manual del proyector / Encendido" cambia
                    // por completo lo que ese vector representa.
                    'content' => $this->withContext($piece, $documentTitle, $block['section']),
                    'section' => $block['section'],
                    'page' => $block['page'],
                    'tokens' => $tokens,
                ];
            }
        }

        return $chunks;
    }

    /**
     * Parte un bloque respetando procedimientos y párrafos.
     *
     * @return list<string>
     */
    private function splitBlock(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        if ($this->estimateTokens($text) <= self::TARGET_TOKENS) {
            return [$text];
        }

        // Un bloque que ES un procedimiento numerado no se toca, cueste lo
        // que cueste en tamaño. Partirlo sería peor que un fragmento grande.
        if ($this->looksLikeProcedure($text)) {
            return [$text];
        }

        $paragraphs = preg_split('/\n\s*\n/u', $text) ?: [$text];

        $chunks = [];
        $current = [];
        $currentTokens = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $tokens = $this->estimateTokens($paragraph);

            // Un párrafo suelto más grande que el objetivo: va solo, sin
            // arrastrar a los vecinos.
            if ($tokens > self::TARGET_TOKENS) {
                if ($current !== []) {
                    $chunks[] = implode("\n\n", $current);
                    $current = [];
                    $currentTokens = 0;
                }

                $chunks[] = $paragraph;

                continue;
            }

            if ($currentTokens + $tokens > self::TARGET_TOKENS && $current !== []) {
                $chunks[] = implode("\n\n", $current);

                // Solapamiento: se arrastra el último párrafo al siguiente
                // fragmento para no perder el hilo justo en el corte.
                // $current no esta vacio aqui, asi que end() devuelve el
                // ultimo parrafo y nunca false.
                $tail = (string) end($current);
                $current = $this->estimateTokens($tail) <= self::OVERLAP_TOKENS ? [$tail] : [];
                $currentTokens = $current === [] ? 0 : $this->estimateTokens($current[0]);
            }

            $current[] = $paragraph;
            $currentTokens += $tokens;
        }

        if ($current !== []) {
            $chunks[] = implode("\n\n", $current);
        }

        return array_values(array_filter($chunks, static fn (string $c): bool => trim($c) !== ''));
    }

    /**
     * Detecta un procedimiento: tres o más líneas que empiezan por número
     * o viñeta. Tres es el umbral porque con dos podría ser casualidad
     * (una fecha, una enumeración corta dentro de un párrafo).
     */
    private function looksLikeProcedure(string $text): bool
    {
        $numbered = preg_match_all('/^\s*(?:\d+[.)]|[-*•]|[a-z][.)])\s+\S/mu', $text);

        return $numbered !== false && $numbered >= 3;
    }

    private function withContext(string $text, string $documentTitle, ?string $section): string
    {
        $header = $section !== null && $section !== ''
            ? "{$documentTitle} — {$section}"
            : $documentTitle;

        return "[{$header}]\n{$text}";
    }

    /**
     * Estimación de tokens sin tokenizador real.
     *
     * En español, aproximadamente 1 token por cada 4 caracteres. Es una
     * aproximación grosera y basta: aquí solo se usa para decidir dónde
     * cortar, no para nada que dependa de precisión.
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
