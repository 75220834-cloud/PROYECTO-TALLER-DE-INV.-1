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
 * Arboles de diagnostico DEMO.
 *
 * ADVERTENCIA IMPORTANTE (plan 17 y 69)
 * -------------------------------------
 * Estos procedimientos NO son procedimientos institucionales. Son una
 * secuencia razonable y generica —revisar corriente, cable, fuente de
 * entrada y duplicado de pantalla— construida para que el motor se pueda
 * probar de extremo a extremo desde el primer dia.
 *
 * Los procedimientos REALES los aportara soporte TI y los reemplazaran.
 * Mientras tanto van marcados como demo y el sistema lo muestra.
 *
 * Sobre la forma de los pasos: cada uno hace UNA sola pregunta con
 * respuestas cerradas, e incluye siempre la opcion "No estoy seguro". Esa
 * tercera opcion no es relleno: sin ella, un docente que duda se ve forzado
 * a mentir, y a partir de ahi el diagnostico va por un camino equivocado.
 */
class DemoDiagnosticsSeeder extends Seeder
{
    public function run(): void
    {
        $this->buildProjectorFlow();
        $this->buildAudioFlow();
    }

    private function buildProjectorFlow(): void
    {
        $category = IncidentCategory::where('code', 'PROJECTOR')->first();

        if ($category === null) {
            return;
        }

        $steps = [
            [
                'key' => 'projector_power',
                'text' => '¿El proyector tiene alguna luz encendida?',
                'help' => 'Mira el proyector: casi todos tienen una luz pequeña de color.',
                'component' => 'status_light',
                'options' => [
                    ['value' => 'green', 'label' => 'Sí, luz verde o azul'],
                    ['value' => 'orange', 'label' => 'Sí, luz naranja o roja'],
                    ['value' => 'none', 'label' => 'No, ninguna luz'],
                    ['value' => 'unsure', 'label' => 'No estoy seguro'],
                ],
                'next' => [
                    'green' => 'check_cable',
                    'orange' => 'turn_on',
                    'none' => 'check_power',
                    'unsure' => 'turn_on',
                ],
            ],
            [
                'key' => 'check_power',
                'text' => 'Revisa que el cable de corriente del proyector esté enchufado.',
                'help' => 'Si hay una regleta, comprueba que su interruptor esté encendido.',
                'component' => 'power_button',
                'options' => [
                    ['value' => 'fixed', 'label' => 'Ya está enchufado'],
                    ['value' => 'cannot', 'label' => 'No lo encuentro o no llego'],
                ],
                'next' => ['fixed' => 'turn_on', 'cannot' => 'END_ESCALATE'],
            ],
            [
                'key' => 'turn_on',
                'text' => 'Enciende el proyector con su botón de encendido.',
                'help' => 'Puede tardar hasta un minuto en dar imagen.',
                'component' => 'power_button',
                'options' => [
                    ['value' => 'on', 'label' => 'Ya encendió'],
                    ['value' => 'no_response', 'label' => 'No responde'],
                ],
                'next' => ['on' => 'check_cable', 'no_response' => 'END_ESCALATE'],
            ],
            [
                'key' => 'check_cable',
                'text' => '¿El cable HDMI está conectado en la computadora?',
                'help' => 'Es el cable con la punta en forma de trapecio.',
                'component' => 'hdmi_port',
                'options' => [
                    ['value' => 'yes', 'label' => 'Sí, está conectado'],
                    ['value' => 'no', 'label' => 'No, estaba suelto'],
                    ['value' => 'unsure', 'label' => 'No sé cuál es ese cable'],
                ],
                'next' => [
                    'yes' => 'check_source',
                    'no' => 'connect_cable',
                    'unsure' => 'connect_cable',
                ],
            ],
            [
                'key' => 'connect_cable',
                'text' => 'Conecta el cable HDMI en la computadora hasta que entre firme.',
                'help' => 'Solo entra en una posición. No lo fuerces.',
                'component' => 'hdmi_connector',
                'options' => [
                    ['value' => 'done', 'label' => 'Listo, ya lo conecté'],
                    ['value' => 'cannot', 'label' => 'No puedo conectarlo'],
                ],
                'next' => ['done' => 'check_source', 'cannot' => 'END_ESCALATE'],
            ],
            [
                'key' => 'check_source',
                'text' => 'En el control del proyector, presiona el botón "Source" o "Input".',
                'help' => 'Ve presionándolo hasta que aparezca la imagen de la computadora.',
                'component' => null,
                'options' => [
                    ['value' => 'done', 'label' => 'Ya lo hice'],
                    ['value' => 'no_remote', 'label' => 'No tengo el control'],
                ],
                'next' => ['done' => 'duplicate_screen', 'no_remote' => 'duplicate_screen'],
            ],
            [
                'key' => 'duplicate_screen',
                'text' => 'En el teclado, presiona la tecla Windows y la letra P al mismo tiempo, y elige "Duplicar".',
                'help' => 'La tecla Windows tiene el dibujo de una ventana, abajo a la izquierda.',
                'component' => null,
                'options' => [
                    ['value' => 'done', 'label' => 'Ya lo hice'],
                    ['value' => 'cannot', 'label' => 'No me aparece nada'],
                ],
                'next' => ['done' => 'final_check', 'cannot' => 'END_ESCALATE'],
            ],
            [
                'key' => 'final_check',
                'text' => '¿Ya se ve la imagen en la pantalla?',
                'help' => null,
                'component' => null,
                'options' => [
                    ['value' => 'yes', 'label' => 'Sí, ya se ve'],
                    ['value' => 'no', 'label' => 'No, sigue igual'],
                ],
                'next' => ['yes' => 'END_RESOLVED', 'no' => 'END_ESCALATE'],
            ],
        ];

        $this->publish($category->id, 'Proyector sin imagen (DEMO)', $steps);
    }

