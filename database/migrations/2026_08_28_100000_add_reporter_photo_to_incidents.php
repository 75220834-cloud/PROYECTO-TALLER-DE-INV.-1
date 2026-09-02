<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto que el docente adjunta al pedir soporte (plan decision D-12).
 *
 * Es la ruta del archivo, no el archivo: guardar imagenes en la base la
 * hincha y hace que cada consulta del panel arrastre megabytes que nadie
 * mira. El archivo vive en disco como el resto del banco visual.
 *
 * Nullable porque adjuntar es OPCIONAL y debe seguir siendolo: un docente
 * con el aula esperando no puede quedar bloqueado por una camara que no
 * abre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('reporter_photo_path')->nullable()->after('reported_description');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn('reporter_photo_path');
        });
    }
};
