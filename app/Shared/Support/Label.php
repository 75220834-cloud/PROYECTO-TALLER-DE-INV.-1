<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Nombre en español de un código interno (rol, acción, modelo...).
 *
 * Si el código no tiene traducción se devuelve tal cual, en vez de la clave
 * «grupo.codigo»: un dato nuevo se ve feo, pero nunca desaparece.
 */
final class Label
{
    /** @return string|array<mixed> */
    public static function for(string $group, string $code): string|array
    {
        // Se busca el código dentro del grupo entero, no con «grupo.código»:
        // códigos como «media.updated» llevan puntos, y el traductor los
        // tomaría por niveles anidados y no encontraría nada.
        $all = __($group);

        return is_array($all) && array_key_exists($code, $all) ? $all[$code] : $code;
    }
}
