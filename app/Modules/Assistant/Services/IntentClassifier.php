<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Services;

use App\Modules\Assistant\Contracts\Classification;
use App\Modules\Assistant\Contracts\LlmProvider;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Shared\Support\TextNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Clasifica el texto libre del docente en una categoria del catalogo.
 *
 * Funcion F1 del plan (13.2). Es la unica pieza donde el modelo de lenguaje
 * toca el camino principal, y esta acotada al maximo:
 *
 *  - la respuesta se valida contra el catalogo REAL de categorias;
 *  - si el modelo no esta o falla, se cae a coincidencia por palabras clave;
 *  - si ninguna de las dos da nada claro, el docente elige con botones.
 *
 * Es decir: el sistema NUNCA depende de que la clasificacion acierte. El
 * camino de botones es el principal; esto solo lo acelera cuando el docente
 * prefiere escribir.
 */
final class IntentClassifier
{
    /**
     * Pistas que NOMBRAN un equipo.
     *
     * Se separan de los sintomas porque pesan mas, y conviene explicar por
     * que: si el docente nombra el equipo, eso es lo que esta roto. El
     * sintoma solo deberia decidir cuando no se nombra ninguno.
     *
     * El caso que obligo a esta separacion es la frase mas comun del
     * proyecto: «el cañón no prende». Nombra el proyector y describe un
     * sintoma que la lista asociaba a la computadora. Puntuando por
     * longitud —como se hacia antes— «no prende» (9 caracteres) le ganaba a
     * «cañon» (5), y el sistema mandaba un tecnico a revisar la
     * computadora de un aula donde el problema era el proyector.
     *
     * @var array<string, list<string>>
     */
    private const DEVICE_TERMS = [
        'PROJECTOR' => ['proyector', 'canon', 'cañon', 'cañón', 'proyeccion', 'proyección', 'ecran'],
        'HDMI_VIDEO' => ['hdmi', 'video', 'vídeo', 'cable de video'],
        'AUDIO' => ['sonido', 'audio', 'volumen'],
        'MICROPHONE' => ['microfono', 'micrófono', 'micro'],
        'SPEAKERS' => ['parlante', 'altavoz', 'bocina', 'cornetas'],
        'COMPUTER' => ['computadora', 'compu', 'cpu', 'pc'],
        'KEYBOARD' => ['teclado', 'teclas'],
        'MOUSE' => ['mouse', 'raton', 'ratón'],
        'NETWORK' => ['cable de red', 'ethernet', 'punto de red'],
        'INTERNET' => ['internet', 'wifi', 'wi-fi'],
        'SOFTWARE' => ['programa', 'software', 'aplicacion', 'aplicación'],
        'SCREEN' => ['pantalla', 'monitor'],
    ];

    /**
     * Pistas que describen un SINTOMA, sin nombrar el equipo.
     *
     * Solo deciden cuando el docente no nombro ningun equipo. «No prende»
     * puede ser la computadora, el proyector o los parlantes; sin mas
     * informacion, la computadora es la apuesta razonable, pero es una
     * apuesta y no una certeza.
     *
     * Vive en codigo y no en base de datos a proposito: es logica de
     * respaldo, no configuracion operativa. Si se editara desde el panel,
     * un cambio descuidado dejaria al sistema sin red de seguridad justo
     * cuando el modelo no esta disponible.
     *
     * @var array<string, list<string>>
     */
    private const SYMPTOM_TERMS = [
        'PROJECTOR' => ['no proyecta', 'proyecta mal', 'se ve morado', 'se ve verde'],
        'HDMI_VIDEO' => ['no se ve', 'sin imagen', 'no aparece', 'pantalla azul', 'pantalla negra', 'sin señal'],
        'AUDIO' => ['no suena', 'no escucha', 'no se oye', 'sin sonido'],
        'COMPUTER' => ['no prende', 'no enciende', 'se colgo', 'se colgó', 'no arranca'],
        'INTERNET' => ['sin conexion', 'sin conexión', 'no navega', 'no hay señal'],
        'SOFTWARE' => ['no abre', 'se cierra solo'],
    ];

    public function __construct(private readonly LlmProvider $llm) {}

