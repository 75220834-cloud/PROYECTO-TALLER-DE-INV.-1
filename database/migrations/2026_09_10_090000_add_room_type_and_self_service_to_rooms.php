<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de ambiente y modo de atencion de cada aula.
 *
 * Salen del checklist real de soporte, que distingue AULA, LABORATORIO y
 * HYFLEX. Son dos columnas y no una a proposito, porque responden cosas
 * distintas:
 *
 *   room_type     QUE es el ambiente. Es dato del inventario y se queda
 *                 como esta aunque cambien las reglas.
 *
 *   self_service  SI el docente puede intentar resolverlo por su cuenta.
 *                 Es una REGLA operativa. Hoy es falsa en las aulas HYFLEX
 *                 porque sus fallos son de software y el proyecto solo
 *                 aborda hardware, pero eso puede cambiar sin que el aula
 *                 deje de ser HYFLEX.
 *
 * Si se hubiera modelado con una sola columna, apagar el autoservicio en un
 * aula concreta obligaria a mentir sobre su tipo, y el inventario dejaria de
 * coincidir con el checklist del que salio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->string('room_type', 30)->default('aula')->after('name');

            $table->boolean('self_service')->default(true)->after('criticality');

            // El motivo se muestra tal cual al docente. Si el sistema le dice
            // que no puede intentarlo solo, tiene derecho a saber por que; un
            // "no disponible" a secas se lee como una averia del sistema.
            $table->string('support_only_reason', 200)->nullable()->after('self_service');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropColumn(['room_type', 'self_service', 'support_only_reason']);
        });
    }
};
