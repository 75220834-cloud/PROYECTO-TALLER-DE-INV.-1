<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Diagnostics\Models\DiagnosticFlow;
use App\Modules\Diagnostics\Models\DiagnosticFlowVersion;
use App\Modules\Diagnostics\Models\DiagnosticStep;
use App\Modules\Incidents\Models\IncidentCategory;
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
 * CUATRO O CINCO SOLUCIONES, CADA UNA CON SALIDA INMEDIATA
 *
 * La version anterior tenia dos o tres pasos y escalaba en cuanto algo
 * «estaba bien»: si la regleta estaba prendida, mandaba a soporte sin probar
 * nada mas. Ahora cada problema ofrece cuatro o cinco acciones reales,
 * ordenadas de la mas probable a la menos, y CADA paso deja terminar con
 * «Listo, ya funciona». Un recorrido largo solo lo hace quien de verdad
 * tiene un problema dificil; el resto sale en el primer o segundo paso.
 *
 * Donde la respuesta depende del equipo —un mouse con cable o inalambrico,
 * un ecran con interruptor o manual— se pregunta primero. El checklist no
 * lo registra, y suponerlo daria instrucciones para un equipo que no esta.
 *
 * HASTA DONDE LLEGA LA MANO DEL DOCENTE
 *
 * Puede prender y apagar equipos, usar el control, reconectar cables sueltos
 * (HDMI, USB, audio, corriente), mover regletas, cambiar de tomacorriente,
 * abrir la tapa de pilas de un control o de un mouse, y usar el teclado y la
 * configuracion normal de Windows. NO puede abrir ningun equipo, subirse a
 * sillas o mesas, ni tocar tableros electricos. Ningun paso lo cruza, y los
 * que podrian tentar a hacerlo lo dicen expresamente.
 *
 * TODA RAMA TERMINA
 *
 * En «resuelto» o en «llamar a soporte». Un paso sin salida deja al docente
 * atrapado mirando una pantalla, y esa es la peor experiencia posible: peor
 * que no haber ofrecido diagnostico.
 *
 * LAS IMAGENES NO SE ENGANCHAN AQUI
 *
 * Cada paso declara la clave de su componente y la imagen se busca al
 * mostrarlo. Asi, subir la foto real al banco la pone en todos los pasos que
 * la usan, sin volver a sembrar.
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
     * Una accion que el docente intenta.
     *
     * «Listo, ya funciona» termina en cualquier paso. «Lo hice y sigue
     * igual» pasa a la siguiente solucion. La tercera opcion, cuando hay, es
     * para quien no puede hacerla —no encuentra la pieza, no la alcanza—: en
     * lugar de mandarlo a soporte, se le deja seguir probando lo demas.
     *
     * @return array<string, mixed>
     */
    private function accion(
        string $key,
        string $texto,
        ?string $ayuda,
        ?string $componente,
        string $siguiente,
        ?string $noPuedo = null,
    ): array {
        $opciones = [
            ['value' => 'funciona', 'label' => 'Listo, ya funciona'],
            ['value' => 'sigue', 'label' => 'Lo hice y sigue igual'],
        ];
        $mapa = ['funciona' => 'END_RESOLVED', 'sigue' => $siguiente];

        if ($noPuedo !== null) {
            $opciones[] = ['value' => 'no_puedo', 'label' => $noPuedo];
            $mapa['no_puedo'] = $siguiente;
        }

        return [
            'key' => $key,
            'text' => $texto,
            'help' => $ayuda,
            'component' => $componente,
            'options' => $opciones,
            'next' => $mapa,
        ];
    }

    /**
     * Una pregunta que decide por donde seguir.
     *
     * @param  array<string, array{0: string, 1: string}>  $opciones  valor => [texto, siguiente]
     * @return array<string, mixed>
     */
    private function pregunta(string $key, string $texto, ?string $ayuda, ?string $componente, array $opciones): array
    {
        return [
            'key' => $key,
            'text' => $texto,
            'help' => $ayuda,
            'component' => $componente,
            'options' => array_map(
                fn (string $valor, array $o): array => ['value' => $valor, 'label' => $o[0]],
                array_keys($opciones),
                $opciones,
            ),
            'next' => array_map(fn (array $o): string => $o[1], $opciones),
        ];
    }

    /**
     * @return array<string, array{0: string, 1: list<array<string, mixed>>}>
     */
    private function flows(): array
    {
        $fin = 'END_ESCALATE';

        return [
            /* ---------------------------------------------------------- */
            'COMPUTER' => ['La computadora no prende o no responde', [
                $this->pregunta('estado',
                    '¿Cómo está la computadora?',
                    'En las aulas hay computadoras «todo en uno» (la pantalla y la computadora son una sola pieza), de torre y cajitas pequeñas debajo del escritorio.',
                    'computer',
                    [
                        'apagada' => ['Está apagada: no tiene ninguna luz', 'boton'],
                        'negra' => ['Tiene luz, pero la pantalla está negra', 'despertar'],
                        'trabada' => ['Prendió, pero está trabada o no responde', 'esperar'],
                    ]),

                // Sin ninguna luz: falta corriente.
                $this->accion('boton',
                    'Presiona una vez el botón de encendido y espera 30 segundos.',
                    'En las «todo en uno» suele estar en el borde de la pantalla, abajo o detrás a la derecha. En las de torre, al frente de la caja. Tiene el símbolo de un círculo con una raya.',
                    'computer_power_button', 'regleta', 'No encuentro el botón'),
                $this->accion('regleta',
                    'Revisa la regleta donde está enchufada: su interruptor tiene que estar prendido.',
                    'La regleta es el enchufe de varias tomas, normalmente bajo el escritorio. Su interruptor se ilumina cuando está prendido. Después de prenderlo, vuelve a presionar el botón de la computadora.',
                    'power_strip', 'cable_poder', 'No encuentro la regleta'),
                $this->accion('cable_poder',
                    'Empuja hasta el fondo el cable de corriente, en la computadora y en la regleta.',
                    'En las «todo en uno» el cable entra por detrás de la pantalla. No hace falta desenchufarlo: basta con empujarlo para que no quede flojo.',
                    'computer_power_cable', 'otro_enchufe'),
                $this->accion('otro_enchufe',
                    'Si hay otro tomacorriente libre cerca, enchufa ahí la regleta o la computadora y vuelve a presionar el botón.',
                    'Así se sabe si el problema era el enchufe. Si no hay otro libre al alcance, pasa al siguiente.',
                    'power_strip', 'descarga', 'No hay otro enchufe cerca'),
                $this->accion('descarga',
                    'Mantén presionado el botón de encendido 15 segundos, suéltalo, espera 10 segundos y presiónalo una vez.',
                    'Esto descarga la energía que queda acumulada: a veces una computadora no arranca hasta hacerlo.',
                    'computer_power_button', $fin),

                // Con luz pero pantalla negra.
                $this->accion('despertar',
                    'Mueve el mouse y presiona una tecla. Espera 10 segundos.',
                    'La pantalla se apaga sola cuando nadie la usa por un rato. Al mover el mouse, despierta.',
                    'mouse', 'monitor'),
                $this->accion('monitor',
                    'Si la pantalla es un monitor aparte de la caja, revisa que esté prendido: su botón está abajo o al costado del monitor.',
                    'En las «todo en uno» la pantalla y la computadora son una sola pieza: si es tu caso, pasa al siguiente.',
                    'tower_pc', 'cable_monitor', 'Es una «todo en uno»'),
                $this->accion('cable_monitor',
                    'Si el monitor es aparte, empuja hasta el fondo su cable de video en la caja de la computadora y en el monitor.',
                    'Es el cable que une la pantalla con la caja. Si es una «todo en uno», pasa al siguiente.',
                    'tower_pc', 'reinicio_negra', 'Es una «todo en uno»'),
                $this->accion('reinicio_negra',
                    'Mantén presionado el botón de encendido 10 segundos hasta que se apague. Espera 5 segundos y vuelve a prenderla.',
                    'Es la forma de apagarla cuando se quedó trabada. Se pierde lo que no estaba guardado.',
                    'computer_power_button', $fin),

                // Prendida pero trabada.
                $this->accion('esperar',
                    'Espera un minuto sin tocar nada.',
                    'Puede estar instalando actualizaciones o abriendo un programa pesado. Si en la pantalla dice que está actualizando, no la apagues: espera a que termine.',
                    'computer', 'cerrar_programa'),
                $this->accion('cerrar_programa',
                    'Presiona a la vez Ctrl + Alt + Supr, entra a «Administrador de tareas», elige el programa que no responde y pulsa «Finalizar tarea».',
                    'Ctrl y Alt están abajo a la izquierda del teclado; Supr, arriba a la derecha (a veces dice «Del»). Se cierra solo ese programa.',
                    'task_manager', 'perifericos', 'No me deja o no aparece'),
                $this->accion('perifericos',
                    'Revisa que el mouse y el teclado estén bien conectados a la computadora.',
                    'A veces la computadora sí funciona pero no recibe lo que haces: un cable o un receptor USB flojo basta para eso.',
                    'usb_ports', 'reinicio_trabada'),
                $this->accion('reinicio_trabada',
                    'Mantén presionado el botón de encendido 10 segundos hasta que se apague. Espera 5 segundos y vuelve a prenderla.',
                    'Es la forma de apagarla cuando se quedó trabada. Se pierde lo que no estaba guardado.',
                    'computer_power_button', $fin),
            ]],

            /* ---------------------------------------------------------- */
            'PROJECTOR' => ['El proyector no prende', [
                $this->pregunta('luz_proyector',
                    'Mira la luz del proyector. ¿Cómo está?',
                    'Es una luz pequeña en el frente o encima del proyector.',
                    'projector_leds',
                    [
                        'sin_luz' => ['No tiene ninguna luz', 'interruptor_pared'],
                        'roja' => ['Roja o naranja, quieta', 'control_encender'],
                        'parpadea' => ['Parpadea', 'enfriar'],
                        'verde' => ['Verde o azul, pero no se ve nada en la pared', 'blank'],
                    ]),

                // Sin ninguna luz: no le llega corriente.
                $this->accion('interruptor_pared',
                    'Busca el interruptor o la llave del proyector en la pared y préndelo.',
                    'Muchos proyectores tienen su propio interruptor junto al de la luz del aula. Después de prenderlo, espera unos segundos a que aparezca la luz.',
                    'projector', 'regleta_proyector', 'No encuentro el interruptor'),
                $this->accion('regleta_proyector',
                    'Si el proyector está enchufado a una regleta que alcanzas desde el piso, revisa que su interruptor esté prendido.',
                    'No te subas a sillas ni mesas para revisarlo: si la regleta está en el techo o no la alcanzas, pasa al siguiente.',
                    'power_strip', 'control_tras_luz', 'No la alcanzo desde el piso'),
                $this->accion('control_tras_luz',
                    'Apunta el control al proyector y presiona una vez el botón de encendido.',
                    'Si ya apareció una luz roja o naranja, el proyector tiene corriente y solo falta encenderlo.',
                    'projector_remote', 'enfriar_tras_luz'),
                $this->accion('enfriar_tras_luz',
                    'Espera dos minutos y vuelve a presionar el botón de encendido del control.',
                    'Si alguien lo apagó hace poco, no vuelve a prender hasta enfriarse.',
                    'projector_leds', $fin),

                // Luz roja o naranja: tiene corriente, esta apagado.
                $this->accion('control_encender',
                    'Apunta el control al frente del proyector y presiona una vez el botón de encendido. Espera 30 segundos.',
                    'Tarda en calentar: la imagen no aparece enseguida. Si pide confirmar, presiona el botón otra vez.',
                    'projector_remote', 'boton_proyector'),
                $this->accion('boton_proyector',
                    'Presiona el botón de encendido del propio proyector, si lo alcanzas desde el piso.',
                    'No te subas a sillas ni mesas: si no lo alcanzas, pasa al siguiente.',
                    'projector', 'pilas_control', 'No lo alcanzo desde el piso'),
                $this->accion('pilas_control',
                    'Abre la tapa de atrás del control, saca las pilas y vuelve a ponerlas respetando el + y el −. Intenta prenderlo otra vez.',
                    'A veces las pilas se mueven y pierden contacto. Si ves polvo blanco o pilas hinchadas, no las toques y pasa al siguiente.',
                    'remote_batteries', 'enfriar_rojo'),
                $this->accion('enfriar_rojo',
                    'Espera dos minutos y vuelve a intentarlo.',
                    'Si alguien lo apagó hace poco, no vuelve a prender hasta enfriarse.',
                    'projector_leds', $fin),

                // Parpadea: esta calentando o enfriando.
                $this->accion('enfriar',
                    'Espera dos minutos sin tocar nada.',
                    'Una luz que parpadea quiere decir que el proyector se está calentando o enfriando. Es normal.',
                    'projector_leds', 'control_tras_espera'),
                $this->accion('control_tras_espera',
                    'Cuando la luz quede quieta, apunta el control al proyector y presiona una vez el botón de encendido.',
                    'Espera 30 segundos a que aparezca la imagen.',
                    'projector_remote', 'pilas_tras_espera'),
                $this->accion('pilas_tras_espera',
                    'Abre la tapa de atrás del control, saca las pilas y vuelve a ponerlas respetando el + y el −. Intenta prenderlo otra vez.',
                    'Si ves polvo blanco o pilas hinchadas, no las toques y pasa al siguiente.',
                    'remote_batteries', 'boton_tras_espera'),
                $this->accion('boton_tras_espera',
                    'Presiona el botón de encendido del propio proyector, si lo alcanzas desde el piso.',
                    'No te subas a sillas ni mesas: si no lo alcanzas, pasa al siguiente.',
                    'projector', $fin, 'No lo alcanzo desde el piso'),

                // Prendido pero sin imagen: es tema de senal.
                $this->accion('blank',
                    'En el control, presiona el botón «Blank» o «AV Mute».',
                    'Ese botón apaga la imagen sin apagar el proyector. Si alguien lo presionó, la pared queda negra aunque todo funcione.',
                    'remote_buttons', 'source_proyector', 'El control no tiene ese botón'),
                $this->accion('source_proyector',
                    'Presiona «Source» o «Input» en el control hasta que aparezca la imagen de la computadora.',
                    'Cada vez que lo presionas cambia de entrada. Espera unos segundos en cada una.',
                    'remote_buttons', 'hdmi_proyector'),
                $this->accion('hdmi_proyector',
                    'Desconecta el cable HDMI de la computadora y vuelve a conectarlo, empujándolo hasta el fondo.',
                    'El conector HDMI es plano, más ancho arriba que abajo. Basta que se suelte un milímetro para perder la imagen.',
                    'hdmi_port', 'win_p_proyector'),
                $this->accion('win_p_proyector',
                    'En el teclado, presiona a la vez la tecla de Windows y la P, y elige «Duplicar».',
                    'La tecla de Windows tiene el logo de las cuatro ventanas, abajo a la izquierda.',
                    'win_p', $fin),
            ]],

            /* ---------------------------------------------------------- */
            'HDMI' => ['No se ve la imagen en el proyector', [
                $this->pregunta('que_ves',
                    '¿Qué se ve en la pared o en el ecran?',
                    null,
                    'projector_no_signal',
                    [
                        'sin_senal' => ['Dice «Sin señal», «No signal» o algo parecido', 'win_p'],
                        'azul_negro' => ['Una pantalla azul o negra, sin mensaje', 'win_p'],
                        'apagado' => ['Nada: el proyector está apagado', 'prender_primero'],
                        'deforme' => ['Se ve, pero cortada, borrosa o con colores raros', 'duplicar_deforme'],
                    ]),

                $this->accion('prender_primero',
                    'Primero hay que prender el proyector: apunta el control al frente y presiona una vez el botón de encendido. Espera 30 segundos.',
                    'Si con el control no prende, vuelve atrás y reporta «El proyector no prende»: ahí está el procedimiento completo.',
                    'projector_remote', 'reconectar_hdmi'),

                // Sin senal o pantalla vacia.
                $this->accion('win_p',
                    'En el teclado, presiona a la vez la tecla de Windows y la P, y elige «Duplicar».',
                    'Windows a veces manda la imagen solo a la pantalla de la computadora. «Duplicar» la manda a las dos. La tecla de Windows tiene el logo de las cuatro ventanas, abajo a la izquierda.',
                    'win_p', 'reconectar_hdmi', 'No aparece ese menú'),
                $this->accion('reconectar_hdmi',
                    'Desconecta el cable HDMI de la computadora y vuelve a conectarlo, empujándolo hasta el fondo.',
                    'El conector HDMI es plano, más ancho arriba que abajo. Basta que se suelte un milímetro para perder la imagen.',
                    'hdmi_port', 'entrada_correcta', 'No encuentro el cable'),
                $this->accion('entrada_correcta',
                    'Fíjate si junto a la entrada del cable dice «IN» u «OUT». El cable del proyector va en la que dice «OUT» (salida).',
                    'Algunas computadoras «todo en uno» tienen dos entradas HDMI iguales: una para recibir imagen y otra para enviarla. Si solo hay una o no dice nada, pasa al siguiente.',
                    'hdmi_port', 'source_hdmi', 'Solo hay una entrada'),
                $this->accion('source_hdmi',
                    'Con el control del proyector, presiona «Source» o «Input» hasta que aparezca la imagen.',
                    'Cada vez que lo presionas cambia de entrada. Espera unos segundos en cada una.',
                    'remote_buttons', 'reiniciar_con_cable'),
                $this->accion('reiniciar_con_cable',
                    'Con el cable ya conectado, reinicia la computadora: Inicio › Apagar › Reiniciar.',
                    'Al arrancar con el cable puesto, la computadora detecta el proyector desde el principio.',
                    'computer', $fin),

                // Se ve, pero mal.
                $this->accion('duplicar_deforme',
                    'En el teclado, presiona a la vez la tecla de Windows y la P, y elige «Duplicar».',
                    'Si la imagen sale cortada o estirada, suele ser porque la computadora la envía con otro tamaño.',
                    'win_p', 'auto_ajuste'),
                $this->accion('auto_ajuste',
                    'En el control del proyector, presiona el botón «Auto».',
                    'Ajusta solo el tamaño y la posición de la imagen. Tarda unos segundos.',
                    'remote_buttons', 'reconectar_deforme', 'El control no tiene ese botón'),
                $this->accion('reconectar_deforme',
                    'Desconecta el cable HDMI y vuelve a conectarlo, empujándolo hasta el fondo.',
                    'Los colores raros o la imagen que parpadea suelen deberse a un cable que no está bien firme.',
                    'hdmi_port', 'enfoque'),
                $this->accion('enfoque',
                    'Si la imagen se ve borrosa, busca en el control un botón «Focus» o «Enfoque» y ajústalo.',
                    'No intentes girar la lente del proyector si para alcanzarla tendrías que subirte a algo.',
                    'remote_buttons', $fin, 'El control no tiene ese botón'),
            ]],

            /* ---------------------------------------------------------- */
            'PROJECTOR_REMOTE' => ['El control del proyector no responde', [
                $this->accion('apuntar',
                    'Apunta el control al frente del proyector, sin nada en medio, y presiona una vez el botón de encendido.',
                    'El sensor está en el frente del proyector. Si apuntas al ecran o hay alguien en medio, la señal no llega.',
                    'projector_remote', 'acercarse'),
                $this->accion('acercarse',
                    'Párate justo debajo del proyector y vuelve a intentarlo.',
                    'Con pilas gastadas, el control solo funciona de cerca. Si así responde, avisa a soporte cuando puedas para que cambien las pilas.',
                    'projector_remote', 'pilas_contacto'),
                $this->accion('pilas_contacto',
                    'Abre la tapa de atrás del control, saca las pilas y vuelve a ponerlas respetando el + y el −.',
                    'A veces las pilas se mueven y pierden contacto. Si ves polvo blanco o pilas hinchadas, no las toques y pasa al siguiente.',
                    'remote_batteries', 'pilas_nuevas'),
                $this->accion('pilas_nuevas',
                    'Si tienes pilas nuevas del mismo tamaño, cámbialas.',
                    'El tamaño está escrito en la pila (AA o AAA). Si no tienes, pasa al siguiente.',
                    'remote_batteries', 'boton_directo', 'No tengo pilas'),
                $this->accion('boton_directo',
                    'Mientras tanto, presiona los botones del propio proyector, si lo alcanzas desde el piso.',
                    'Así puedes dar la clase igual. No te subas a sillas ni mesas: si no lo alcanzas, avísanos.',
                    'projector', $fin, 'No lo alcanzo desde el piso'),
            ]],

            /* ---------------------------------------------------------- */
            'SCREEN' => ['El ecran no baja o no sube', [
                $this->pregunta('tipo_ecran',
                    '¿Cómo se mueve el ecran de tu aula?',
                    'El ecran es la tela blanca donde se proyecta.',
                    'screen',
                    [
                        'interruptor' => ['Con un interruptor en la pared', 'mantener'],
                        'manual' => ['A mano, jalándolo del borde', 'jalar'],
                        'no_se' => ['No sé', 'mantener'],
                    ]),

                // Electrico.
                $this->accion('mantener',
                    'Busca el interruptor del ecran en la pared y mantén presionado el botón de bajar unos segundos.',
                    'En muchos, el ecran se mueve solo mientras mantienes presionado: un toque corto no basta. Suele estar cerca de la pizarra o de la puerta.',
                    'screen_switch', 'stop', 'No encuentro el interruptor'),
                $this->accion('stop',
                    'Si el interruptor tiene un botón del medio («Stop» o un cuadrado), presiónalo una vez. Después vuelve a presionar bajar.',
                    'Ese botón detiene el motor y lo deja listo para moverse otra vez. Si no tiene botón del medio, pasa al siguiente.',
                    'screen_switch', 'contrario', 'No tiene botón del medio'),
                $this->accion('contrario',
                    'Presiona unos segundos el botón contrario (subir), suéltalo y vuelve a presionar bajar.',
                    'Si el ecran llegó a su tope, a veces se bloquea en esa dirección hasta moverlo un poco al revés.',
                    'screen_switch', 'obstaculo'),
                $this->accion('obstaculo',
                    'Revisa que nada trabe el ecran: una pizarra corrida, un mueble, una cortina o un cable.',
                    'El motor se detiene solo cuando encuentra algo en el camino, para no romperse. Quita lo que esté en medio y vuelve a presionar bajar.',
                    'screen', $fin),

                // Manual.
                $this->accion('jalar',
                    'Toma el borde de abajo por el centro y jala hacia abajo, suave y parejo, hasta un poco más de donde quieres que quede. Luego suéltalo despacio.',
                    'Así se traba en esa posición. Si jalas de una esquina, se tuerce y no traba.',
                    'screen', 'destrabar'),
                $this->accion('destrabar',
                    'Si no se queda abajo o no quiere subir, jala un poquito hacia abajo y suéltalo despacio.',
                    'Ese movimiento libera el seguro. Para subirlo, acompáñalo con la mano: si lo sueltas de golpe, se enrolla con fuerza.',
                    'screen', 'obstaculo_manual'),
                $this->accion('obstaculo_manual',
                    'Revisa que nada trabe el ecran: una pizarra corrida, un mueble, una cortina o un cable.',
                    'No lo fuerces: si algo lo traba, quítalo y vuelve a intentar.',
                    'screen', 'proyectar_pared'),
                $this->accion('proyectar_pared',
                    'Mientras tanto, si hay una pared blanca o la pizarra, puedes proyectar ahí.',
                    'Así puedes dar la clase igual. Avisamos a soporte para que revise el ecran.',
                    null, $fin),
            ]],

            /* ---------------------------------------------------------- */
            'SPEAKER' => ['No se escucha el audio', [
                $this->accion('silencio',
                    'Mira el ícono del parlante, abajo a la derecha de la pantalla. Si tiene una X o una raya, haz clic en él y sube el volumen.',
                    'Una X junto al ícono quiere decir que el sonido está silenciado.',
                    'volume_icon', 'salida_audio'),
                $this->accion('salida_audio',
                    'Haz clic en el ícono del parlante y revisa a dónde sale el sonido: tienen que estar elegidos los parlantes del aula, no la pantalla del proyector ni unos audífonos.',
                    'Al conectar el proyector, Windows a veces manda el sonido al proyector, que no suena. Junto al volumen hay una flecha o un nombre de dispositivo: haz clic ahí para cambiarlo.',
                    'audio_output', 'parlantes_on', 'No sé cuál elegir'),
                $this->accion('parlantes_on',
                    'Revisa que los parlantes del aula estén encendidos y gira su perilla de volumen hacia la derecha.',
                    'Suelen tener una luz pequeña cuando están prendidos. La perilla a veces también es el interruptor: gírala hasta que haga clic.',
                    'speaker', 'cable_audio', 'No encuentro los parlantes'),
                $this->accion('cable_audio',
                    'Revisa que el cable de audio esté bien conectado en la computadora: la punta verde va en la entrada verde o en la que tiene el dibujo de unos audífonos.',
                    'Es un cable delgado con la punta redonda. Empújalo hasta el fondo.',
                    'audio_cable', 'volumen_programa'),
                $this->accion('volumen_programa',
                    'Revisa el volumen dentro del programa que usas: el video, la presentación o la página web.',
                    'YouTube, PowerPoint y los reproductores tienen su propio volumen: si está en cero, no se escucha aunque Windows esté al máximo.',
                    'volume_icon', $fin),
            ]],

            /* ---------------------------------------------------------- */
            'MOUSE' => ['El mouse no se mueve', [
                $this->pregunta('tipo_mouse',
                    '¿El mouse tiene cable?',
                    null,
                    'mouse',
                    [
                        'cable' => ['Sí, tiene cable', 'reconectar_mouse'],
                        'inalambrico' => ['No, es inalámbrico', 'interruptor_mouse'],
                    ]),

                // Con cable.
                $this->accion('reconectar_mouse',
                    'Desconecta el cable del mouse de la computadora y vuelve a conectarlo. Espera 10 segundos.',
                    'Windows tarda unos segundos en reconocerlo de nuevo.',
                    'usb_ports', 'otro_usb_mouse'),
                $this->accion('otro_usb_mouse',
                    'Conéctalo en otra entrada USB de la computadora.',
                    'Las entradas USB son rectangulares. En las «todo en uno» suelen estar al costado o detrás de la pantalla.',
                    'usb_ports', 'superficie'),
                $this->accion('superficie',
                    'Muévelo sobre una hoja de papel o sobre el escritorio, no sobre vidrio ni sobre algo brillante.',
                    'El sensor del mouse no lee bien las superficies transparentes o muy brillantes.',
                    'mouse', 'reiniciar_teclado'),
                $this->accion('reiniciar_teclado',
                    'Reinicia la computadora usando el teclado: presiona Ctrl + Alt + Supr, luego Tab hasta el botón de apagado de abajo a la derecha, Enter, y elige «Reiniciar».',
                    'Con Tab te mueves entre opciones y con las flechas y Enter eliges. Se pierde lo que no estaba guardado.',
                    'keyboard', $fin, 'No lo logro'),

                // Inalambrico.
                $this->accion('interruptor_mouse',
                    'Voltea el mouse y revisa su interruptor de abajo: tiene que estar en «ON».',
                    'Muchos inalámbricos tienen un interruptor pequeño debajo. Si estaba en «OFF», muévelo a «ON» y espera unos segundos.',
                    'mouse', 'receptor_mouse', 'No tiene interruptor'),
                $this->accion('receptor_mouse',
                    'Saca el receptor USB del mouse de la computadora y vuelve a ponerlo. Espera 10 segundos.',
                    'El receptor es una pieza pequeña, del tamaño de una uña, conectada en una entrada USB.',
                    'mouse', 'pilas_mouse', 'No encuentro el receptor'),
                $this->accion('pilas_mouse',
                    'Abre la tapa de abajo del mouse, saca las pilas y vuelve a ponerlas respetando el + y el −. Si tienes pilas nuevas, cámbialas.',
                    'Si ves polvo blanco o pilas hinchadas, no las toques y pasa al siguiente.',
                    'mouse', 'superficie_inalambrico'),
                $this->accion('superficie_inalambrico',
                    'Muévelo sobre una hoja de papel o sobre el escritorio, cerca de la computadora.',
                    'Lejos del receptor o sobre vidrio, el mouse inalámbrico deja de responder.',
                    'mouse', $fin),
            ]],

            /* ---------------------------------------------------------- */
            'KEYBOARD' => ['El teclado no escribe', [
                $this->pregunta('que_pasa_teclado',
                    '¿Qué le pasa al teclado?',
                    null,
                    'keyboard',
                    [
                        'nada' => ['No escribe nada', 'clic_cuadro'],
                        'algunas' => ['Escribe, pero algunas teclas no funcionan o salen otros signos', 'bloq_num'],
                    ]),

                // No escribe nada.
                $this->accion('clic_cuadro',
                    'Con el mouse, haz clic justo donde quieres escribir y vuelve a intentarlo.',
                    'Si no hay un lugar elegido para escribir, el teclado no muestra nada aunque funcione.',
                    'keyboard', 'reconectar_teclado'),
                $this->accion('reconectar_teclado',
                    'Desconecta el cable del teclado, o su receptor USB si es inalámbrico, y vuelve a conectarlo. Espera 10 segundos.',
                    'Windows tarda unos segundos en reconocerlo de nuevo.',
                    'usb_ports', 'otro_usb_teclado'),
                $this->accion('otro_usb_teclado',
                    'Conéctalo en otra entrada USB de la computadora.',
                    'Las entradas USB son rectangulares. En las «todo en uno» suelen estar al costado o detrás de la pantalla.',
                    'usb_ports', 'pilas_teclado'),
                $this->accion('pilas_teclado',
                    'Si es inalámbrico, revisa que su interruptor esté en «ON» y saca y vuelve a poner sus pilas.',
                    'El interruptor suele estar en el borde de arriba o debajo. Si el teclado tiene cable, pasa al siguiente.',
                    'keyboard', 'reiniciar_mouse', 'Tiene cable'),
                $this->accion('reiniciar_mouse',
                    'Reinicia la computadora con el mouse: Inicio › Apagar › Reiniciar.',
                    'Se pierde lo que no estaba guardado.',
                    'computer', $fin),

                // Algunas teclas.
                $this->accion('bloq_num',
                    'Si los números de la derecha no funcionan, presiona una vez la tecla «Bloq Num» o «Num Lock».',
                    'Esa tecla prende y apaga los números del lado derecho. Si no es tu caso, pasa al siguiente.',
                    'keyboard', 'bloq_mayus', 'No es mi caso'),
                $this->accion('bloq_mayus',
                    'Si todo sale en mayúsculas, presiona una vez la tecla «Bloq Mayús» o «Caps Lock».',
                    'Si no es tu caso, pasa al siguiente.',
                    'keyboard', 'idioma', 'No es mi caso'),
                $this->accion('idioma',
                    'Si la ñ, la @ o los signos salen cambiados, presiona a la vez la tecla de Windows y la barra espaciadora, y elige «ESP» o «Español».',
                    'Abajo a la derecha de la pantalla se ve el idioma activo del teclado: tiene que decir ESP.',
                    'keyboard_language', 'reconectar_teclas'),
                $this->accion('reconectar_teclas',
                    'Desconecta el teclado y vuelve a conectarlo. Espera 10 segundos.',
                    'Si sigue igual, puede ser una tecla dañada: hay que revisarlo.',
                    'usb_ports', $fin),
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

        // Si el arbol publicado ya es este, no se toca. Sin esta guarda,
        // cada `db:seed` publicaria una version nueva identica a la
        // anterior, y el historial diria que el procedimiento cambio
        // cuando no cambio nada.
        if ($this->isCurrent($flow, $steps)) {
            return;
        }

        // Version nueva cuando el contenido cambia, en lugar de editar la
        // anterior: las incidencias ya registradas siguen apuntando a la
        // version que ejecutaron de verdad, y sin eso el analisis
        // compararia respuestas dadas a preguntas distintas.
        $nextVersion = ((int) $flow->versions()->max('version')) + 1;

        $version = DiagnosticFlowVersion::create([
            'flow_id' => $flow->id,
            'version' => $nextVersion,
            'published_at' => now(),
        ]);

        $order = 0;

        foreach ($steps as $step) {
            DiagnosticStep::create([
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

    /**
     * El arbol publicado mas reciente coincide paso a paso con esta definicion.
     *
     * @param  list<array<string, mixed>>  $steps
     */
    private function isCurrent(DiagnosticFlow $flow, array $steps): bool
    {
        $latest = $flow->versions()->orderByDesc('version')->first();

        if ($latest === null) {
            return false;
        }

        $published = DiagnosticStep::query()
            ->where('flow_version_id', $latest->id)
            ->where('is_terminal', false)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DiagnosticStep $step): array => [
                'key' => $step->step_key,
                'text' => $step->prompt_text,
                'help' => $step->help_text,
                'options' => $step->answer_options,
                'next' => $step->next_step_map,
                'component' => $step->component_key,
            ])
            ->all();

        $wanted = array_map(fn (array $step): array => [
            'key' => $step['key'],
            'text' => $step['text'],
            'help' => $step['help'],
            'options' => $step['options'],
            'next' => $step['next'],
            'component' => $step['component'],
        ], $steps);

        // == y no ===: compara claves y valores sin exigir el mismo orden de
        // claves, que el JSON guardado en la base no garantiza.
        return $published == $wanted;
    }

    /**
     * Las claves de componente que usan los arboles. Las lee el cargador de
     * imagenes provisionales y la guia de fotos: una sola lista, sin copias.
     *
     * @return list<string>
     */
    public function componentKeys(): array
    {
        $keys = [];

        foreach ($this->flows() as [, $steps]) {
            foreach ($steps as $step) {
                if ($step['component'] !== null) {
                    $keys[] = $step['component'];
                }
            }
        }

        return array_values(array_unique($keys));
    }
}
