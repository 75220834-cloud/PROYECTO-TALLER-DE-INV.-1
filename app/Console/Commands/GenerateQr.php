<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Locations\Services\QrCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Genera el QR de acceso en archivos y dice a donde apunta.
 *
 * Existe para poder comprobar la direccion antes de abrir el panel, y
 * para tener el QR a mano cuando se prueba con un tunel temporal, cuya
 * direccion cambia cada vez que se reinicia.
 */
final class GenerateQr extends Command
{
    protected $signature = 'qr:generar';

    protected $description = 'Genera el QR de acceso (PNG y SVG) y muestra a qué dirección apunta';

    public function handle(QrCodeService $qr): int
    {
        $dir = storage_path('app'.DIRECTORY_SEPARATOR.'qr');
        File::ensureDirectoryExists($dir);

        File::put("{$dir}/qr-acceso.png", $qr->png(600));
        File::put("{$dir}/qr-acceso.svg", $qr->svg(600));

        $this->line('El QR apunta a:');
        $this->info('  '.$qr->targetUrl());
        $this->newLine();
        $this->line("Archivos generados en {$dir}:");
        $this->line('  qr-acceso.png   para ver en pantalla o mandar por mensaje');
        $this->line('  qr-acceso.svg   para imprimir, no pierde nitidez');

        $warning = $qr->printWarning();

        if ($warning !== null) {
            $this->newLine();
            $this->warn('ATENCIÓN: '.$warning);
        }

        return self::SUCCESS;
    }
}
