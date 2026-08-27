<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Processors\ImageProcessor;
use Illuminate\Database\Seeder;

/**
 * Banco visual inicial: diagramas de conectores dibujados por el equipo.
 *
 * POR QUE DIAGRAMAS Y NO FOTOS TODAVIA (plan 10.8, Anexo A)
 * ---------------------------------------------------------
 * Un conector HDMI es identico en cualquier equipo del mundo, asi que estos
 * diagramas sirven desde el primer dia y NO dependen de que exista
 * autorizacion para fotografiar las aulas (riesgo R14 del plan).
 *
 * Cuando lleguen las fotos reales no se descartan: conviven. El diagrama
 * explica QUE ES el conector; la foto muestra DONDE ESTA en ese aula.
 *
 * Y lo que no se hace, por si acaso: NO se generan imagenes con IA. Los
 * modelos generativos dibujan puertos con el numero de pines equivocado, y
 * una imagen plausible pero falsa lleva al docente a manipular el conector
 * incorrecto. Es peor que no mostrar nada y mas dificil de detectar que una
 * alucinacion de texto.
 */
class DemoMediaSeeder extends Seeder
{
    public function run(ImageProcessor $processor): void
    {
        $assets = [
            [
                'code' => 'CONECTOR-HDMI',
                'title' => 'Conector HDMI',
                'alt_text' => 'Dibujo de un conector HDMI: forma de trapecio, más ancho arriba que abajo.',
                'caption' => 'Así es la punta de un cable HDMI.',
                'scope_key' => 'hdmi_connector',
                'svg' => $this->hdmiSvg(),
            ],
            [
                'code' => 'PUERTO-HDMI',
                'title' => 'Puerto HDMI',
                'alt_text' => 'Dibujo de una entrada HDMI señalada con una flecha.',
                'caption' => 'Busca esta entrada con forma de trapecio.',
                'scope_key' => 'hdmi_port',
                'svg' => $this->hdmiPortSvg(),
            ],
            [
                'code' => 'CONECTOR-VGA',
                'title' => 'Conector VGA',
                'alt_text' => 'Dibujo de un conector VGA azul, con quince pines y dos tornillos laterales.',
                'caption' => 'El VGA es azul y tiene dos tornillos a los lados.',
                'scope_key' => 'vga_connector',
                'svg' => $this->vgaSvg(),
            ],
            [
                'code' => 'CONECTOR-AUDIO',
                'title' => 'Cable de audio 3.5 mm',
                'alt_text' => 'Dibujo de un conector de audio de 3.5 milímetros, delgado y con anillos negros.',
                'caption' => 'El cable de audio es delgado y tiene anillos negros.',
                'scope_key' => 'audio_jack',
                'svg' => $this->audioJackSvg(),
            ],
            [
                'code' => 'BOTON-ENCENDIDO',
                'title' => 'Botón de encendido',
                'alt_text' => 'Símbolo universal de encendido: un círculo abierto con una línea vertical arriba.',
                'caption' => 'Este símbolo marca el botón de encendido.',
                'scope_key' => 'power_button',
                'svg' => $this->powerSvg(),
            ],
            [
                'code' => 'LUZ-ESTADO',
                'title' => 'Luces indicadoras',
                'alt_text' => 'Tres luces: verde encendido, naranja en espera y roja de error.',
                'caption' => 'Verde: encendido. Naranja: en espera. Roja: problema.',
                'scope_key' => 'status_light',
                'svg' => $this->statusLightSvg(),
            ],
        ];

        foreach ($assets as $asset) {
            $stored = $processor->storeSvg($asset['svg'], $asset['code']);

            MediaAsset::updateOrCreate(
                ['code' => $asset['code']],
                array_merge($stored, [
                    'title' => $asset['title'],
                    'alt_text' => $asset['alt_text'],
                    'caption' => $asset['caption'],
                    'type' => 'diagram',

                    // Procedencia obligatoria: sin ella no se publica.
                    'source' => 'Diagrama propio del equipo del proyecto',
                    'license' => 'Uso interno del proyecto',

                    'scope_type' => 'component',
                    'scope_key' => $asset['scope_key'],
                    'is_active' => true,

                    // Marcados como demo hasta que soporte los valide.
                    'is_demo' => true,
                ])
            );
        }

        $this->command->info('  Banco visual: '.count($assets).' diagramas de conectores.');
    }

    // ------------------------------------------------------------ dibujos
    // Formas deliberadamente simples y CORRECTAS. Lo que importa no es que
    // sean bonitas, sino que el docente reconozca la pieza que tiene en la
    // mano: el HDMI es un trapecio, el VGA es azul con tornillos, el jack
    // de audio es delgado con anillos.

    private function hdmiSvg(): string
    {
        return <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 140" role="img" aria-label="Conector HDMI">
          <rect width="240" height="140" fill="#f8fafc"/>
          <path d="M60 45 h120 l-12 42 h-96 Z" fill="#475569" stroke="#1e293b" stroke-width="3"/>
          <path d="M70 52 h100 l-8 26 h-84 Z" fill="#cbd5e1"/>
          <g fill="#94a3b8">
            <rect x="80" y="58" width="5" height="14"/><rect x="92" y="58" width="5" height="14"/>
            <rect x="104" y="58" width="5" height="14"/><rect x="116" y="58" width="5" height="14"/>
            <rect x="128" y="58" width="5" height="14"/><rect x="140" y="58" width="5" height="14"/>
            <rect x="152" y="58" width="5" height="14"/>
          </g>
          <rect x="104" y="87" width="32" height="30" fill="#334155"/>
          <text x="120" y="132" text-anchor="middle" font-family="system-ui,sans-serif" font-size="15" font-weight="600" fill="#0f172a">HDMI</text>
        </svg>
        SVG;
    }

