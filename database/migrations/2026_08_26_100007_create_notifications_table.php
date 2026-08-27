<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos internos para el personal de soporte (plan 14).
 *
 * Estructura estandar de Laravel para poder usar el sistema de
 * notificaciones del framework mas adelante sin migrar datos.
 *
 * Este es el UNICO canal del MVP y no depende de nada externo: funciona
 * con la salida a Internet bloqueada, que es requisito del plan (20).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 191);
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Bandeja del tecnico: sus avisos sin leer, lo mas reciente
            // primero. Es la consulta que se ejecuta en cada carga del panel.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
