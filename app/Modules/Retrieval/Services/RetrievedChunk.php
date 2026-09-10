<?php

declare(strict_types=1);

namespace App\Modules\Retrieval\Services;

use App\Modules\Knowledge\Models\KnowledgeChunk;

/**
 * Un fragmento recuperado, con la traza de cómo llegó al resultado.
 *
 * Se conservan las dos posiciones de origen (léxica y semántica) porque
 * sirven para dos cosas distintas:
 *
 *  - El evaluador de confianza las usa para saber si el resultado es sólido
 *    o si solo apareció por una de las dos vías.
 *  - Al depurar, dicen POR QUÉ el sistema recuperó algo aparentemente
 *    irrelevante, que de otro modo es imposible de reconstruir.
 */
final readonly class RetrievedChunk
{
    public function __construct(
        public KnowledgeChunk $chunk,
        public float $score,
        public ?int $lexicalRank = null,
        public ?int $semanticRank = null,

        /**
         * Similitud coseno con la pregunta, entre 0 y 1.
         *
         * Se conserva ADEMAS de la posicion porque responden preguntas
         * distintas: la posicion dice «es el mejor de los candidatos», la
         * similitud dice «se parece de verdad». Un fragmento puede ser el
         * mejor de un conjunto en el que nada sirve.
         *
         * Null cuando no hubo vectores disponibles.
         */
        public ?float $similarity = null,
    ) {}

    public function citation(): string
    {
        return $this->chunk->citation();
    }

    public function content(): string
    {
        return $this->chunk->content;
    }

    /**
     * Apareció por AMBAS vías.
     *
     * Es la señal de recuperación más fuerte que hay: coincide en palabras
     * y también en significado. Un fragmento que solo sale por una vía
     * puede ser una coincidencia de vocabulario o un parecido vago.
     */
    public function foundByBoth(): bool
    {
        return $this->lexicalRank !== null && $this->semanticRank !== null;
    }

    /**
     * ¿Este fragmento tiene algo que ver con la pregunta?
     *
     * Existe por un fallo real, encontrado probando el asistente con el
     * corpus ya cargado: a «cuánto cuesta la matrícula» el sistema devolvía
     * pasajes sobre el control del proyector con confianza media, en lugar
     * de escalar. La palabra «cuesta» aparecía en «cuesta más subir hasta el
     * equipo», y con eso bastaba.
     *
     * La causa es que la fusión por posición no conserva ninguna medida
     * absoluta: el peor de cuatro candidatos sigue teniendo una posición, y
     * la salvaguarda que amplía el prefiltro garantiza que siempre haya
     * candidatos aunque ninguno sirva.
     *
     * POR QUE NO BASTA UN UMBRAL DE SIMILITUD
     *
     * Se midió, y no separan. Las preguntas ajenas al dominio dieron entre
     * 0,506 y 0,602; las legítimas, entre 0,582 y 0,783. Se solapan. El
     * modelo de vectores comprime las similitudes en una banda estrecha, así
     * que cualquier umbral único deja pasar basura o descarta preguntas
     * buenas.
     *
     * LO QUE SI SEPARA
     *
     * Que las dos vías coincidan. En las preguntas legítimas el mejor
     * fragmento estaba arriba en AMBOS rankings (léxico 1 / semántico 3);
     * en las ajenas, el que ganaba por léxico caía al puesto 22, 39 o 52 en
     * el semántico. Compartir una palabra suelta es fácil; compartir
     * vocabulario Y significado, no.
     *
     * La segunda condición —similitud alta a secas— mantiene vivo el caso
     * que justifica la búsqueda híbrida: la paráfrasis que no comparte
     * ninguna palabra con el documento y solo puede llegar por el vector.
     *
     * CALIBRACION PENDIENTE. Los dos números salen de cinco preguntas de
     * prueba, no de un conjunto etiquetado. Están elegidos del lado
     * conservador —se prefiere escalar de más— y hay que ajustarlos con
     * datos reales del piloto.
     */
    public function isRelevant(float $floor, int $agreementRank): bool
    {
        $porAmbas = $this->lexicalRank !== null
            && $this->semanticRank !== null
            && $this->lexicalRank <= $agreementRank
            && $this->semanticRank <= $agreementRank;

        if ($porAmbas) {
            return true;
        }

        // Sin vectores el sistema se apoya solo en el léxico: ahí una
        // coincidencia de palabras es todo lo que hay, y se acepta.
        if ($this->similarity === null) {
            return $this->lexicalRank !== null;
        }

        return $this->similarity >= $floor;
    }
}
