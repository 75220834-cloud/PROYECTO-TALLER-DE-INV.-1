<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Extractors;

use App\Modules\Knowledge\Contracts\DocumentExtractor;
use Smalot\PdfParser\Parser;

/**
 * Extrae texto de un PDF, página por página.
 *
 * Limitación conocida y que conviene tener presente: si el PDF es un
 * ESCANEO (imágenes sin capa de texto), aquí no sale nada. No es un fallo
 * del sistema; es que ese archivo no contiene texto. La tubería lo detecta
 * y marca el documento como fallido con un mensaje que lo explica, en lugar
 * de indexar un documento vacío que después nadie entendería por qué no
 * responde.
 */
final class PdfExtractor implements DocumentExtractor
{
    public function supports(string $mimeType): bool
    {
        return $mimeType === 'application/pdf';
    }

    public function extract(string $absolutePath): array
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($absolutePath);

        $result = [];
        $pageNumber = 1;

        foreach ($pdf->getPages() as $page) {
            $text = trim($page->getText());

            if ($text !== '') {
                $result[] = [
                    'page' => $pageNumber,
                    'section' => null,
                    'text' => $this->clean($text),
                ];
            }

            $pageNumber++;
        }

        return $result;
    }

    public function name(): string
    {
        return 'pdf';
    }

    /**
     * Limpieza mínima: los extractores de PDF suelen partir palabras al
     * final de línea y meter espacios de más. Sin esto, el índice se llena
     * de términos rotos que después no coinciden con nada al buscar.
     */
    private function clean(string $text): string
    {
        // Une palabras partidas con guion al final de línea.
        $text = preg_replace('/(\w)-\s*\n\s*(\w)/u', '$1$2', $text) ?? $text;

        // Colapsa espacios y saltos repetidos.
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
