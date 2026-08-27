<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Contracts;

/**
 * Extrae texto de un documento subido.
 *
 * Interfaz por formato para poder añadir soporte de uno nuevo sin tocar la
 * tubería de ingesta. Cada implementación declara qué MIME acepta.
 */
interface DocumentExtractor
{
    public function supports(string $mimeType): bool;

    /**
     * Texto por unidad natural del formato.
     *
     * Se devuelve una LISTA y no un único string a propósito: conservar el
     * límite de página (o de sección) es lo que permite que el asistente
     * cite "p. 12" en lugar de decir vagamente "en el manual" (plan 42).
     *
     * @return list<array{page: int|null, section: string|null, text: string}>
     */
    public function extract(string $absolutePath): array;

    public function name(): string;
}
