<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Sirve las imagenes del banco visual.
 *
 * Van por controlador y NO desde el directorio publico (plan 16.2). Tres
 * razones:
 *
 *  1. Los archivos viven fuera del webroot: no se pueden pedir a mano ni
 *     enumerar probando nombres.
 *  2. Solo se entregan activos ACTIVOS: despublicar una imagen la retira de
 *     verdad, no solo de las pantallas.
 *  3. Permite fijar las cabeceras de cache y de tipo de contenido, en lugar
 *     de dejarlas al criterio del servidor web.
 *
 * Es publico a proposito: el docente no esta autenticado y necesita ver las
 * imagenes del diagnostico. No hay nada sensible en un dibujo de un
 * conector HDMI.
 */
class MediaController extends Controller
{
    public function show(Request $request, MediaAsset $asset): Response
    {
        abort_unless($asset->is_active, 404);

        $wantsThumb = $request->boolean('thumb') && $asset->thumbnail_path !== null;
        $path = $wantsThumb ? $asset->thumbnail_path : $asset->file_path;

        $disk = Storage::disk(config('incidencias.media.disk'));

        abort_unless($disk->exists($path), 404);

        return response($disk->get($path), SymfonyResponse::HTTP_OK, [
            'Content-Type' => $asset->mime_type ?? 'application/octet-stream',

            // Cache larga: el contenido de un activo no cambia. Si se
            // reemplaza la imagen se crea un archivo con nombre nuevo, asi
            // que no hay riesgo de servir una version obsoleta.
            'Cache-Control' => 'public, max-age=604800',

            // El navegador no debe adivinar el tipo: con un SVG servido
            // desde el mismo origen, adivinar mal es un XSS.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
