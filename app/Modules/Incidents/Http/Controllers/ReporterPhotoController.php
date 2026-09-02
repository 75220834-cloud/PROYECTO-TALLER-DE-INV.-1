<?php

declare(strict_types=1);

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Models\Incident;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve la foto que adjunto el docente (decision D-12).
 *
 * Va por controlador y NO desde public/: la foto de un aula puede mostrar
 * cosas que nadie decidio publicar —una pizarra con nombres, una pantalla con
 * datos— y en public/ quedaria accesible para cualquiera que adivine la
 * direccion. Aqui exige sesion y permiso de ver incidencias.
 */
class ReporterPhotoController extends Controller
{
    public function __invoke(Incident $incident): StreamedResponse
    {
        abort_if($incident->reporter_photo_path === null, 404);

        $disk = Storage::disk(config('incidencias.media.disk'));

        abort_unless($disk->exists($incident->reporter_photo_path), 404);

        // La cache es PRIVADA: es contenido de una incidencia concreta, y un
        // proxy compartido no debe guardarlo para servirselo a otro.
        return $disk->response($incident->reporter_photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
