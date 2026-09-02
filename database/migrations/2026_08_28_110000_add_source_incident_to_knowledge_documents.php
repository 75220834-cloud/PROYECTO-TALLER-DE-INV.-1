<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De que incidencia salio un articulo de conocimiento (plan CU-S-16).
 *
 * Sin esta columna, un articulo redactado a partir de una solucion real
 * queda indistinguible de uno escrito de memoria. Y cuando el asistente cite
 * ese articulo a un docente, nadie va a poder rastrear si lo que afirma
 * ocurrio de verdad alguna vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            // nullOnDelete y no cascade: si algun dia se borra la incidencia,
            // el articulo sigue siendo valido, solo pierde su procedencia.
            $table->foreignId('source_incident_id')->nullable()->after('category_id')
                ->constrained('incidents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_incident_id');
        });
    }
};
