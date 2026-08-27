<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadena de ubicaciones: sede -> pabellon -> piso -> aula.
 *
 * Reglas que esta migracion hace CUMPLIR a nivel de base de datos, no solo
 * de aplicacion (plan 26):
 *
 *  - No puede existir un pabellon sin sede, un piso sin pabellon ni un aula
 *    sin piso. Las claves foraneas usan RESTRICT: borrar una sede con
 *    pabellones falla, no arrastra en cascada.
 *  - Los codigos son unicos DENTRO de su padre, no globalmente: dos
 *    pabellones distintos pueden tener un aula "101" cada uno.
 *  - rooms.code se ALMACENA, nunca se deriva concatenando pabellon+piso+
 *    numero. Derivarlo asumiria una nomenclatura perfecta que no ha sido
 *    confirmada y que en la practica tiene excepciones (laboratorios,
 *    auditorios). La resolucion es una CONSULTA al catalogo (plan 11.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });

        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Un pabellon "C" por sede, no uno global.
            $table->unique(['site_id', 'code']);
            $table->index(['site_id', 'is_active', 'sort_order']);
        });

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->restrictOnDelete();

            // Entero con signo a proposito: permite sotanos (-1) sin
            // necesitar un caso especial mas adelante.
            $table->smallInteger('number');

            // Etiqueta visible al docente ("Piso 3", "Semisotano"). Se
            // guarda porque no todo piso se nombra con su numero.
            $table->string('label', 50);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Impide dos "piso 3" en el mismo pabellon.
            $table->unique(['building_id', 'number']);
            $table->index(['building_id', 'is_active', 'number']);
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained('floors')->restrictOnDelete();

            // Codigo real del aula ("C305", "LAB-2"). Almacenado, no derivado.
            $table->string('code', 50);
            $table->string('name', 150)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();

            // Alimenta el calculo de prioridad y el modelo de riesgo.
            $table->unsignedTinyInteger('criticality')->default(1);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['floor_id', 'code']);

            // Busqueda directa por codigo: la via alternativa a la cascada
            // que el docente puede usar escribiendo "C305" (plan CU-D-04).
            $table->index('code');
            $table->index(['floor_id', 'is_active', 'code']);
        });
    }

    public function down(): void
    {
        // Orden inverso: las FK con RESTRICT impiden cualquier otro orden.
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('sites');
    }
};
