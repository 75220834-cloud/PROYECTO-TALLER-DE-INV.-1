<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco de imagenes curado (plan 10.8, 13.6 y Anexo A).
 *
 * La IA SELECCIONA de este catalogo; nunca crea ni describe una imagen que
 * no este aqui. Una imagen generada de un puerto HDMI sale con los pines
 * mal y lleva al docente a manipular el conector equivocado: es peor que no
 * mostrar nada, y mas dificil de detectar que una alucinacion de texto.
 *
 * alt_text, source y license son NOT NULL a proposito:
 *  - sin alt_text la imagen es inaccesible y ademas inutil si no carga;
 *  - sin source/license no se puede publicar (procedencia no verificada).
 * Es integridad de datos, no burocracia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();

            // Codigo estable usado por el motor y por la IA: "PUERTO-HDMI-PC".
            $table->string('code', 80)->unique();

            $table->string('title', 150);

            // Obligatorio: accesibilidad y respaldo cuando la imagen no carga.
            $table->string('alt_text', 255);

            $table->string('caption', 255)->nullable();
            $table->enum('type', ['photo', 'diagram', 'gif'])->default('photo');

            $table->string('file_path', 255);
            $table->string('thumbnail_path', 255)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type', 80)->nullable();

            // Procedencia obligatoria (plan 10.8).
            $table->string('source', 255);
            $table->string('license', 120);
            $table->string('attribution', 255)->nullable();

            // Coordenadas relativas de flechas/recuadros. Se superponen en SVG
            // generado por la aplicacion, para que una misma foto base sirva a
            // varios pasos con anotaciones distintas.
            $table->json('annotations')->nullable();

            // Resolucion en cascada: permite reutilizar un activo en varios
            // pasos sin duplicarlo (plan 11.3).
            $table->enum('scope_type', ['component', 'category', 'equipment_type', 'generic'])
                ->default('component');
            $table->string('scope_key', 80)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scope_type', 'scope_key', 'is_active']);
            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
