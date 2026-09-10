<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Normalizacion de texto para comparar palabras.
 *
 * POR QUE EXISTE ESTA CLASE, Y POR QUE FUE UN DEFECTO REAL
 *
 * Cuatro sitios del sistema comparan el texto libre del docente contra
 * listas de palabras: el clasificador de intencion, su proveedor de
 * pruebas, el detector de riesgo fisico y el verificador de respuestas.
 * Cada uno normalizaba a su manera, y uno de ellos —el clasificador— usaba
 * `mb_strtolower()` SIN indicar la codificacion.
 *
 * Sin ese segundo argumento, la funcion usa `mb_internal_encoding()`, que
 * depende de la configuracion de PHP de cada maquina. En XAMPP sobre
 * Windows salia UTF-8 y todo funcionaba; en el servidor de integracion no,
 * y «cañon» dejaba de coincidir con «cañon». El sintoma era absurdo: la
 * misma frase clasificaba como PROYECTOR en una maquina y como COMPUTADORA
 * en otra, sin que nada fallara visiblemente.
 *
 * Es exactamente la clase de defecto que no aparece hasta que el sistema se
 * mueve de computadora — es decir, hasta el dia del piloto.
 *
 * QUE HACE `fold()`
 *
 * Ademas de fijar la codificacion, quita las tildes y convierte la eñe. En
 * un aula nadie escribe con tildes: «cañón», «cañon» y «canon» son la misma
 * palabra dicha por tres docentes distintos, y las tres tienen que
 * clasificar igual. Esto no es tolerancia a errores: es como se escribe de
 * verdad desde un celular, de pie, con una clase esperando.
 */
final class TextNormalizer
{
    /**
     * Tildes y eñe. La 'ç' esta por los apellidos y marcas que aparecen en
     * los codigos de equipo.
     *
     * @var array<string, string>
     */
    private const FOLDED = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
        'ç' => 'c',
    ];

    /** Minusculas, con la codificacion fijada de forma explicita. */
    public static function lower(string $text): string
    {
        return mb_strtolower($text, 'UTF-8');
    }

    /** Mayusculas, con la codificacion fijada de forma explicita. */
    public static function upper(string $text): string
    {
        return mb_strtoupper($text, 'UTF-8');
    }

    /**
     * Minusculas y sin tildes ni eñe.
     *
     * Es lo que hay que usar para comparar contra una lista de palabras
     * clave, y hay que aplicarlo TAMBIEN a las palabras de la lista: si
     * solo se normaliza el texto del docente, una pista escrita con tilde
     * deja de coincidir con nada y desaparece en silencio.
     */
    public static function fold(string $text): string
    {
        return strtr(self::lower($text), self::FOLDED);
    }

    /**
     * Normaliza una lista de pistas y elimina las que quedan repetidas.
     *
     * El descarte importa: «cañon» y «canon» son dos entradas distintas en
     * el catalogo de pistas y se pliegan a la misma. Sin eliminar el
     * duplicado, esa categoria puntuaria el doble por una sola coincidencia
     * del docente, y ganaria por un detalle de como esta escrita la lista y
     * no por lo que dijo el docente.
     *
     * @param  list<string>  $hints
     * @return list<string>
     */
    public static function foldAll(array $hints): array
    {
        return array_values(array_unique(array_map(self::fold(...), $hints)));
    }
}
