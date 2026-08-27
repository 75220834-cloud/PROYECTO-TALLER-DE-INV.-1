<?php

declare(strict_types=1);

namespace App\Modules\Locations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Locations\Services\QrCodeService;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Pantalla del QR generico y su cartel imprimible (plan CU-A-06).
 */
class QrCodeController extends Controller
{
    public function show(QrCodeService $qr): View
    {
        return view('admin.qr.show', [
            'targetUrl' => $qr->targetUrl(),
            'pngDataUri' => $qr->pngDataUri(320),
        ]);
    }

    /** Cartel A4 listo para imprimir y pegar en el aula. */
    public function poster(QrCodeService $qr): View
    {
        return view('admin.qr.poster', [
            'targetUrl' => $qr->targetUrl(),
            'svg' => $qr->svg(520),
        ]);
    }

    public function download(QrCodeService $qr): Response
    {
        // SVG y no PNG: el cartel puede imprimirse en A4 o en A3 y el
        // vectorial no pierde nitidez. Un PNG de 400 px impreso a tamano
        // cartel se ve dentado y algunos lectores fallan.
        return response($qr->svg(600), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-acceso-incidencias.svg"',
        ]);
    }
}
