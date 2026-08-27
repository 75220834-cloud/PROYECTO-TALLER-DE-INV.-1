<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Señales de riesgo (plan 15).
 *
 * QUÉ SE PREDICE Y QUÉ NO
 * No se predice que un equipo se vaya a averiar. Se estima la probabilidad
 * de que un aula (o un aula y una categoría) registre AL MENOS UNA
 * incidencia en los próximos N días. El plan descarta expresamente lo
 * primero por no ser sustentable con los datos disponibles (§27).
 *
 * `factors` es NOT NULL a propósito. Un score sin explicación se percibe
 * como arbitrario y acaba ignorándose por el equipo de soporte, con lo cual
 * todo el módulo deja de servir para nada. Si un método no puede explicarse
 * en estos términos, no se usa: la interpretabilidad manda sobre la
 * precisión en este contexto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();

            $table->enum('scope_type', ['room', 'equipment', 'room_category']);
            $table->unsignedBigInteger('scope_id');

            $table->foreignId('category_id')->nullable()
                ->constrained('incident_categories')->nullOnDelete();

            $table->unsignedSmallInteger('horizon_days');
            $table->decimal('score', 5, 4);
            $table->enum('risk_band', ['low', 'medium', 'high']);

            // Los factores que produjeron el score, en lenguaje llano.
            $table->json('factors');

            // Qué modelo lo calculó. Sin esto no se puede comparar un
            // periodo con otro ni saber si una mejora vino del modelo o del
            // azar: dos números de modelos distintos no son comparables.
            $table->string('model_code', 60);
            $table->string('model_version', 20);

            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'computed_at']);
            $table->index(['risk_band', 'computed_at']);
        });

        /**
         * Agregados diarios precalculados.
         *
         * Desnormalización deliberada (plan 11.1): evita recorrer la tabla
         * de incidencias entera en cada carga del tablero. Es cache
         * reconstruible, no fuente de verdad.
         */
        Schema::create('metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('day');

            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()
                ->constrained('incident_categories')->nullOnDelete();

            $table->unsignedSmallInteger('incidents_total')->default(0);
            $table->unsignedSmallInteger('resolved_by_assistant')->default(0);
            $table->unsignedSmallInteger('resolved_onsite')->default(0);
            $table->unsignedSmallInteger('abandoned_drafts')->default(0);
            $table->unsignedInteger('median_resolution_minutes')->nullable();

            $table->timestamps();

            $table->unique(['day', 'room_id', 'category_id']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_snapshots');
        Schema::dropIfExists('risk_scores');
    }
};