    private function hdmiPortSvg(): string
    {
        return <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 140" role="img" aria-label="Puerto HDMI señalado">
          <rect width="240" height="140" fill="#f8fafc"/>
          <rect x="30" y="55" width="180" height="52" rx="5" fill="#e2e8f0" stroke="#94a3b8" stroke-width="2"/>
          <path d="M92 70 h56 l-6 20 h-44 Z" fill="#0f172a"/>
          <path d="M97 74 h46 l-4 12 h-38 Z" fill="#475569"/>
          <path d="M120 20 v24 M120 44 l-9 -10 M120 44 l9 -10" stroke="#dc2626" stroke-width="4" fill="none" stroke-linecap="round"/>
          <text x="120" y="128" text-anchor="middle" font-family="system-ui,sans-serif" font-size="14" font-weight="600" fill="#0f172a">Entrada HDMI</text>
        </svg>
        SVG;
    }

    private function vgaSvg(): string
    {
        return <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 140" role="img" aria-label="Conector VGA">
          <rect width="240" height="140" fill="#f8fafc"/>
          <path d="M62 48 h116 l-10 44 h-96 Z" fill="#1d4ed8" stroke="#1e3a8a" stroke-width="3"/>
          <path d="M74 56 h92 l-7 28 h-78 Z" fill="#93c5fd"/>
          <g fill="#1e3a8a">
            <circle cx="88" cy="63" r="3"/><circle cx="102" cy="63" r="3"/><circle cx="116" cy="63" r="3"/>
            <circle cx="130" cy="63" r="3"/><circle cx="144" cy="63" r="3"/>
            <circle cx="93" cy="72" r="3"/><circle cx="107" cy="72" r="3"/><circle cx="121" cy="72" r="3"/>
            <circle cx="135" cy="72" r="3"/><circle cx="149" cy="72" r="3"/>
          </g>
          <circle cx="52" cy="70" r="9" fill="#64748b"/><circle cx="188" cy="70" r="9" fill="#64748b"/>
          <text x="120" y="128" text-anchor="middle" font-family="system-ui,sans-serif" font-size="15" font-weight="600" fill="#0f172a">VGA (azul, con tornillos)</text>
        </svg>
        SVG;
    }

    private function audioJackSvg(): string
    {
        return <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 140" role="img" aria-label="Conector de audio 3.5 mm">
          <rect width="240" height="140" fill="#f8fafc"/>
          <rect x="60" y="62" width="60" height="20" rx="4" fill="#334155"/>
          <rect x="120" y="66" width="70" height="12" rx="6" fill="#cbd5e1" stroke="#94a3b8" stroke-width="2"/>
          <rect x="146" y="66" width="5" height="12" fill="#0f172a"/>
          <rect x="163" y="66" width="5" height="12" fill="#0f172a"/>
          <circle cx="188" cy="72" r="6" fill="#cbd5e1" stroke="#94a3b8" stroke-width="2"/>
          <text x="120" y="112" text-anchor="middle" font-family="system-ui,sans-serif" font-size="15" font-weight="600" fill="#0f172a">Audio 3.5 mm</text>
          <text x="120" y="130" text-anchor="middle" font-family="system-ui,sans-serif" font-size="12" fill="#64748b">delgado, con anillos negros</text>
        </svg>
        SVG;
    }

    private function powerSvg(): string
    {
        return <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 140" role="img" aria-label="Símbolo de encendido">
          <rect width="240" height="140" fill="#f8fafc"/>
          <circle cx="120" cy="62" r="34" fill="#e2e8f0" stroke="#94a3b8" stroke-width="2"/>
          <path d="M120 42 a20 20 0 1 0 0.1 0" fill="none" stroke="#0f172a" stroke-width="6" stroke-linecap="round"/>
          <line x1="120" y1="36" x2="120" y2="58" stroke="#0f172a" stroke-width="6" stroke-linecap="round"/>
          <text x="120" y="122" text-anchor="middle" font-family="system-ui,sans-serif" font-size="15" font-weight="600" fill="#0f172a">Botón de encendido</text>
        </svg>
        SVG;
    }

    private function statusLightSvg(): string
    {
        return <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 140" role="img" aria-label="Luces indicadoras">
          <rect width="240" height="140" fill="#f8fafc"/>
          <circle cx="60" cy="52" r="16" fill="#16a34a"/>
          <circle cx="120" cy="52" r="16" fill="#f59e0b"/>
          <circle cx="180" cy="52" r="16" fill="#dc2626"/>
          <text x="60" y="92" text-anchor="middle" font-family="system-ui,sans-serif" font-size="13" fill="#0f172a">Encendido</text>
          <text x="120" y="92" text-anchor="middle" font-family="system-ui,sans-serif" font-size="13" fill="#0f172a">En espera</text>
          <text x="180" y="92" text-anchor="middle" font-family="system-ui,sans-serif" font-size="13" fill="#0f172a">Problema</text>
          <text x="120" y="122" text-anchor="middle" font-family="system-ui,sans-serif" font-size="12" fill="#64748b">Mira el color de la luz del equipo</text>
        </svg>
        SVG;
    }
}
