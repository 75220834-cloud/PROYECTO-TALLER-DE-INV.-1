<?php

declare(strict_types=1);

namespace App\Modules\Locations\Services;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Generacion del QR de acceso.
 *
 * DECISION FIRME (plan v3, D-3): existe UN SOLO codigo QR, generico e
 * identico en todas las aulas del piloto. El QR identifica el acceso al
 * sistema, NUNCA la ubicacion.
 *
 * Por eso esta clase no recibe ni acepta un aula. Si en el futuro alguien
 * intenta anadir un parametro de aula aqui, esta rompiendo una decision
 * tomada a proposito, no anadiendo una mejora: la ubicacion se determina
 * siempre por seleccion del docente contra el catalogo institucional.
 *
 * Tampoco lleva firma ni token. Una URL firmada fija no es un control de
 * acceso: cualquiera que escanee el cartel una vez conserva el enlace y
 * puede compartirlo. La proteccion vive en la creacion del ticket (16.4),
 * no en el acceso.
 */
final class QrCodeService
{
    /** Destino del QR: la entrada del docente al sistema. */
    public function targetUrl(): string
    {
        return route('teacher.start');
    }

    /**
     * SVG para impresion: escala a cualquier tamano de cartel sin pixelarse.
     */
    public function svg(int $size = 400): string
    {
        return (new SvgWriter)->write($this->build($size))->getString();
    }

    /**
     * PNG para pantalla y para pegar en documentos.
     */
    public function png(int $size = 400): string
    {
        return (new PngWriter)->write($this->build($size))->getString();
    }

    /** Data URI lista para incrustar en una vista sin escribir archivos. */
    public function pngDataUri(int $size = 400): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($size));
    }

    private function build(int $size): QrCode
    {
        return new QrCode(
            data: $this->targetUrl(),
            encoding: new Encoding('UTF-8'),

            // Correccion ALTA a proposito: el cartel vive pegado en un aula,
            // donde se raya, se despega por una esquina y acumula polvo. Un
            // nivel bajo ahorraria unos milimetros y haria que dejara de
            // leerse a los pocos meses.
            errorCorrectionLevel: ErrorCorrectionLevel::High,

            size: $size,

            // Margen generoso: sin zona de silencio suficiente, muchas
            // camaras de celular no enfocan el codigo.
            margin: 16,
        );
    }
}
