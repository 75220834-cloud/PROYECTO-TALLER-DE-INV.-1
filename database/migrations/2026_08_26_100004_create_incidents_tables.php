<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nucleo del sistema: incidencias, su historial y su conversacion.
 *
 * Decision de diseno clave (plan 7.1): la incidencia nace como BORRADOR en
 * cuanto el docente confirma la ubicacion, no cuando escala. Motivo de
 * investigacion: permite medir ABANDONOS (entro y no completo) y el tiempo
 * real desde el inicio del reporte. Si el reloj arrancara al crear el
 * ticket, el indicador de tiempo quedaria sesgado a favor del sistema y la
 * comparacion contra el proceso tradicional no valdria nada.
 *
 * Nota sobre indices: el plan preveia un indice PARCIAL sobre los estados
 * activos. MySQL/MariaDB no soporta indices parciales (era sintaxis de
 * PostgreSQL, heredada del diseno anterior a la decision D-1). Se sustituye
 * por indices compuestos que sirven las mismas consultas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();

            // Identificador publico no enumerable, para enlaces de
            // seguimiento que no revelen cuantas incidencias existen.
            $table->uuid('uuid')->unique();

            // Numero legible ("000184"). Nullable: un borrador todavia no
            // consume numero de ticket.
            $table->string('ticket_number', 20)->nullable()->unique();

            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();

            // Nullable: el borrador se crea al confirmar la ubicacion,
            // ANTES de que el docente elija la categoria.
            $table->foreignId('category_id')->nullable()
                ->constrained('incident_categories')->restrictOnDelete();

            $table->foreignId('status_id')->constrained('incident_statuses')->restrictOnDelete();
            $table->foreignId('priority_id')->nullable()
                ->constrained('incident_priorities')->restrictOnDelete();

            $table->text('reported_description')->nullable();

            // Trazabilidad de quien clasifico: necesaria para evaluar el
            // acierto del clasificador contra la correccion del docente.
            $table->enum('classified_by', ['teacher', 'assistant', 'support'])->nullable();
            $table->decimal('classification_confidence', 4, 3)->nullable();

            // Senal del docente. NO es la prioridad: entra como una variable
            // mas en la regla de calculo (plan 16.4).
            $table->boolean('blocks_class')->default(false);

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('resolution_type', ['assistant', 'onsite', 'remote', 'none'])->nullable();
            $table->text('resolution_notes')->nullable();
            $table->text('technical_diagnosis')->nullable();

            // Identificacion OPCIONAL y omitible. No es credencial: no da ni
            // niega acceso (plan 16.1).
            $table->string('reporter_hint', 120)->nullable();

            // Identificador opaco del dispositivo (localStorage). Sin datos
            // personales. Alimenta el limite antiabuso por dispositivo.
            $table->string('device_key', 64)->nullable();

            // IP HASHEADA con HMAC, nunca en claro: basta para agrupar y
            // limitar, y evita retener un dato personal que el sistema no
            // necesita (plan 16.4).
            $table->string('ip_hash', 64)->nullable();

            $table->boolean('is_draft')->default(true);

            // Cuando el docente se suma a un ticket existente en lugar de
            // crear uno nuevo (control antiduplicados).
            $table->foreignId('merged_into_id')->nullable()
                ->constrained('incidents')->nullOnDelete();

            $table->unsignedTinyInteger('reopened_count')->default(0);

            // Marcas de tiempo del ciclo de vida. Cada una es un indicador
            // operativo de la investigacion (plan 26.bis).
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            // Bandeja de soporte: la consulta mas frecuente del panel.
            $table->index(['status_id', 'created_at']);

            // Historial por aula y calculo de recurrencia para el riesgo.
            $table->index(['room_id', 'created_at']);
            $table->index(['category_id', 'created_at']);
            $table->index(['assigned_to', 'status_id']);

            // Antiduplicados y tope por aula: se consulta en CADA intento de
            // creacion de ticket, por eso tiene indice propio (plan 11.4).
            $table->index(['room_id', 'category_id', 'status_id']);

            // Limites antiabuso por dispositivo e IP.
            $table->index(['device_key', 'created_at']);
            $table->index(['ip_hash', 'created_at']);

            // Purga de borradores abandonados.
            $table->index(['is_draft', 'created_at']);
        });

        // Historial APPEND-ONLY. Es la fuente de verdad del ciclo de vida y
        // lo que permite responder por que un ticket termino en soporte.
        // No se actualiza ni se borra nunca: sin esa garantia, la auditoria
        // y la reconstruccion de la linea de tiempo no valen nada.
        Schema::create('incident_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->enum('actor_type', ['teacher', 'user', 'system']);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('from_value')->nullable();
            $table->json('to_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');

            // Sin timestamps(): esta tabla no se modifica, solo se agrega.
            $table->index(['incident_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });

        Schema::create('incident_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->enum('author_type', ['teacher', 'assistant', 'support']);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['incident_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_messages');
        Schema::dropIfExists('incident_events');
        Schema::dropIfExists('incidents');
    }
};
