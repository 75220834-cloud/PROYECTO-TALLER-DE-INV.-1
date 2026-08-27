<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Services;

/**
 * Comprueba que una respuesta generada esté ANCLADA en las fuentes.
 *
 * Esta clase existe porque el prompt no es una garantía. Un modelo local
 * pequeño ignora instrucciones con más frecuencia de la que sería cómodo
 * admitir, y "le dijimos que no inventara" no es un control de seguridad.
 * El plan exige tolerancia cero con las alucinaciones (§17.6), y eso solo
 * se sostiene si el código lo verifica.
 *
 * CÓMO SE VERIFICA
 * Se extraen los términos con contenido de la respuesta y se mide qué
 * proporción aparece también en los pasajes recuperados. Una respuesta
 * legítima reformula las fuentes: comparte casi todo su vocabulario
 * sustantivo. Una inventada introduce términos que no están en ninguna
 * parte.
 *
 * LO QUE ESTO NO ES
 * No detecta una respuesta que use las palabras correctas y las combine
 * mal ("desconecta el cable HDMI para que aparezca la imagen"). Es una red
 * de seguridad contra la invención descarada, no una verificación
 * semántica. Combinado con la política de confianza y con la obligación de
 * citar, reduce mucho el riesgo; no lo elimina, y conviene decirlo así en
 * el informe en lugar de vender una garantía que no existe.
 */
final class AnswerVerifier
{
    /** Proporción mínima de términos de la respuesta presentes en las fuentes. */
    private const MIN_GROUNDING = 0.6;

    /**
     * Términos frecuentes que no discriminan nada y falsearían la medida
     * al alza si se contaran.
     *
     * @var list<string>
     */
    private const STOPWORDS = [
        'para', 'con', 'que', 'los', 'las', 'del', 'una', 'uno', 'esta', 'este',
        'como', 'por', 'mas', 'muy', 'pero', 'debe', 'puede', 'hacer', 'estar',
        'tiene', 'sobre', 'entre', 'cuando', 'donde', 'porque', 'todo', 'toda',
        'desde', 'hasta', 'aunque', 'segun', 'luego', 'antes', 'despues',
    ];

    /**
     * @param  list<string>  $passages
     */
    public function isGrounded(string $answer, array $passages): bool
    {
        $answerTerms = $this->terms($answer);

        if ($answerTerms === []) {
            return false;
        }

        $sourceTerms = [];

        foreach ($passages as $passage) {
            foreach ($this->terms($passage) as $term) {
                $sourceTerms[$term] = true;
            }
        }

        if ($sourceTerms === []) {
            return false;
        }

        $found = 0;

        foreach ($answerTerms as $term) {
            if (isset($sourceTerms[$term])) {
                $found++;
            }
        }

        return ($found / count($answerTerms)) >= self::MIN_GROUNDING;
    }

    /**
     * Rechaza respuestas peligrosas por su contenido, no por su origen.
     *
     * Aunque la respuesta esté perfectamente anclada en un documento real,
     * el sistema NUNCA debe decirle a un docente que abra un equipo o que
     * manipule cableado eléctrico (plan 13.4, regla 8). Si un documento
     * institucional contiene ese paso, es un paso para un técnico, no para
     * quien está dando clase.
     */
    public function isSafe(string $answer): bool
    {
        $normalized = mb_strtolower($answer);

        $forbidden = [
            'abre el equipo', 'abrir el equipo', 'destornilla', 'desatornilla',
            'desarma', 'desmonta', 'quita la tapa', 'saca la tapa',
            'manipula el cableado', 'cable eléctrico', 'cable electrico',
            'toca los contactos', 'interruptor eléctrico', 'tablero eléctrico',
        ];

        foreach ($forbidden as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Detecta certezas absolutas.
     *
     * El plan lo prohíbe expresamente (§44): el asistente nunca debe decir
     * que algo "definitivamente funcionará". Prometer un resultado que no
     * puede garantizar destruye la confianza del docente la primera vez que
     * falla, y con ella la del sistema entero.
     */
    public function hasOverconfidentClaim(string $answer): bool
    {
        $normalized = mb_strtolower($answer);

        foreach (['definitivamente funciona', 'seguro que funciona', 'esto lo soluciona siempre',
            'garantizado', 'con toda seguridad', 'sin duda funcionará', 'sin duda funcionara'] as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function terms(string $text): array
    {
        $normalized = mb_strtolower($text);

        $normalized = strtr($normalized, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]);

        $words = preg_split('/[^a-z0-9ñ]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = array_filter(
            $words,
            static fn (string $w): bool => mb_strlen($w) > 3 && ! in_array($w, self::STOPWORDS, true)
        );

        return array_values(array_unique($terms));
    }
}
