<?php

declare(strict_types=1);

namespace App\Modules\Retrieval\Contracts;

/**
 * Genera los vectores con los que se busca en la base de conocimiento.
 *
 * `modelIdentifier()` NO es informativo: se guarda junto a cada fragmento y
 * se compara al buscar. Cambiar de modelo de embeddings invalida todos los
 * vectores anteriores, y mezclarlos daría resultados silenciosamente
 * incorrectos — el fallo más difícil de detectar de todo el módulo, porque
 * el sistema seguiría respondiendo, solo que mal (plan 14.5).
 */
interface EmbeddingProvider
{
    /**
     * @return list<float>|null null si no se pudo generar
     */
    public function embed(string $text): ?array;

    /**
     * @param  list<string>  $texts
     * @return list<list<float>|null>
     */
    public function embedBatch(array $texts): array;

    public function dimensions(): int;

    public function modelIdentifier(): string;

    public function isAvailable(): bool;
}
