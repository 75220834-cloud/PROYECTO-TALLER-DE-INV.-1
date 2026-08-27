<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antiabuso, auditoria y satisfaccion.
 *
 * incident_abuse_rejections es la contraparte imprescindible de los
 * controles de 16.4. Registrar los rechazos no es opcional por tres
 * razones distintas, y conviene tenerlas presentes:
 *
 *  1. Seguridad: sin registro no hay forma de saber si el endpoint publico
 *     esta siendo atacado o simplemente nadie lo usa.
 *  2. Calibracion: los umbrales iniciales son estimaciones sin datos. Si
 *     estan rechazando gente legitima, tiene que verse en una tabla, no
 *     descubrirse por una queja.
 *  3. Investigacion: la tasa de rechazos es un indicador del piloto y se
 *     reporta tal cual salga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_abuse_rejections', function (Blueprint $table) {
            $table->id();

            // Nullable: un bot puede intentar crear sin aula valida.
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('category_id')->nullable()
                ->constrained('incident_categories')->nullOnDelete();

            $table->enum('reason', [
                'duplicate',
                'room_active_limit',
                'device_rate',
                'ip_rate',
                'similarity',
                'bot_signal',
                'unconfirmed',
            ]);

            $table->string('ip_hash', 64)->nullable();
            $table->string('device_key', 64)->nullable();

            // Fragmento del intento, acotado: suficiente para investigar,
            // insuficiente para acumular datos que no necesitamos.
            $table->string('payload_excerpt', 255)->nullable();

            // Si el ticket existente al que se le ofrecio sumarse.
            $table->foreignId('related_incident_id')->nullable()
                ->constrained('incidents')->nullOnDelete();

            $table->timestamp('occurred_at');

            $table->index('occurred_at');
            $table->index(['room_id', 'occurred_at']);
            $table->index(['reason', 'occurred_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at');

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        // Encuesta de facilidad de uso. Opcional y omitible a proposito:
        // el plan advierte contra perjudicar la experiencia por medir.
        Schema::create('satisfaction_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->unique()->constrained('incidents')->cascadeOnDelete();
            $table->unsignedTinyInteger('ease_score');
            $table->string('comment', 500)->nullable();
            $table->timestamp('answered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_responses');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('incident_abuse_rejections');
    }
};
