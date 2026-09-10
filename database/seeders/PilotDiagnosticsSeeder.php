<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Diagnostics\Models\DiagnosticFlow;
use App\Modules\Diagnostics\Models\DiagnosticFlowVersion;
use App\Modules\Diagnostics\Models\DiagnosticStep;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Media\Models\MediaAsset;
use Illuminate\Database\Seeder;

/**
 * Arboles de diagnostico del piloto: uno por cada equipo del checklist.
 *
 * ESTOS NO SON DATOS DE DEMOSTRACION. Son los procedimientos con los que el
 * sistema va a atender a docentes reales, y por eso no llevan is_demo ni
 * desaparecen al purgar las pruebas.
 *
 * QUIEN LOS ESCRIBIO Y QUE VALEN
 *
 * Los redacto el equipo del proyecto porque soporte no tenia procedimientos
 * escritos. El contenido es tecnicamente correcto y verificable —revisar un
 * cable HDMI se hace igual en cualquier aula del mundo— pero NO es normativa
 * institucional: nadie de la universidad los ha aprobado todavia. Conviene
 * decirlo asi en el informe en lugar de presentarlos como oficiales.
 *
 * CUATRO PANTALLAS COMO MAXIMO
 *
 * Tres preguntas de diagnostico y una de comprobacion. El limite no es
 * tecnico: un docente de pie, con cuarenta personas esperando, abandona
 * antes del quinto paso. Alargar el arbol no resuelve mas casos — solo hace
 * que se vaya por el canal informal, que es lo que el piloto mide.
 *
 * HASTA DONDE LLEGA LA MANO DEL DOCENTE
 *
 * Puede prender y apagar equipos, usar el control, reconectar cables sueltos
 * (HDMI, USB, audio, corriente), mover regletas y cambiar de tomacorriente.
 * NO puede abrir ningun equipo ni tocar tableros electricos. Ese limite lo
 * fijo el responsable del proyecto y ningun paso lo cruza.
 *
 * TODA RAMA TERMINA
 *
 * En «resuelto» o en «llamar a soporte». Un paso sin salida deja al docente
 * atrapado mirando una pantalla, y esa es la peor experiencia posible: peor
 * que no haber ofrecido diagnostico.
 */
class PilotDiagnosticsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->flows() as $code => [$name, $steps]) {
            $this->publish($code, $name, $steps);
        }

        /*
         * Arboles de categorias que ya no se ofrecen: se desactivan.
         *
         * No se borran por el mismo motivo que las categorias: hay
         * incidencias que recorrieron esos pasos y sus respuestas tienen que
         * seguir siendo interpretables contra el arbol que se les mostro de
         * verdad. Desactivado deja de servirse y sigue explicando el pasado.
         */
        DiagnosticFlow::query()
            ->whereIn('category_id', IncidentCategory::query()->where('is_active', false)->select('id'))
            ->update(['is_active' => false]);
    }

    /**
     * Opciones de cierre que se repiten en todos los arboles.
     *
     * @return array<string, mixed>
     */
    private function finalCheck(string $pregunta): array
    {
        return [
            'key' => 'final_check',
            'text' => $pregunta,
            'help' => null,
            'component' => null,
            'options' => [
                ['value' => 'yes', 'label' => 'Sí, ya funciona'],
                ['value' => 'no', 'label' => 'No, sigue igual'],
            ],
            'next' => ['yes' => 'END_RESOLVED', 'no' => 'END_ESCALATE'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: list<array<string, mixed>>}>
     */
    private function flows(): array
    {
        return [
            'COMPUTER' => ['La computadora no prende o no responde', [
                [
                    'key' => 'luz',
                    'text' => 'Mira la computadora. ¿Tiene alguna luz encendida?',
                    'help' => 'Suele ser una luz pequeña, blanca o azul, cerca del botón de encendido.',
                    'component' => 'computer_power',
                    'options' => [
                        ['value' => 'apagada', 'label' => 'No, está completamente apagada'],
                        ['value' => 'encendida', 'label' => 'Sí, tiene luz pero la pantalla está negra'],
                        ['value' => 'no_se', 'label' => 'No veo ninguna luz'],
                    ],
                    'next' => ['apagada' => 'corriente', 'encendida' => 'reiniciar', 'no_se' => 'corriente'],
                ],
                [
                    'key' => 'corriente',
                    'text' => 'Revisa la regleta donde está enchufada: que esté prendida y que el cable no esté flojo.',
                    'help' => 'La regleta es el enchufe múltiple con un interruptor rojo o naranja. Si está apagado, préndelo.',
                    'component' => 'power_strip',
                    'options' => [
                        ['value' => 'arreglado', 'label' => 'Estaba apagada o floja, ya lo arreglé'],
                        ['value' => 'estaba_bien', 'label' => 'Todo estaba bien conectado'],
                        ['value' => 'no_alcanzo', 'label' => 'No alcanzo a ver la regleta'],
                    ],
                    'next' => ['arreglado' => 'final_check', 'estaba_bien' => 'END_ESCALATE', 'no_alcanzo' => 'END_ESCALATE'],
                ],
                [
                    'key' => 'reiniciar',
                    'text' => 'Mantén presionado el botón de encendido 10 segundos hasta que se apague. Espera 5 segundos y vuelve a prenderla.',
                    'help' => 'Es la forma de apagarla cuando se quedó trabada. No pierdes nada que ya esté guardado.',
                    'component' => 'computer_power',
                    'options' => [
                        ['value' => 'reinicie', 'label' => 'Ya la reinicié'],
                        ['value' => 'no_apaga', 'label' => 'No se apaga'],
                    ],
                    'next' => ['reinicie' => 'final_check', 'no_apaga' => 'END_ESCALATE'],
                ],
                $this->finalCheck('¿Ya prende la computadora?'),
            ]],

            'PROJECTOR' => ['El proyector no prende', [
                [
                    'key' => 'luz_proyector',
                    'text' => 'Mira la luz del proyector. ¿De qué color está?',
                    'help' => 'Rojo o naranja quiere decir que está enchufado pero apagado. Verde o azul, que ya está prendido.',
                    'component' => 'projector_leds',
                    'options' => [
                        ['value' => 'sin_luz', 'label' => 'No tiene ninguna luz'],
                        ['value' => 'roja', 'label' => 'Roja o naranja'],
                        ['value' => 'verde', 'label' => 'Verde o azul'],
                    ],
                    'next' => ['sin_luz' => 'corriente_proyector', 'roja' => 'prender_control', 'verde' => 'final_check'],
                ],
                [
                    'key' => 'corriente_proyector',
                    'text' => 'Revisa la llave de la pared del proyector y que su cable de corriente esté bien enchufado.',
                    'help' => 'Muchos proyectores tienen su propia llave junto al interruptor de la luz del aula.',
                    'component' => 'projector_power',
                    'options' => [
                        ['value' => 'arreglado', 'label' => 'Estaba apagada, ya la prendí'],
                        ['value' => 'estaba_bien', 'label' => 'Ya estaba prendida'],
                        ['value' => 'no_encuentro', 'label' => 'No encuentro la llave'],
                    ],
                    'next' => ['arreglado' => 'final_check', 'estaba_bien' => 'END_ESCALATE', 'no_encuentro' => 'END_ESCALATE'],
                ],
                [
                    'key' => 'prender_control',
                    'text' => 'Apunta el control al proyector y presiona el botón de encendido. Espera 30 segundos: tarda en calentar.',
                    'help' => 'Si el control no responde, vuelve atrás y reporta «El control del proyector no responde».',
                    'component' => 'projector_remote',
                    'options' => [
                        ['value' => 'prendio', 'label' => 'Ya prendió'],
                        ['value' => 'no_responde', 'label' => 'No pasa nada'],
                    ],
                    'next' => ['prendio' => 'final_check', 'no_responde' => 'END_ESCALATE'],
                ],
                $this->finalCheck('¿Ya prende el proyector?'),
            ]],

            'HDMI' => ['No se ve la imagen en el proyector', [
                [
                    'key' => 'que_muestra',
                    'text' => '¿Qué se ve en la pared?',
                    'help' => null,
                    'component' => 'projector_no_signal',
                    'options' => [
                        ['value' => 'sin_senal', 'label' => 'Dice «Sin señal» o «No signal»'],
                        ['value' => 'azul_negro', 'label' => 'Una pantalla azul o negra'],
                        ['value' => 'nada', 'label' => 'Nada, el proyector está apagado'],
                    ],
                    'next' => ['sin_senal' => 'reconectar_hdmi', 'azul_negro' => 'reconectar_hdmi', 'nada' => 'END_ESCALATE'],
                ],
                [
                    'key' => 'reconectar_hdmi',
                    'text' => 'Desconecta el cable HDMI de la computadora y vuélvelo a conectar, empujándolo hasta el fondo.',
                    'help' => 'El HDMI es el conector plano y ancho. Suele soltarse solo un milímetro y con eso ya deja de dar imagen.',
                    'component' => 'hdmi_port',
                    'options' => [
                        ['value' => 'reconecte', 'label' => 'Ya lo reconecté'],
                        ['value' => 'no_encuentro', 'label' => 'No encuentro el cable'],
                    ],
                    'next' => ['reconecte' => 'duplicar_pantalla', 'no_encuentro' => 'END_ESCALATE'],
                ],
                [
                    'key' => 'duplicar_pantalla',
                    'text' => 'En el teclado, mantén presionada la tecla de Windows y presiona la letra P. Elige «Duplicar».',
                    'help' => 'La tecla de Windows es la que tiene el logo, abajo a la izquierda, entre Ctrl y Alt.',
                    'component' => 'win_p',
                    'options' => [
                        ['value' => 'lo_hice', 'label' => 'Ya lo hice'],
                        ['value' => 'no_aparece', 'label' => 'No aparece ese menú'],
                    ],
                    'next' => ['lo_hice' => 'final_check', 'no_aparece' => 'END_ESCALATE'],
                ],
                $this->finalCheck('¿Ya se ve la imagen en la pared?'),
            ]],

            'PROJECTOR_REMOTE' => ['El control del proyector no responde', [
                [
                    'key' => 'apuntar',
                    'text' => 'Apunta el control directamente al proyector, sin nada en medio, y presiona un botón.',
                    'help' => 'El sensor está en la parte delantera del proyector. Si hay una mochila o el ecran en medio, no llega la señal.',
                    'component' => 'projector_remote',
                    'options' => [
                        ['value' => 'responde', 'label' => 'Así sí responde'],
                        ['value' => 'nada', 'label' => 'Sigue sin responder'],
                    ],
                    'next' => ['responde' => 'final_check', 'nada' => 'pilas'],
                ],
                [
                    'key' => 'pilas',
                    'text' => 'Abre la tapa de atrás del control y cambia las pilas.',
                    'help' => 'Es la causa más común. Si no tienes pilas a la mano, dínoslo y te llevamos unas.',
                    'component' => 'remote_batteries',
                    'options' => [
                        ['value' => 'cambie', 'label' => 'Ya las cambié'],
                        ['value' => 'sin_pilas', 'label' => 'No tengo pilas a la mano'],
                    ],
                    'next' => ['cambie' => 'final_check', 'sin_pilas' => 'END_ESCALATE'],
                ],
                $this->finalCheck('¿Ya responde el control?'),
            ]],

            'SCREEN' => ['El ecran no baja o no sube', [
                [
                    'key' => 'interruptor',
                    'text' => 'Busca el interruptor del ecran en la pared, junto a la pizarra, y mantenlo presionado.',
                    'help' => 'Hay que mantenerlo, no darle un toque: el ecran baja mientras lo tienes presionado.',
                    'component' => 'screen_switch',
                    'options' => [
                        ['value' => 'funciona', 'label' => 'Así sí se mueve'],
                        ['value' => 'no_responde', 'label' => 'Lo presiono y no pasa nada'],
                        ['value' => 'no_encuentro', 'label' => 'No encuentro el interruptor'],
                    ],
                    'next' => ['funciona' => 'final_check', 'no_responde' => 'obstaculo', 'no_encuentro' => 'END_ESCALATE'],
                ],
                [
                    'key' => 'obstaculo',
                    'text' => 'Revisa que no haya nada trabando el ecran: una pizarra, un mueble o un cable.',
                    'help' => 'El motor se detiene solo cuando encuentra resistencia, para no romperse.',
                    'component' => 'screen',
                    'options' => [
                        ['value' => 'quite', 'label' => 'Había algo, ya lo quité'],
                        ['value' => 'nada', 'label' => 'No hay nada trabándolo'],
                    ],
                    'next' => ['quite' => 'final_check', 'nada' => 'END_ESCALATE'],
                ],
                $this->finalCheck('¿Ya se mueve el ecran?'),
            ]],

            'SPEAKER' => ['No se escucha el audio', [
                [
                    'key' => 'volumen',
                    'text' => 'Mira el ícono de la bocina, abajo a la derecha de la pantalla. ¿Tiene una X?',
                    'help' => 'Si tiene una X, el sonido está silenciado. Haz clic en el ícono y sube la barra.',
                    'component' => 'volume_icon',
                    'options' => [
                        ['value' => 'silenciado', 'label' => 'Estaba silenciado, ya lo activé'],
                        ['value' => 'ok', 'label' => 'El volumen está arriba'],
                        ['value' => 'no_encuentro', 'label' => 'No encuentro el ícono'],
                    ],
                    'next' => ['silenciado' => 'final_check', 'ok' => 'parlantes', 'no_encuentro' => 'parlantes'],
                ],
                [
                    'key' => 'parlantes',
                    'text' => '¿Los parlantes están encendidos? Súbeles el volumen con la perilla.',
                    'help' => 'Suelen tener una luz pequeña. La perilla también sirve de interruptor: gírala hasta que haga clic.',
                    'component' => 'speaker',
                    'options' => [
                        ['value' => 'prendi', 'label' => 'Estaban apagados, ya los prendí'],
                        ['value' => 'ya_estaban', 'label' => 'Ya estaban encendidos'],
                    ],
                    'next' => ['prendi' => 'final_check', 'ya_estaban' => 'cable_audio'],
                ],
                [
                    'key' => 'cable_audio',
                    'text' => 'Revisa que el cable verde del audio esté bien conectado en la computadora.',
                    'help' => 'Es un cable delgado con la punta verde. Va en el hueco verde de la computadora.',
                    'component' => 'audio_jack',
                    'options' => [
                        ['value' => 'reconecte', 'label' => 'Lo reconecté'],
                        ['value' => 'estaba_bien', 'label' => 'Ya estaba bien conectado'],
                    ],
                    'next' => ['reconecte' => 'final_check', 'estaba_bien' => 'END_ESCALATE'],
                ],
                $this->finalCheck('¿Ya se escucha?'),
            ]],

            'MOUSE' => ['El mouse no se mueve', [
                [
                    'key' => 'conexion_mouse',
                    'text' => 'Revisa que el cable del mouse, o su receptor USB, esté bien conectado a la computadora.',
                    'help' => 'El receptor es una pieza pequeña, del tamaño de una uña. Sácalo y vuelve a ponerlo.',
                    'component' => 'usb_port',
                    'options' => [
                        ['value' => 'reconecte', 'label' => 'Lo reconecté'],
                        ['value' => 'estaba_bien', 'label' => 'Ya estaba bien conectado'],
                    ],
                    'next' => ['reconecte' => 'final_check', 'estaba_bien' => 'pilas_mouse'],
                ],
                [
                    'key' => 'pilas_mouse',
                    'text' => '¿El mouse es inalámbrico? Si lo es, ábrelo por abajo y revisa las pilas.',
                    'help' => 'Si tiene cable, pasa a la siguiente pregunta.',
                    'component' => 'mouse_batteries',
                    'options' => [
                        ['value' => 'cambie', 'label' => 'Cambié las pilas'],
                        ['value' => 'tiene_cable', 'label' => 'Tiene cable'],
                    ],
                    'next' => ['cambie' => 'final_check', 'tiene_cable' => 'superficie'],
                ],
                [
                    'key' => 'superficie',
                    'text' => 'Muévelo sobre una hoja de papel o sobre el escritorio, no sobre vidrio ni sobre algo brillante.',
                    'help' => 'El sensor del mouse no lee bien sobre superficies transparentes o muy brillantes.',
                    'component' => null,
                    'options' => [
                        ['value' => 'funciona', 'label' => 'Así sí se mueve'],
                        ['value' => 'igual', 'label' => 'Sigue igual'],
                    ],
                    'next' => ['funciona' => 'final_check', 'igual' => 'END_ESCALATE'],
                ],
                $this->finalCheck('¿Ya se mueve el mouse?'),
            ]],

            'KEYBOARD' => ['El teclado no escribe', [
                [
                    'key' => 'conexion_teclado',
                    'text' => 'Revisa que el cable del teclado, o su receptor USB, esté bien conectado.',
                    'help' => 'Sácalo y vuelve a ponerlo en el mismo hueco, empujándolo hasta el fondo.',
                    'component' => 'usb_port',
                    'options' => [
                        ['value' => 'reconecte', 'label' => 'Lo reconecté'],
                        ['value' => 'estaba_bien', 'label' => 'Ya estaba bien conectado'],
                    ],
                    'next' => ['reconecte' => 'final_check', 'estaba_bien' => 'luces_teclado'],
                ],
                [
                    'key' => 'luces_teclado',
                    'text' => 'Presiona la tecla «Bloq Num» o «Num Lock». ¿Se prende alguna luz en el teclado?',
                    'help' => 'Si se prende, el teclado sí está conectado y el problema es otro.',
                    'component' => 'keyboard_leds',
                    'options' => [
                        ['value' => 'prende', 'label' => 'Sí, se prende una luz'],
                        ['value' => 'no_prende', 'label' => 'No se prende nada'],
                    ],
                    'next' => ['prende' => 'END_ESCALATE', 'no_prende' => 'pilas_teclado'],
                ],
                [
                    'key' => 'pilas_teclado',
                    'text' => '¿El teclado es inalámbrico? Si lo es, revisa sus pilas.',
                    'help' => 'Si tiene cable y aun así no prende ninguna luz, avísanos: hay que revisarlo.',
                    'component' => null,
                    'options' => [
                        ['value' => 'cambie', 'label' => 'Cambié las pilas'],
                        ['value' => 'tiene_cable', 'label' => 'Tiene cable'],
                    ],
                    'next' => ['cambie' => 'final_check', 'tiene_cable' => 'END_ESCALATE'],
                ],
                $this->finalCheck('¿Ya escribe el teclado?'),
            ]],
        ];
    }

    /**
     * Crea el arbol, sus pasos terminales y lo publica.
     *
     * @param  list<array<string, mixed>>  $steps
     */
    private function publish(string $categoryCode, string $name, array $steps): void
    {
        $category = IncidentCategory::where('code', $categoryCode)->first();

        if ($category === null) {
            return;
        }

        $flow = DiagnosticFlow::updateOrCreate(
            ['category_id' => $category->id],
            ['name' => $name, 'is_active' => true],
        );

        // Version nueva en cada siembra en lugar de editar la anterior: las
        // incidencias ya registradas siguen apuntando a la version que
        // ejecutaron de verdad, y sin eso el analisis compararia respuestas
        // dadas a preguntas distintas.
        $nextVersion = ((int) $flow->versions()->max('version')) + 1;

        $version = DiagnosticFlowVersion::create([
            'flow_id' => $flow->id,
            'version' => $nextVersion,
            'published_at' => now(),
        ]);

        $media = MediaAsset::pluck('id', 'scope_key');
        $order = 0;

        foreach ($steps as $step) {
            $created = DiagnosticStep::create([
                'flow_version_id' => $version->id,
                'step_key' => $step['key'],
                'sort_order' => $order++,
                'prompt_text' => $step['text'],
                'help_text' => $step['help'],
                'answer_options' => $step['options'],
                'next_step_map' => $step['next'],
                'is_terminal' => false,
                'component_key' => $step['component'],
            ]);

            // La imagen se engancha si ya existe en el banco. Mientras no
            // esten las fotos reales, el paso funciona igual: es preferible
            // un paso sin foto que un paso que no existe.
            if ($step['component'] !== null && isset($media[$step['component']])) {
                $created->media()->attach($media[$step['component']], [
                    'role' => 'primary',
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Marcadores de desenlace. No son pantallas: el motor los resuelve
        // al llegar, sin pedirle al docente un toque de mas.
        foreach ([['END_RESOLVED', 'resolved'], ['END_ESCALATE', 'escalate']] as [$key, $outcome]) {
            DiagnosticStep::create([
                'flow_version_id' => $version->id,
                'step_key' => $key,
                'sort_order' => $order++,
                'prompt_text' => $outcome === 'resolved' ? 'Problema resuelto' : 'Se requiere soporte',
                'answer_options' => [],
                'is_terminal' => true,
                'terminal_outcome' => $outcome,
            ]);
        }
    }
}
