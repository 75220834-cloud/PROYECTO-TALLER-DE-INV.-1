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
    /**
     * Destino del QR: la entrada del docente al sistema.
     *
     * Con PUBLIC_URL definida se usa esa direccion y no la de la peticion
     * en curso. El QR se genera desde el panel, que se abre en la PC de
     * soporte como http://localhost; tomar la direccion de la peticion
     * produciria un cartel que en el celular del docente no abre nada.
     */
    public function targetUrl(): string
    {
        $public = rtrim((string) config('incidencias.public_url'), '/');

        if ($public === '') {
            return route('teacher.start');
        }

        return $public.route('teacher.start', absolute: false);
    }

    /**
     * Motivo por el que el QR actual NO sirve para imprimir, o null si sirve.
     *
     * Un QR que apunta a localhost o a una IP privada se ve perfecto en
     * pantalla y funciona en la PC donde se genero. Falla en el aula, con
     * los carteles ya pegados. Esta comprobacion existe para que eso se vea
     * antes de imprimir.
     */
    public function printWarning(): ?string
    {
        $host = (string) parse_url($this->targetUrl(), PHP_URL_HOST);

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return 'Este código apunta a «localhost»: solo abre en esta misma computadora. '
                .'En el celular de un docente no abre nada. Define PUBLIC_URL antes de imprimir.';
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return "Este código apunta a una dirección de red interna ({$host}). Solo abre desde esa misma red: "
                .'un docente con datos móviles no podrá entrar. Define PUBLIC_URL antes de imprimir.';
        }

        if (str_ends_with($host, '.trycloudflare.com')) {
            return 'Este código apunta a un túnel temporal de Cloudflare. Esa dirección cambia cada vez que '
                .'se reinicia el túnel: sirve para probar, NO para imprimir carteles.';
        }

        return null;
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
