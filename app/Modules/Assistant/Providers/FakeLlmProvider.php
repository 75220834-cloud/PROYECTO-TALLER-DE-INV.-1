<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Providers;

use App\Modules\Assistant\Contracts\Classification;
use App\Modules\Assistant\Contracts\LlmProvider;
use App\Shared\Support\TextNormalizer;

/**
 * Proveedor DETERMINISTA para pruebas (plan 17.3).
 *
 * Las pruebas nunca deben depender de un modelo real: seria lento, exigiria
 * tener Ollama levantado en cada maquina y en CI, y sobre todo daria
 * resultados distintos entre ejecuciones. Una suite que falla o pasa segun
 * el humor del modelo no sirve para nada.
 *
 * Clasifica por palabras clave. No pretende ser bueno: pretende ser
 * PREDECIBLE, para poder afirmar cosas sobre el resto del sistema.
 */
final class FakeLlmProvider implements LlmProvider
{
    /** @var array<string, list<string>> etiqueta => pistas */
    private const HINTS = [
        'PROJECTOR' => ['proyector', 'cañon', 'canon', 'proyecta'],
        'HDMI' => ['hdmi', 'imagen', 'video', 'no se ve', 'pantalla azul'],
        'SPEAKER' => ['parlante', 'altavoz', 'bocina', 'sonido', 'audio', 'no suena', 'volumen'],
        'SCREEN' => ['ecran', 'ecram', 'telon'],
        'PROJECTOR_REMOTE' => ['control del proyector', 'control remoto', 'mando'],
        'COMPUTER' => ['computadora', 'compu', 'pc', 'cpu', 'no prende'],
        'KEYBOARD' => ['teclado'],
        'MOUSE' => ['mouse', 'raton', 'ratón'],
        'OTHER' => ['internet', 'wifi', 'red', 'programa', 'software', 'microfono'],
    ];

    /** @var list<string> */
    public array $classifyCalls = [];

    public function classify(string $text, array $allowedLabels): Classification
    {
        $this->classifyCalls[] = $text;

        $normalized = TextNormalizer::fold($text);
        $matches = [];

        foreach (self::HINTS as $label => $hints) {
            if (! in_array($label, $allowedLabels, true)) {
                continue;
            }

            foreach (TextNormalizer::foldAll($hints) as $hint) {
                if (str_contains($normalized, $hint)) {
                    $matches[$label] = ($matches[$label] ?? 0) + 1;
                }
            }
        }

        if ($matches === []) {
            return Classification::unknown('fake');
        }

        // arsort es estable desde PHP 8.0, asi que un empate lo decide el
        // orden de declaracion de HINTS. Se deja dicho porque de ese detalle
        // depende que "el cañon no prende" —que nombra un equipo y describe
        // un sintoma de otro— clasifique siempre igual.
        arsort($matches);
        $label = (string) array_key_first($matches);

        // Varias categorias plausibles => AMBIGUO. Devolver confianza alta
        // aqui haria que el sistema asumiera una y se saltara la pregunta
        // de desambiguacion, que es justo lo que el plan exige NO hacer.
        $confidence = count($matches) > 1 ? 0.45 : 0.9;

        return new Classification(
            label: $label,
            confidence: $confidence,
            alternatives: array_slice(array_keys($matches), 1),
            method: 'fake',
        );
    }

    public function rephrase(string $text, string $context = ''): ?string
    {
        return null;
    }

    public function answerGrounded(string $question, array $passages): ?string
    {
        // Determinista para pruebas: nunca genera. Asi las pruebas del
        // asistente comprueban el camino de respaldo, que es el que de
        // verdad tiene que ser correcto.
        return null;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function identifier(): string
    {
        return 'fake';
    }
}
