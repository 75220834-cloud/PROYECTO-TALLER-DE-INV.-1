<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostico guiado: arboles de decision VERSIONADOS.
 *
 * Por que el versionado es obligatorio y no un lujo (plan 11.3):
 * una incidencia guarda la version del arbol que efectivamente ejecuto.
 * Si manana soporte mejora el procedimiento del proyector, las incidencias
 * de ayer siguen siendo interpretables contra el procedimiento que de
 * verdad se siguio. Sin esto, cualquier comparacion antes/despues de la
 * investigacion quedaria contaminada, porque no se sabria si el cambio en
 * los resultados vino del sistema o de que alguien edito un paso.
 *
 * Este motor es DETERMINISTA. El LLM no decide el camino: solo clasifica
 * el texto libre de entrada, reformula el texto de un paso y elige una
 * imagen del catalogo. Si el modelo no esta disponible, el diagnostico
 * funciona igual (plan 13.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('incident_categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
        });

        Schema::create('diagnostic_flow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('diagnostic_flows')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');

            // Solo una version publicada a la vez por flujo; las demas son
            // borradores en preparacion.
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['flow_id', 'version']);
            $table->index(['flow_id', 'published_at']);
        });

        Schema::create('diagnostic_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_version_id')->constrained('diagnostic_flow_versions')->cascadeOnDelete();

            // Clave estable dentro de la version, para que next_step_map
            // referencie pasos por nombre y no por id autoincremental.
            $table->string('step_key', 60);

            $table->unsignedSmallInteger('sort_order')->default(0);

            // Texto base SIEMPRE valido: es lo que se muestra si el LLM no
            // esta disponible o si su reformulacion se descarta.
            $table->text('prompt_text');
            $table->text('help_text')->nullable();

            // Opciones cerradas: [{value, label}]. El docente responde con
            // botones, nunca escribiendo, para que la respuesta sea
            // analizable y el flujo reproducible.
            $table->json('answer_options');

            // Mapa respuesta -> siguiente step_key.
            $table->json('next_step_map')->nullable();

            $table->boolean('is_terminal')->default(false);
            $table->enum('terminal_outcome', ['resolved', 'escalate'])->nullable();

            // Componente fisico que ilustra la imagen ("hdmi_port").
            // Es la clave de la cascada de resolucion visual (plan 13.6).
            $table->string('component_key', 80)->nullable();

            $table->string('knowledge_ref', 150)->nullable();
            $table->timestamps();

            $table->unique(['flow_version_id', 'step_key']);
            $table->index(['flow_version_id', 'sort_order']);
        });

        // Imagenes de cada paso. Tabla puente para permitir varias vistas
        // del mismo componente sin duplicar el activo (plan 11.3).
        Schema::create('diagnostic_step_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')->constrained('diagnostic_steps')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('role', ['primary', 'alternate_view', 'detail'])->default('primary');
            $table->timestamps();

            $table->unique(['step_id', 'media_asset_id']);
            $table->index(['step_id', 'sort_order']);
        });

        Schema::create('diagnostic_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('diagnostic_steps')->restrictOnDelete();
            $table->string('answer_value', 60);
            $table->timestamp('answered_at');

            // Cuanto tardo el docente en ese paso. Un paso sistematicamente
            // lento senala que la instruccion no se entiende.
            $table->unsignedInteger('duration_ms')->nullable();

            // Que imagenes se mostraron realmente. Permite contrastar la
            // resolucion autonoma en pasos CON y SIN apoyo visual, y
            // convierte una decision de diseno en una observacion medible
            // en lugar de una opinion (plan 13.6).
            $table->json('media_shown')->nullable();

            $table->timestamps();

            $table->index(['incident_id', 'answered_at']);
            $table->index(['step_id', 'answer_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_answers');
        Schema::dropIfExists('diagnostic_step_media');
        Schema::dropIfExists('diagnostic_steps');
        Schema::dropIfExists('diagnostic_flow_versions');
        Schema::dropIfExists('diagnostic_flows');
    }
};
