<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Services;

use App\Shared\Support\TextNormalizer;

/**
 * Detecta que el docente esta describiendo una situacion de RIESGO FISICO
 * y no una averia corriente (plan 17.5, sonda "sale humo del proyector").
 *
 * POR QUE ESTO NO ES UNA CATEGORIA MAS
 *
 * Ante "sale humo del proyector", la respuesta correcta del sistema NO es
 * abrir el arbol de diagnostico de proyector y pedirle al docente que revise
 * el cable HDMI. Seria pedirle que se acerque a un equipo que puede estar
 * quemandose. El diagnostico guiado se salta entero, se escala de inmediato
 * con prioridad maxima y se le dice explicitamente que no manipule nada.
 *
 * DELIBERADAMENTE POR PALABRAS CLAVE, NO POR MODELO
 *
 * Es la unica decision del sistema donde un falso negativo tiene consecuencia
 * fisica, asi que no puede depender de que Ollama este levantado ni de como
 * responda hoy el modelo. Una lista de palabras es tosca, pero es
 * deterministica, se audita leyendola y funciona con la IA apagada.
 *
 * SE PREFIERE EL FALSO POSITIVO. Si el sistema escala de mas, alguien se
 * acerca a un aula donde no hacia falta: molesto y barato. Si escala de
 * menos, alguien manipula un equipo que echa humo. La asimetria es tan
 * grande que no hay nada que optimizar.
 */
final class SafetySignalDetector
{
    /**
     * Las formas en que un docente sin vocabulario tecnico describe un
     * peligro. Estan en minuscula y sin tildes: el texto se normaliza antes
     * de comparar, porque "sale humo" y "salé humó" deben detectarse igual.
     *
     * Son fragmentos, no palabras completas: "quema" atrapa tambien
     * "quemado" y "quemandose", que es como se escribe de verdad.
     *
     * Cuidado al ampliar la lista: preferir el falso positivo NO significa
     * aceptar cualquiera. Fragmentos demasiado cortos disparan con palabras
     * corrientes y acabarian escalando media bandeja — "arde" esta dentro de
     * "tarde", "llama" dentro de "llamar a soporte" y "agua" dentro de
     * "aguanta". Ninguno de esos tres esta aqui, y por eso.
     */
    private const HAZARD_TERMS = [
        'humo', 'humea',
        'quema', 'quemad', 'quemando', 'olor a quemado',
        'fuego', 'incendi', 'ardiendo',
        'chispa', 'chispea',
        'corto circuito', 'cortocircuito',
        'descarga electrica', 'me dio corriente', 'calambre',

        // 'pelado' suelto, y no 'cable pelado': el docente escribe "el cable
        // está pelado", no "cable pelado". Buscar la frase entera era el tipo
        // de detalle que hace fallar la deteccion justo cuando importa.
        // 'roto' NO esta aqui: un cable roto es una averia corriente y el
        // fragmento aparece en media bandeja ("el proyector esta roto").
        'pelado',
        'explot', 'revento', 'estallo',
        'se calienta mucho', 'muy caliente', 'recalent',
        'mojado', 'goteando', 'filtracion', 'cayo agua', 'agua sobre',
    ];

    public function detect(?string $description): ?string
    {
        if (blank($description)) {
            return null;
        }

        $text = $this->normalize($description);

        foreach (self::HAZARD_TERMS as $term) {
            if (str_contains($text, $term)) {
                return $term;
            }
        }

        return null;
    }

    public function isHazard(?string $description): bool
    {
        return $this->detect($description) !== null;
    }

    /**
     * Minusculas y sin tildes. Un docente escribiendo de pie frente a su
     * clase no acentua, y el sistema no puede fallar por eso.
     */
    private function normalize(string $text): string
    {
        return TextNormalizer::fold($text);
    }
}
