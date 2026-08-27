<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogos de incidencia: prioridades, estados y categorias.
 *
 * Patron aplicado en los tres (plan 11.1):
 *   - `code`  INMUTABLE, es lo que la logica de negocio compara.
 *   - `name`  EDITABLE, es lo que ve el usuario.
 *
 * Asi el administrador puede renombrar "Proyector" a "Videoproyector" sin
 * romper una sola regla, y a la vez el codigo nunca compara contra texto
 * que alguien puede cambiar desde un formulario. Esto satisface el
 * requisito de catalogos configurables sin sacrificar la seguridad de la
 * maquina de estados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);

            // Mayor level = mas urgente. Permite comparar y ordenar sin
            // depender del texto ni del id.
            $table->unsignedTinyInteger('level');

            $table->unsignedSmallInteger('sla_response_minutes')->nullable();
            $table->unsignedSmallInteger('sla_resolution_minutes')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'level']);
        });

        Schema::create('incident_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);

            // Distingue los estados que cuentan como "abierto". Es lo que
            // consulta el control antiabuso de tope por aula y lo que filtra
            // la bandeja de soporte, por eso vive en el catalogo y no en un
            // listado quemado en codigo.
            $table->boolean('is_open')->default(true);

            // Estado final: no admite mas transiciones salvo reapertura.
            $table->boolean('is_terminal')->default(false);

            // Marca los estados que cuentan como resueltos para las metricas.
            $table->boolean('is_resolved')->default(false);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_open', 'sort_order']);
        });

        Schema::create('incident_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);

            // Texto corto y sin jerga que se muestra en el boton grande de
            // "que problema tienes" ("No se ve la imagen").
            $table->string('teacher_label', 120)->nullable();

            $table->string('icon', 60)->nullable();

            // Imagen representativa: permite al docente reconocer la
            // categoria visualmente sin depender de saber el nombre tecnico
            // (plan 13.6). Nullable: el sistema degrada a solo texto.
            $table->foreignId('media_asset_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();

            $table->foreignId('default_priority_id')->nullable()
                ->constrained('incident_priorities')->restrictOnDelete();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_categories');
        Schema::dropIfExists('incident_statuses');
        Schema::dropIfExists('incident_priorities');
    }
};