    public function classify(string $text): Classification
    {
        $text = trim($text);

        if ($text === '') {
            return Classification::unknown('empty');
        }

        $allowed = $this->allowedCodes();

        if ($allowed === []) {
            return Classification::unknown('no_catalog');
        }

        $fromLlm = $this->llm->classify($text, $allowed);

        // VALIDACION OBLIGATORIA contra el catalogo real.
        //
        // No basta con que el proveedor valide por su cuenta: esto es la
        // frontera de la que depende todo el sistema, y tiene que sostenerse
        // aunque el proveedor cambie, se sustituya o tenga un fallo. Si el
        // modelo devuelve una categoria que no existe, aqui se descarta y se
        // sigue con el respaldo por palabras clave.
        //
        // Sin esta comprobacion, un modelo podria introducir una categoria
        // inventada en el sistema, que es exactamente la alucinacion que el
        // plan prohibe con tolerancia cero (plan 13.4 y 17.6).
        if ($fromLlm->label !== null && in_array($fromLlm->label, $allowed, true)) {
            return $fromLlm;
        }

        if ($fromLlm->label !== null) {
            Log::warning('El modelo devolvió una categoría inexistente; se descarta.', [
                'returned' => $fromLlm->label,
                'provider' => $this->llm->identifier(),
            ]);
        }

        // Respaldo sin modelo. Confianza deliberadamente moderada: unas
        // palabras clave no son un clasificador, y marcarlas como certeza
        // haria que el sistema asumiera categorias sin preguntar.
        return $this->byKeywords($text, $allowed);
    }

    /**
     * Decision a partir de la clasificacion.
     *
     * Tres desenlaces, en linea con la politica de confianza del plan (13.5):
     *   accept  - se preselecciona la categoria y se pide confirmar
     *   ask     - hay candidatas, se le muestran para que elija
     *   manual  - no hay nada claro: catalogo completo en botones
     */
    public function decide(Classification $classification): string
    {
        $high = config('incidencias.assistant.confidence_high');
        $low = config('incidencias.assistant.confidence_low');

        // Umbrales sin calibrar todavia (plan 13.5): mientras no existan
        // datos etiquetados, se opera en modo CONSERVADOR y nunca se asume
        // una categoria por cuenta propia. Escalar de mas es el error
        // barato; asumir mal es el caro.
        if ($high === null || $low === null) {
            return $classification->label !== null ? 'ask' : 'manual';
        }

        if ($classification->isConfident((float) $high)) {
            return 'accept';
        }

        if ($classification->confidence >= (float) $low) {
            return 'ask';
        }

        return 'manual';
    }

    /** @return list<string> */
    /**
     * Puntua el texto contra un catalogo de pistas.
     *
     * Dentro de un mismo catalogo, la pista mas larga es la mas especifica:
     * «cable de red» dice mucho mas que «red».
     *
     * @param  array<string, list<string>>  $catalog
     * @param  list<string>  $allowed
     * @return array<string, int>
     */
    private function scoreAgainst(string $normalized, array $catalog, array $allowed): array
    {
        $scores = [];

        foreach ($catalog as $code => $hints) {
            if (! in_array($code, $allowed, true)) {
                continue;
            }

            foreach (TextNormalizer::foldAll($hints) as $hint) {
                if (str_contains($normalized, $hint)) {
                    $scores[$code] = ($scores[$code] ?? 0) + mb_strlen($hint);
                }
            }
        }

        arsort($scores);

        return $scores;
    }

    private function allowedCodes(): array
    {
        return IncidentCategory::query()
            ->active()
            ->where('code', '!=', 'OTHER')
            ->pluck('code')
            ->all();
    }

    /**
     * @param  list<string>  $allowed
     */
    private function byKeywords(string $text, array $allowed): Classification
    {
        // Se pliegan las DOS partes: el texto del docente y las pistas.
        // Normalizar solo una haria que una pista escrita con tilde dejara
        // de coincidir con nada, y en silencio.
        $normalized = TextNormalizer::fold($text);

        // Los equipos primero. Si alguna categoria coincide por el nombre
        // del equipo, los sintomas ya no compiten: quedan fuera del
        // reparto por completo, no como un empate a resolver.
        $scores = $this->scoreAgainst($normalized, self::DEVICE_TERMS, $allowed);

        if ($scores === []) {
            $scores = $this->scoreAgainst($normalized, self::SYMPTOM_TERMS, $allowed);
        }

        if ($scores === []) {
            return Classification::unknown('keywords');
        }

        // Ya vienen ordenados por scoreAgainst().
        $codes = array_keys($scores);

        return new Classification(
            label: (string) $codes[0],
            // Varias categorias plausibles => ambiguo, y hay que preguntar.
            confidence: count($scores) > 1 ? 0.5 : 0.7,
            alternatives: array_slice($codes, 1, 2),
            method: 'keywords',
        );
    }
}
