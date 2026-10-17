<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Processors\ImageProcessor;
use Database\Seeders\PilotDiagnosticsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Carga (o quita) las ilustraciones provisionales del diagnostico.
 *
 * Mientras soporte no tome las fotos reales, los pasos se muestran solo con
 * texto. Estas ilustraciones permiten comprobar ya que las imagenes salen
 * donde deben, y orientan al docente mientras tanto. Cada una lleva escrito
 * que es provisional.
 *
 * NO HAY QUE QUITARLAS PARA PONER LAS FOTOS
 *
 * Una foto real subida con la misma clave gana siempre a la provisional, y
 * la provisional deja de verse. Quitarlas solo hace falta si se prefiere que
 * un paso sin foto real se muestre sin imagen.
 *
 * Se marcan como demostracion (is_demo): nunca se confunden con material
 * validado por soporte.
 */
final class LoadPlaceholderImages extends Command
{
    protected $signature = 'imagenes:provisionales
        {--quitar : Elimina las ilustraciones provisionales en lugar de cargarlas}';

    protected $description = 'Carga las ilustraciones provisionales del diagnóstico (o las quita con --quitar)';

    private const PREFIX = 'PROV-';

    /**
     * Texto alternativo de cada ilustracion: lo que leeria un lector de
     * pantalla y lo que se ve en el banco de imagenes.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const TEXTOS = [
        'computer' => ['Tipos de computadora', 'Dibujo de una computadora todo en uno, una de torre con monitor y una cajita pequeña.'],
        'computer_power_button' => ['Botón de encendido', 'Dibujo de una computadora todo en uno con el botón de encendido marcado en rojo.'],
        'computer_power_cable' => ['Cable de corriente de la computadora', 'Dibujo de la parte de atrás de una computadora con el cable de corriente marcado.'],
        'power_strip' => ['Regleta', 'Dibujo de una regleta de cuatro tomas con el interruptor marcado.'],
        'projector' => ['Proyector', 'Dibujo de un proyector colgado del techo con su botón marcado.'],
        'projector_leds' => ['Luces del proyector', 'Dibujo de las luces del proyector y lo que significa cada color.'],
        'projector_remote' => ['Control apuntando al proyector', 'Dibujo de un control remoto apuntando al frente del proyector.'],
        'remote_batteries' => ['Pilas del control', 'Dibujo de la tapa de pilas del control abierta, con el más y el menos señalados.'],
        'remote_buttons' => ['Botones del control', 'Dibujo de un control con los botones Source, Auto, Blank y Focus marcados.'],
        'hdmi_port' => ['Entrada HDMI', 'Dibujo de dos entradas HDMI, IN y OUT, con la salida OUT marcada.'],
        'projector_no_signal' => ['Mensaje «Sin señal»', 'Dibujo de la pared proyectada con el mensaje Sin señal.'],
        'screen_switch' => ['Interruptor del ecran', 'Dibujo del interruptor de pared del ecran con el botón Bajar marcado.'],
        'screen' => ['Ecran', 'Dibujo del ecran con el centro del borde de abajo marcado.'],
        'tower_pc' => ['Computadora de torre', 'Dibujo de un monitor y una caja de torre unidos por el cable de video.'],
        'usb_ports' => ['Entradas USB', 'Dibujo de las entradas USB al costado de la computadora.'],
        'mouse' => ['Mouse', 'Dibujo de un mouse por arriba y por abajo, con el interruptor y el receptor USB.'],
        'keyboard' => ['Teclas que se usan', 'Dibujo de un teclado con las teclas Bloq Mayús, Bloq Num y Windows marcadas.'],
        'keyboard_language' => ['Idioma del teclado', 'Dibujo de la barra de tareas con el idioma ESP marcado.'],
        'task_manager' => ['Administrador de tareas', 'Dibujo del menú de Ctrl Alt Supr con Administrador de tareas marcado.'],
        'win_p' => ['Windows + P', 'Dibujo del menú Proyectar con la opción Duplicar marcada.'],
        'volume_icon' => ['Ícono del volumen', 'Dibujo de la barra de tareas con el ícono del parlante silenciado marcado.'],
        'audio_output' => ['Salida de sonido', 'Dibujo del menú de volumen con los parlantes del aula elegidos.'],
        'speaker' => ['Parlante del aula', 'Dibujo de un parlante con la perilla de volumen marcada.'],
        'audio_cable' => ['Cable de audio', 'Dibujo de las entradas de audio con la verde marcada.'],
    ];

    public function handle(ImageProcessor $processor): int
    {
        if ($this->option('quitar')) {
            return $this->quitar($processor);
        }

        $dir = base_path('database'.DIRECTORY_SEPARATOR.'imagenes-provisionales');
        $claves = (new PilotDiagnosticsSeeder)->componentKeys();
        $cargadas = 0;

        foreach ($claves as $clave) {
            $archivo = $dir.DIRECTORY_SEPARATOR.$clave.'.svg';

            if (! is_file($archivo)) {
                $this->warn("  Falta la ilustración de «{$clave}»: ese paso se verá sin imagen.");

                continue;
            }

            [$titulo, $alt] = self::TEXTOS[$clave] ?? [$clave, 'Ilustración provisional.'];
            $code = self::PREFIX.strtoupper(str_replace('_', '-', $clave));

            $guardado = $processor->storeSvg(File::get($archivo), $code);

            MediaAsset::withTrashed()->updateOrCreate(
                ['code' => $code],
                array_merge($guardado, [
                    'title' => $titulo.' (provisional)',
                    'alt_text' => $alt,
                    // El pie ya va dibujado dentro de la ilustracion.
                    'caption' => null,
                    'type' => 'diagram',
                    'source' => 'Ilustración provisional del proyecto',
                    'license' => 'Uso interno del proyecto',
                    'scope_type' => 'component',
                    'scope_key' => $clave,
                    'is_active' => true,
                    'is_demo' => true,
                    'deleted_at' => null,
                ])
            );

            $cargadas++;
        }

        $this->info("{$cargadas} ilustraciones provisionales cargadas.");
        $this->line('Cuando subas la foto real con la misma clave, la reemplaza sola.');

        return self::SUCCESS;
    }

    private function quitar(ImageProcessor $processor): int
    {
        $assets = MediaAsset::withTrashed()->where('code', 'like', self::PREFIX.'%')->get();

        foreach ($assets as $asset) {
            $processor->delete($asset->file_path, $asset->thumbnail_path);
            // La relacion con los pasos tiene borrado restringido: primero se
            // sueltan, por si alguna se asigno a mano desde el panel.
            DB::table('diagnostic_step_media')->where('media_asset_id', $asset->id)->delete();
            $asset->forceDelete();
        }

        $this->info("{$assets->count()} ilustraciones provisionales eliminadas.");

        return self::SUCCESS;
    }
}
