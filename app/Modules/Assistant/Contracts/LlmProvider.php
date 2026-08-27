<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Contracts;

/**
 * Proveedor de modelo de lenguaje (plan 13.3).
 *
 * La aplicacion NUNCA habla con Ollama ni con ninguna API directamente:
 * habla con esta interfaz. Cambiar de modelo local, pasar a otro servidor
 * de inferencia o desactivar la IA por completo es cambiar una variable de
 * entorno, no tocar la logica de negocio.
 *
 * Requisito NO negociable del plan: el sistema debe funcionar entero con
 * un proveedor nulo. Si alguna funcionalidad deja de estar disponible sin
 * modelo, es un defecto (plan 13.1).
 */
interface LlmProvider
{
    /**
     * Clasifica un texto libre en una de las etiquetas permitidas.
     *
     * La lista de etiquetas se pasa SIEMPRE y la respuesta se valida contra
     * ella: el modelo no puede inventar una categoria que no exista en el
     * catalogo.
     *
     * @param  list<string>  $allowedLabels
     */
    public function classify(string $text, array $allowedLabels): Classification;

    /**
     * Reformula un texto para hacerlo mas claro, sin anadir informacion.
     *
     * Devuelve null si no puede: quien llama debe conservar el texto
     * original como respaldo valido.
     */
    public function rephrase(string $text, string $context = ''): ?string;

    /**
     * Responde una pregunta USANDO SOLO los pasajes entregados.
     *
     * Es la funcion F3 del plan (13.2) y la mas delicada de las tres. Tres
     * reglas que no son sugerencias:
     *
     *  - Si los pasajes no contienen la respuesta, devuelve null. No
     *    "intentarlo", no "aproximarse": null. Inventar un procedimiento
     *    institucional es la alucinacion mas grave que puede cometer este
     *    sistema (plan 44 y 55).
     *  - Nunca puede anadir informacion que no este en los pasajes.
     *  - Quien llama VERIFICA la respuesta contra los pasajes y la descarta
     *    si no esta anclada. La defensa no se confia al prompt.
     *
     * @param  list<string>  $passages
     */
    public function answerGrounded(string $question, array $passages): ?string;

    /** Si el proveedor esta operativo ahora mismo. */
    public function isAvailable(): bool;

    /** Identificador del modelo, para registrarlo con cada interaccion. */
    public function identifier(): string;
}
