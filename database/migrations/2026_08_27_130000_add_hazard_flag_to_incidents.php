<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de riesgo fisico reportado (plan 17.5).
 *
 * Se guarda como columna propia y no se deduce del texto cada vez que hace
 * falta. Dos razones: soporte tiene que poder filtrar su bandeja por esto sin
 * escanear descripciones, y la deteccion es una decision que el sistema tomo
 * en un momento concreto — si manana se ajusta la lista de terminos, los
 * tickets antiguos deben conservar lo que se decidio entonces, no lo que se
 * decidiria hoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->boolean('hazard_reported')->default(false)->after('blocks_class');
            $table->string('hazard_term', 60)->nullable()->after('hazard_reported');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['hazard_reported', 'hazard_term']);
        });
    }
};