    private function buildAudioFlow(): void
    {
        $category = IncidentCategory::where('code', 'AUDIO')->first();

        if ($category === null) {
            return;
        }

        $steps = [
            [
                'key' => 'check_volume',
                'text' => 'Revisa el volumen de la computadora, abajo a la derecha de la pantalla.',
                'help' => 'Si el icono de la bocina tiene una X, el sonido está silenciado.',
                'component' => null,
                'options' => [
                    ['value' => 'muted', 'label' => 'Estaba silenciado, ya lo activé'],
                    ['value' => 'ok', 'label' => 'El volumen está bien'],
                    ['value' => 'unsure', 'label' => 'No lo encuentro'],
                ],
                'next' => ['muted' => 'final_check', 'ok' => 'check_speakers_power', 'unsure' => 'check_speakers_power'],
            ],
            [
                'key' => 'check_speakers_power',
                'text' => '¿Los parlantes están encendidos?',
                'help' => 'Suelen tener una luz pequeña y una perilla de volumen.',
                'component' => 'power_button',
                'options' => [
                    ['value' => 'yes', 'label' => 'Sí, están encendidos'],
                    ['value' => 'no', 'label' => 'No, ya los encendí'],
                    ['value' => 'unsure', 'label' => 'No estoy seguro'],
                ],
                'next' => ['yes' => 'check_audio_cable', 'no' => 'final_check', 'unsure' => 'check_audio_cable'],
            ],
            [
                'key' => 'check_audio_cable',
                'text' => 'Revisa que el cable de audio esté conectado en la computadora.',
                'help' => 'Es un cable delgado con anillos negros en la punta.',
                'component' => 'audio_jack',
                'options' => [
                    ['value' => 'reconnected', 'label' => 'Lo reconecté'],
                    ['value' => 'was_fine', 'label' => 'Ya estaba conectado'],
                    ['value' => 'unsure', 'label' => 'No sé cuál es'],
                ],
                'next' => ['reconnected' => 'final_check', 'was_fine' => 'END_ESCALATE', 'unsure' => 'END_ESCALATE'],
            ],
            [
                'key' => 'final_check',
                'text' => '¿Ya se escucha el sonido?',
                'help' => null,
                'component' => null,
                'options' => [
                    ['value' => 'yes', 'label' => 'Sí, ya se escucha'],
                    ['value' => 'no', 'label' => 'No, sigue sin sonido'],
                ],
                'next' => ['yes' => 'END_RESOLVED', 'no' => 'END_ESCALATE'],
            ],
        ];

        $this->publish($category->id, 'Sin audio en el aula (DEMO)', $steps);
    }

    /**
     * Crea el arbol, sus pasos terminales y lo publica.
     *
     * @param  list<array<string, mixed>>  $steps
     */
    private function publish(int $categoryId, string $name, array $steps): void
    {
        $flow = DiagnosticFlow::updateOrCreate(
            ['category_id' => $categoryId],
            ['name' => $name, 'is_active' => true],
        );

        // Version nueva en cada siembra en lugar de editar la anterior: es
        // exactamente lo que exige el versionado del plan. Las incidencias
        // ya registradas siguen apuntando a la version que ejecutaron.
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

        $this->command->info("  Árbol '{$name}': v{$nextVersion} publicada con ".count($steps).' pasos.');
    }
}
