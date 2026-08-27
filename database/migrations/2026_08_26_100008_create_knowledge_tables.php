<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base de conocimiento y su índice de recuperación (plan 14).
 *
 * DOS DECISIONES QUE CONVIENE ENTENDER
 *
 * 1. Los DOCUMENTOS son la fuente de verdad; los fragmentos son caché
 *    derivado. Se puede borrar y reconstruir el índice entero desde los
 *    archivos originales. Eso simplifica el respaldo (basta con guardar los
 *    documentos) y hace que reindexar sea una operación segura.
 *
 * 2. El vector se guarda como JSON y la similitud se calcula en PHP.
 *    MariaDB 10.4 no tiene tipo VECTOR (supuesto S8 del plan, ya verificado
 *    en esta máquina). Para que eso sea viable, la búsqueda PREFILTRA en SQL
 *    y solo calcula el coseno sobre los candidatos: por eso hay índice
 *    FULLTEXT y por eso se precalcula la norma del vector.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);

            // manual | procedure | faq | guide | protocol | other
            $table->string('type', 40)->default('other');

            // Un documento acotado a una categoría se prefiltra mejor: al
            // buscar sobre un problema de audio no hace falta mirar el
            // manual del proyector.
            $table->foreignId('category_id')->nullable()
                ->constrained('incident_categories')->nullOnDelete();

            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->text('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id']);
        });

        Schema::create('knowledge_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');

            $table->string('file_path', 255);
            $table->string('original_name', 255);

            // SHA-256 del archivo: evita reprocesar el mismo documento dos
            // veces y detecta que alguien resubió exactamente lo mismo.
            $table->string('file_hash', 64);

            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');
            $table->unsignedSmallInteger('page_count')->nullable();

            // Estado de cada etapa de la ingesta. Es lo que el plan exige
            // mostrar en el panel: quien sube un documento tiene que poder
            // ver en qué punto está y por qué falló si falló.
            $table->enum('processing_status', [
                'pending', 'extracting', 'chunking', 'embedding', 'indexed', 'failed',
            ])->default('pending');

            $table->text('processing_error')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->unsignedSmallInteger('chunk_count')->default(0);
            $table->timestamps();

            $table->unique(['document_id', 'version']);
            $table->index('file_hash');
            $table->index('processing_status');
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_version_id')
                ->constrained('knowledge_document_versions')->cascadeOnDelete();

            $table->unsignedSmallInteger('chunk_index');
            $table->text('content');
            $table->unsignedSmallInteger('token_count')->default(0);

            // Metadatos de la cita exigida por el plan (42): el asistente
            // debe poder decir de dónde sacó la respuesta.
            $table->string('section_title', 255)->nullable();
            $table->unsignedSmallInteger('page_number')->nullable();

            // Vector como JSON. No es lo ideal, es lo que MariaDB 10.4
            // permite (ver cabecera).
            $table->json('embedding')->nullable();

            // Identificador del modelo que generó el vector. Cambiar de
            // modelo invalida TODOS los vectores anteriores; sin este campo
            // el sistema mezclaría vectores incompatibles y devolvería
            // resultados silenciosamente incorrectos, que es el fallo más
            // difícil de detectar de todo el módulo (plan 14.5).
            $table->string('embedding_model', 80)->nullable();

            // Norma precalculada: la del vector es constante, así que
            // recalcularla en cada consulta multiplicaría el coste del
            // coseno sin ninguna necesidad.
            $table->decimal('embedding_norm', 12, 8)->nullable();

            // Copia de la categoría del documento, para prefiltrar sin join.
            $table->foreignId('category_id')->nullable()
                ->constrained('incident_categories')->nullOnDelete();

            $table->timestamps();

            $table->index(['document_version_id', 'chunk_index']);
            $table->index(['category_id', 'document_version_id']);
            $table->index('embedding_model');
        });

        // FULLTEXT aparte: Laravel no lo expone en el Blueprint para MySQL.
        // Es lo que hace viable el prefiltrado (plan 14.3).
        Schema::getConnection()->statement(
            'ALTER TABLE knowledge_chunks ADD FULLTEXT INDEX knowledge_chunks_content_fulltext (content)'
        );

        Schema::create('assistant_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();

            $table->text('query');
            $table->string('intent', 60)->nullable();
            $table->text('response')->nullable();

            // La confianza y la decisión quedan registradas SIEMPRE, de modo
            // que la política de escalamiento sea auditable y se pueda
            // recalibrar a posteriori con datos reales (plan 13.5).
            $table->decimal('confidence_score', 4, 3)->nullable();
            $table->enum('confidence_band', ['high', 'medium', 'low'])->nullable();
            $table->boolean('escalated')->default(false);

            $table->string('llm_model', 80)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('token_usage')->nullable();
            $table->json('media_suggested')->nullable();
            $table->timestamps();

            $table->index(['incident_id', 'created_at']);
            $table->index(['confidence_band', 'escalated']);
        });

        // Qué fragmento sustentó cada respuesta. Sin esto no se puede
        // comprobar si el asistente citó de verdad o se lo inventó.
        Schema::create('retrieval_citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interaction_id')->constrained('assistant_interactions')->cascadeOnDelete();
            $table->foreignId('chunk_id')->constrained('knowledge_chunks')->cascadeOnDelete();
            $table->unsignedTinyInteger('rank');
            $table->decimal('score', 8, 6);
            $table->timestamps();

            $table->index(['interaction_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retrieval_citations');
        Schema::dropIfExists('assistant_interactions');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_document_versions');
        Schema::dropIfExists('knowledge_documents');
    }
};
