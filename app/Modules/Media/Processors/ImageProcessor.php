<?php

declare(strict_types=1);

namespace App\Modules\Media\Processors;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Procesa las imagenes que se suben al banco visual.
 *
 * NUNCA se sirve el archivo original tal cual (plan 16.2). Reprocesarlo
 * cumple tres cosas a la vez, y las tres importan:
 *
 *  1. SEGURIDAD: al redibujar la imagen se descarta cualquier carga
 *     maliciosa embebida en los metadatos o en bytes sobrantes del archivo.
 *  2. PRIVACIDAD: se eliminan los metadatos EXIF. Una foto tomada con un
 *     celular puede llevar la geolocalizacion exacta del aula, y eso es un
 *     dato que el sistema no necesita retener.
 *  3. PESO: la imagen se sirve a un docente de pie, con una clase
 *     esperando y una conexion que puede ser mala. Excederse en el tamano
 *     penaliza en el peor momento posible.
 */
final class ImageProcessor
{
    private ImageManager $manager;

    public function __construct()
    {
        // Driver GD: viene con el XAMPP del equipo. Imagick daria mejor
        // calidad pero no esta garantizado en todas las maquinas y el plan
        // prioriza que los tres integrantes tengan el mismo entorno.
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Guarda la imagen procesada y devuelve sus metadatos.
     *
     * @return array{file_path: string, thumbnail_path: string, width: int, height: int, file_size: int, mime_type: string}
     */
    public function store(UploadedFile $file, string $code): array
    {
        $disk = Storage::disk(config('incidencias.media.disk'));
        $dir = trim((string) config('incidencias.media.path'), '/');

        $maxWidth = (int) config('incidencias.media.max_width');
        $thumbWidth = (int) config('incidencias.media.thumbnail_width');
        $format = (string) config('incidencias.media.format');

        // Nombre aleatorio: el nombre original puede llevar informacion del
        // equipo de quien lo subio y ademas es una via clasica de traversal.
        $basename = Str::slug($code).'-'.Str::random(8);

        $image = $this->manager->read($file->getRealPath());

        // scaleDown no agranda: una foto pequena se queda como esta en vez
        // de ampliarse y verse pixelada.
        $image->scaleDown(width: $maxWidth);

        $encoded = $format === 'webp'
            ? $image->toWebp(quality: 82)
            : $image->toJpeg(quality: 82);

        $path = "{$dir}/{$basename}.{$format}";
        $disk->put($path, (string) $encoded);

        $thumb = $this->manager->read($file->getRealPath())->scaleDown(width: $thumbWidth);
        $thumbPath = "{$dir}/{$basename}-thumb.{$format}";
        $disk->put($thumbPath, (string) ($format === 'webp' ? $thumb->toWebp(quality: 75) : $thumb->toJpeg(quality: 75)));

        return [
            'file_path' => $path,
            'thumbnail_path' => $thumbPath,
            'width' => $image->width(),
            'height' => $image->height(),
            'file_size' => strlen((string) $encoded),
            'mime_type' => $format === 'webp' ? 'image/webp' : 'image/jpeg',
        ];
    }

    /**
     * Guarda un diagrama SVG creado por el equipo.
     *
     * Los SVG NO pasan por el redibujado: son vectoriales y reprocesarlos
     * los destruiria. A cambio, solo se aceptan los que genera el propio
     * equipo, nunca una subida de usuario: un SVG puede contener JavaScript
     * ejecutable y servirlo seria abrir un XSS de manual (plan 16.2).
     */
    public function storeSvg(string $svg, string $code): array
    {
        $disk = Storage::disk(config('incidencias.media.disk'));
        $dir = trim((string) config('incidencias.media.path'), '/');

        $path = "{$dir}/".Str::slug($code).'.svg';
        $disk->put($path, $svg);

        return [
            'file_path' => $path,
            'thumbnail_path' => null,
            'width' => null,
            'height' => null,
            'file_size' => strlen($svg),
            'mime_type' => 'image/svg+xml',
        ];
    }

    public function delete(?string ...$paths): void
    {
        $disk = Storage::disk(config('incidencias.media.disk'));

        foreach ($paths as $path) {
            if ($path !== null && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
