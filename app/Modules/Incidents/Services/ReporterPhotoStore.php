<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Guarda la foto que el docente adjunta al pedir soporte (decisión D-12).
 *
 * ES LA ÚNICA SUBIDA DE ARCHIVOS QUE EL SISTEMA ACEPTA SIN AUTENTICACIÓN, y
 * por eso se trata con más cuidado que el banco de imágenes del panel:
 *
 * 1. SE REPROCESA SIEMPRE, nunca se guarda el archivo original. Eso elimina
 *    los metadatos EXIF —incluida la geolocalización de dónde se tomó— y
 *    neutraliza cualquier carga escondida dentro de la imagen. Un archivo que
 *    entra sin identificar a quien lo sube no se sirve tal cual jamás.
 *
 * 2. EL NOMBRE ES ALEATORIO. El original puede llevar el modelo del teléfono
 *    y además es la vía clásica de escapar del directorio.
 *
 * 3. SE REDUCE A 1200 px. Un móvil moderno sube fotos de 8 MB que el técnico
 *    va a mirar en una pantalla: guardar el original solo llena el disco del
 *    servidor institucional.
 *
 * Vive fuera del webroot y se sirve por controlador con permiso, igual que
 * el resto del banco visual.
 */
final class ReporterPhotoStore
{
    private const MAX_WIDTH = 1200;

    private readonly ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    public function store(UploadedFile $file, string $incidentUuid): string
    {
        $disk = Storage::disk(config('incidencias.media.disk'));
        $dir = trim((string) config('incidencias.media.path'), '/').'/reportes';

        $image = $this->manager->read($file->getRealPath());

        // scaleDown no agranda: una foto pequeña se queda como está en lugar
        // de estirarse y verse peor de lo que llegó.
        $image->scaleDown(width: self::MAX_WIDTH);

        // El uuid en el nombre facilita relacionar archivo e incidencia si
        // alguien tiene que revisar el disco a mano; la parte aleatoria
        // impide adivinar la ruta de la foto de otro.
        $path = "{$dir}/{$incidentUuid}-".Str::random(8).'.webp';

        $disk->put($path, (string) $image->toWebp(quality: 80));

        return $path;
    }
}
