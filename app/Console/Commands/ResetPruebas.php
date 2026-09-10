<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deja el contador en cero sin perder el catalogo.
 *
 * QUE BORRA Y QUE NO
 *
 * Borra todo lo que genera el USO del sistema: incidencias, sus eventos,
 * mensajes, encuestas, rechazos del antiabuso, respuestas del diagnostico,
 * conversaciones con el asistente y señales de riesgo.
 *
 * NO toca nada de lo que costo trabajo cargar: aulas, equipos, guias,
 * procedimientos, imagenes, usuarios ni catalogos. Ese es exactamente el
 * punto — entre una prueba y otra hay que poder empezar de cero sin volver a
 * importar el checklist ni resubir los manuales.
 *
 * POR QUE NO REUTILIZA demo:purge
 *
 * Son cosas distintas. `demo:purge` elimina los datos MARCADOS como falsos y
 * deja los reales; este borra la ACTIVIDAD y deja la configuracion. Al
 * probar con datos reales lo que sobra es la actividad, y esa no esta
 * marcada como demo: es indistinguible de la de verdad. Por eso hace falta
 * un comando propio.
 *
 * EXIGE --force. La version interactiva se responde con Enter sin leerla, y
 * este comando borra datos que no se pueden recuperar.
 */
class ResetPruebas extends Command
{
    protected $signature = 'pruebas:reiniciar {--force : Confirmar el borrado}';

    protected $description = 'Borra la actividad de prueba y conserva aulas, equipos y manuales';

    /**
     * En orden: primero lo que apunta a incidencias, al final las
     * incidencias. Al reves, las claves foraneas lo impiden — y hacen bien.
     *
     * @var list<string>
     */
    private const TABLES = [
        'retrieval_citations',
        'assistant_interactions',
        'diagnostic_answers',
        'incident_abuse_rejections',
        'satisfaction_responses',
        'incident_messages',
        'incident_events',
        'incidents',
        'risk_scores',
        'metric_snapshots',
        'notifications',
        'audit_logs',
    ];

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('Esto borra TODA la actividad registrada y no se puede deshacer.');
            $this->newLine();
            $this->line('  Se borran: incidencias, encuestas, conversaciones con el asistente,');
            $this->line('             respuestas del diagnóstico, rechazos del antiabuso,');
            $this->line('             señales de riesgo, avisos y auditoría.');
            $this->newLine();
            $this->line('  Se conservan: aulas, equipos, guías, procedimientos, imágenes,');
            $this->line('                usuarios y catálogos.');
            $this->newLine();
            $this->error('Añade --force para ejecutarlo de verdad.');

            return self::FAILURE;
        }

        $borrado = [];

        DB::transaction(function () use (&$borrado): void {
            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $n = DB::table($table)->count();

                if ($n > 0) {
                    DB::table($table)->delete();
                    $borrado[$table] = $n;
                }
            }
        });

        if ($borrado === []) {
            $this->info('No había nada que borrar: el sistema ya estaba limpio.');

            return self::SUCCESS;
        }

        foreach ($borrado as $table => $n) {
            $this->line(sprintf('  %-28s %6d', $table, $n));
        }

        $this->newLine();
        $this->info('Listo. El catálogo y los manuales siguen cargados.');

        // Lo que queda en pie, para que quien ejecute esto lo vea y no tenga
        // que ir a comprobarlo por su cuenta.
        $this->newLine();
        $this->line(sprintf(
            '  Siguen cargados: %d aulas, %d equipos, %d guías, %d procedimientos.',
            DB::table('rooms')->count(),
            DB::table('equipment')->count(),
            DB::table('knowledge_documents')->count(),
            DB::table('diagnostic_flows')->where('is_active', true)->count(),
        ));

        return self::SUCCESS;
    }
}
