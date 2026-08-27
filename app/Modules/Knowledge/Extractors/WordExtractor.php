<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Extractors;

use App\Modules\Knowledge\Contracts\DocumentExtractor;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;

/**
 * Extrae texto de un DOCX conservando los ENCABEZADOS.
 *
 * Los encabezados importan más de lo que parece: son la unidad natural de
 * fragmentación de un procedimiento. Cortar por tamaño ciego partiría un
 * procedimiento de ocho pasos por la mitad, y media instrucción es peor que
 * ninguna (plan 14.2).
 */
final class WordExtractor implements DocumentExtractor
{
    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ], true);
    }

    public function extract(string $absolutePath): array
    {
        $document = IOFactory::load($absolutePath);

        $result = [];
        $currentSection = null;
        $buffer = [];

        foreach ($document->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof Title) {
                    // Nuevo encabezado: se cierra el bloque anterior.
                    if ($buffer !== []) {
                        $result[] = [
                            'page' => null,
                            'section' => $currentSection,
                            'text' => trim(implode("\n", $buffer)),
                        ];
                        $buffer = [];
                    }

                    $currentSection = trim($this->textOf($element->getText()));

                    continue;
                }

                $text = $this->readElement($element);

                if ($text !== '') {
                    $buffer[] = $text;
                }
            }
        }

        if ($buffer !== []) {
            $result[] = [
                'page' => null,
                'section' => $currentSection,
                'text' => trim(implode("\n", $buffer)),
            ];
        }

        return array_values(array_filter($result, static fn (array $b): bool => $b['text'] !== ''));
    }

    public function name(): string
    {
        return 'word';
    }

    private function readElement(object $element): string
    {
        if ($element instanceof TextRun) {
            $parts = [];

            foreach ($element->getElements() as $child) {
                if (method_exists($child, 'getText')) {
                    $parts[] = $this->textOf($child->getText());
                }
            }

            return trim(implode('', $parts));
        }

        if (method_exists($element, 'getText')) {
            return trim($this->textOf($element->getText()));
        }

        return '';
    }

    /** getText() puede devolver un string o un objeto anidado. */
    private function textOf(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, 'getText')) {
            return $this->textOf($value->getText());
        }

        return '';
    }
}
