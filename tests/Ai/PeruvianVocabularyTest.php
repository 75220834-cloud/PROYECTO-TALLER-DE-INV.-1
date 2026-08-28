<?php

declare(strict_types=1);

use App\Modules\Assistant\Providers\OllamaProvider;

/**
 * El vocabulario del aula peruana en el prompt del clasificador.
 *
 * POR QUÉ ESTA PRUEBA NO LLAMA AL MODELO
 *
 * Ejecutar Ollama en la suite la haría lenta, dependiente de que el servidor
 * esté levantado y no reproducible entre máquinas. Lo que sí se puede fijar
 * —y es lo que de verdad se rompería sin darse cuenta— es que los términos
 * sigan en el prompt: si alguien "limpia" el glosario por parecerle
 * relleno, esto falla.
 *
 * La verificación contra el modelo real se hizo a mano y está documentada:
 * con el glosario, «el cañón no prende» clasifica como PROYECTOR; sin él,
 * el modelo lo daba por MICRÓFONO o COMPUTADORA.
 */
it('el prompt enseña al modelo cómo se habla en un aula peruana', function (string $termino) {
    $prompt = (new ReflectionClass(OllamaProvider::class))->getFileName();
    $codigo = file_get_contents($prompt);

    expect($codigo)->toContain($termino);
})->with([
    // El más importante: un modelo entrenado con español general lo
    // clasificaba como micrófono.
    'cañón',
    'compu',
    'ecran',
    'no jala',
    'malogrado',
    'parlantes',
]);

it('avisa explícitamente de la confusión más cara', function () {
    $codigo = file_get_contents((new ReflectionClass(OllamaProvider::class))->getFileName());

    // Sin esta línea el modelo acertaba «proyector» pero fallaba «cañón»,
    // que es como lo dice de verdad casi todo el mundo.
    expect($codigo)->toContain('«cañón» significa proyector');
});

it('pide bajar la confianza en lugar de acertar por casualidad', function () {
    $codigo = file_get_contents((new ReflectionClass(OllamaProvider::class))->getFileName());

    // Una confianza inflada haría que el sistema asumiera la categoría sin
    // preguntar, y un docente acabaría en un árbol de diagnóstico ajeno a
    // su problema.
    expect($codigo)->toContain('preferible preguntar al docente');
});
