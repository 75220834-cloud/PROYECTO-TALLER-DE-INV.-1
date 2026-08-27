<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario de equipos por aula (plan 16 del prompt, 11.3 del plan).
 *
 * equipment.room_id es NOT NULL con RESTRICT: es imposible registrar un
 * equipo en un aula inexistente, e imposible borrar un aula que todavia
 * tiene equipos. La integridad la garantiza la base, no la buena voluntad
 * del codigo que escriba encima.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);

            // Un problema de proyector nace clasificado como proyector.
            $table->foreignId('default_category_id')->nullable()
                ->constrained('incident_categories')->nullOnDelete();

            // Imagen generica del tipo: ultimo escalon de la cascada de
            // resolucion visual antes de caer a solo texto (plan 13.6).
            $table->foreignId('media_asset_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();

            // NOT NULL: no existe equipo sin aula (plan 26).
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('equipment_type_id')->constrained('equipment_types')->restrictOnDelete();

            $table->string('asset_code', 60)->unique();
            $table->string('brand', 80)->nullable();
            $table->string('model', 80)->nullable();

            // Nullable y sin unique: no todo equipo tiene serie legible, y
            // forzar unicidad sobre un campo que suele venir vacio o mal
            // transcrito bloquearia la carga de datos reales.
            $table->string('serial_number', 120)->nullable();

            $table->enum('status', ['operational', 'degraded', 'out_of_service', 'in_maintenance'])
                ->default('operational');

            $table->date('commissioned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['room_id', 'is_active']);
            $table->index(['equipment_type_id', 'status']);
            $table->index('serial_number');
        });

        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->enum('type', ['preventive', 'corrective'])->default('preventive');
            $table->timestamp('performed_at');
            $table->string('performed_by', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // "Dias desde el ultimo mantenimiento" es una variable del
            // modelo de riesgo (plan 15.3); este indice la hace barata.
            $table->index(['equipment_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('equipment_types');
    }
};
