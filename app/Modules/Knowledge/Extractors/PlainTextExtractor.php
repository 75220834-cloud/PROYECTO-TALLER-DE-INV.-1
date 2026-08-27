<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Extractors;

use App\Modules\Knowledge\Contracts\DocumentExtractor;

/**
 * Texto plano y Markdown.
 *
 * En Markdown se aprovechan los encabezados (#, ##...) como límites de
 * sección, igual que en Word. Es el formato más cómodo para que soporte
 * escriba procedimientos nuevos sin depender de Office.
 */
final class PlainTextExtractor implements DocumentExtractor
{
    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, [
            'text/plain',
            'text/markdown',
            'text/x-markdown',
            'application/octet-stream',
        ], true);
    }

    public function extract(string $absolutePath): array
    {
        $raw = (string) file_get_contents($absolutePath);

        // Los archivos guardados desde Windows suelen venir en ANSI y con
        // BOM. Sin normalizar, las tildes se indexan rotas y después nada
        // coincide al buscar.
        $raw = $this->normalizeEncoding($raw);

        $result = [];
        $currentSection = null;
        $buffer = [];

        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            if (preg_match('/^#{1,6}\s+(.*)$/u', trim($line), $m) === 1) {
                if ($buffer !== []) {
                    $result[] = [
                        'page' => null,
                        'section' => $currentSection,
                        'text' => trim(implode("\n", $buffer)),
                    ];
                    $buffer = [];
                }

                $currentSection = trim($m[1]);

                continue;
            }

            $buffer[] = $line;
        }

        if ($buffer !== []) {
            $result[] = [
                'page' => null,
                'section' => $currentSection,
                'text' => trim(implode("\n", $buffer)),
            ];
        }

        return array_values(array_filter(
            $result,
            static fn (array $b): bool => trim($b['text']) !== ''
        ));
    }

    public function name(): string
    {
        return 'text';
    }

    private function normalizeEncoding(string $raw): string
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return $raw;
    }
}
