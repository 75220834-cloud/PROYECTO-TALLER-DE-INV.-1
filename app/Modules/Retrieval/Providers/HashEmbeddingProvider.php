<?php

declare(strict_types=1);

namespace App\Modules\Retrieval\Providers;

use App\Modules\Retrieval\Contracts\EmbeddingProvider;

/**
 * Vectores DETERMINISTAS sin modelo, por hashing de términos.
 *
 * Cumple dos papeles y conviene ser claro sobre ambos:
 *
 *  1. EN PRUEBAS es el proveedor por defecto. Una suite que dependa de un
 *     modelo real sería lenta, exigiría Ollama levantado en cada máquina y
 *     en CI, y daría resultados distintos entre ejecuciones. Aquí el mismo
 *     texto produce siempre el mismo vector.
 *
 *  2. EN PRODUCCIÓN SIN MODELO permite que la base de conocimiento se
 *     indexe igual y que la búsqueda siga funcionando, apoyada sobre todo
 *     en el filtro léxico. La calidad semántica es MUY inferior a la de un
 *     modelo real: no reconoce que "no se ve nada" y "ausencia de señal de
 *     vídeo" hablan de lo mismo.
 *
 * Es decir: sirve para que el sistema no se caiga, no para sustituir a un
 * modelo. Si se usa en el piloto, hay que decirlo en el informe.
 */
final class HashEmbeddingProvider implements EmbeddingProvider
{
    private const DIMENSIONS = 256;

    /**
     * Siempre devuelve un vector: el hashing no puede fallar. Se estrecha
     * el tipo respecto de la interfaz (que admite null) porque aqui esa
     * posibilidad no existe y declararla obligaria a comprobarla en vano.
     *
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $vector = array_fill(0, self::DIMENSIONS, 0.0);

        foreach ($this->terms($text) as $term) {
            // Posición estable del término dentro del vector.
            $bucket = (int) (hexdec(substr(md5($term), 0, 8)) % self::DIMENSIONS);

            // El signo también sale del hash: reparte los términos en
            // ambos sentidos y evita que todos los vectores apunten a la
            // misma región del espacio.
            $sign = (hexdec(substr(md5($term), 8, 2)) % 2) === 0 ? 1.0 : -1.0;

            $vector[$bucket] += $sign;
        }

        return $vector;
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }

    public function dimensions(): int
    {
        return self::DIMENSIONS;
    }

    public function modelIdentifier(): string
    {
        // Identificador explícito: si un día se cambia a un modelo real, el
        // desajuste se detecta y obliga a reindexar en lugar de mezclar
        // vectores incompatibles en silencio.
        return 'hash-256';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /** @return list<string> */
    private function terms(string $text): array
    {
        $normalized = mb_strtolower($text);

        // Se quitan las tildes para que "proyección" y "proyeccion" caigan
        // en el mismo término: en la práctica los docentes escriben ambas.
        $normalized = strtr($normalized, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]);

        $words = preg_split('/[^a-z0-9ñ]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Palabras de una o dos letras no discriminan nada.
        return array_values(array_filter($words, static fn (string $w): bool => mb_strlen($w) > 2));
    }
}
