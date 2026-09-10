<?php

declare(strict_types=1);

use App\Shared\Support\TextNormalizer;

/**
 * Normalizacion de texto.
 *
 * Estas pruebas existen por un defecto real: el clasificador usaba
 * `mb_strtolower()` sin indicar la codificacion, que entonces depende de la
 * configuracion de PHP de cada maquina. La misma frase —«el cañon no
 * prende»— clasificaba como PROYECTOR en Windows y como COMPUTADORA en el
 * servidor de integracion, sin que nada fallara visiblemente.
 *
 * Es la clase de defecto que no aparece hasta que el sistema cambia de
 * computadora, es decir, hasta el dia del piloto.
 */
it('quita las tildes y la eñe', function () {
    expect(TextNormalizer::fold('El CAÑÓN no PRENDE'))->toBe('el canon no prende')
        ->and(TextNormalizer::fold('proyección'))->toBe('proyeccion')
        ->and(TextNormalizer::fold('micrófono'))->toBe('microfono')
        ->and(TextNormalizer::fold('SEÑAL'))->toBe('senal');
});

it('deja igual las tres formas en que se escribe la misma palabra', function () {
    // En un aula nadie escribe con tildes. «cañón», «cañon» y «canon» son la
    // misma palabra dicha por tres docentes distintos.
    $formas = ['cañón', 'cañon', 'CAÑÓN', 'Canon', 'canon'];

    expect(collect($formas)->map(TextNormalizer::fold(...))->unique()->values()->all())
        ->toBe(['canon']);
});

it('no depende de la codificación interna que tenga configurada PHP', function () {
    // El defecto original: sin el segundo argumento, mb_strtolower usa
    // mb_internal_encoding(), que cambia entre maquinas.
    $antes = mb_internal_encoding();

    try {
        mb_internal_encoding('ISO-8859-1');

        expect(TextNormalizer::fold('el CAÑON no prende'))->toBe('el canon no prende');
    } finally {
        mb_internal_encoding($antes === false ? 'UTF-8' : $antes);
    }
});

it('descarta las pistas que se pliegan a la misma palabra', function () {
    // Sin el descarte, una categoria que lista «cañon» y «canon» puntuaria
    // el doble por una sola coincidencia del docente, y ganaria por como
    // esta escrita la lista y no por lo que el docente dijo.
    expect(TextNormalizer::foldAll(['cañon', 'canon', 'proyector']))
        ->toBe(['canon', 'proyector']);
});

it('no toca el texto que ya está normalizado', function () {
    expect(TextNormalizer::fold('el proyector no enciende'))->toBe('el proyector no enciende');
});

it('sobrevive al texto vacío y a los símbolos', function () {
    expect(TextNormalizer::fold(''))->toBe('')
        ->and(TextNormalizer::fold('¿¡...!?'))->toBe('¿¡...!?')
        ->and(TextNormalizer::foldAll([]))->toBe([]);
});
