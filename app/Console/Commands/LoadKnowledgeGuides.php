<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Knowledge\Jobs\ProcessDocumentVersion;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Carga las guias tecnicas del proyecto en la base de conocimiento.
 *
 * QUE SON ESTAS GUIAS
 *
 * Las redacto el equipo del proyecto porque soporte no tenia procedimientos
 * escritos. Cada archivo lo dice en su primera linea: son tecnicamente
 * correctas y verificables, pero no son normativa institucional. Esa
 * advertencia viaja DENTRO del texto, no solo en la base de datos, porque el
 * archivo se puede descargar y circular por correo, y un procedimiento suelto
 * sin origen se aplica como si fuera oficial.
 *
 * POR QUE SE PUBLICAN DIRECTAMENTE
 *
 * Un documento en borrador no alimenta al asistente. Mientras no haya nada
 * publicado, el asistente responde "no lo se" a todo y no se puede probar
 * nada — que es exactamente la situacion de la que este comando saca al
 * sistema. Se publican, y queda escrito en cada uno que estan pendientes de
 * revision.
 *
 * IDEMPOTENTE. Volver a ejecutarlo no duplica: si el archivo no cambio, la
 * huella coincide y se salta. Si cambio, sube una version nueva y conserva la
 * anterior, porque las incidencias atendidas bajo la version antigua tienen
 * que seguir siendo interpretables contra ella.
 */
class LoadKnowledgeGuides extends Command
{
    protected $signature = 'conocimiento:cargar
        {--ruta=database/conocimiento : Carpeta con las guías en Markdown}';

    protected $description = 'Carga las guías técnicas del proyecto y las manda a indexar';

    /**
     * Archivo => categoria a la que se acota.
     *
     * Acotar por categoria hace que al buscar sobre un problema de audio no
     * se recorra el manual del proyector. Un documento sin categoria se
     * busca siempre, que es lo correcto para lo general y un desperdicio
     * para lo especifico.
     *
     * @var array<string, string>
     */
    private const CATEGORIES = [
        'computadora.md' => 'COMPUTER',
        'proyector.md' => 'PROJECTOR',
        'hdmi.md' => 'HDMI',
        'control-proyector.md' => 'PROJECTOR_REMOTE',
        'ecran.md' => 'SCREEN',
        'parlante.md' => 'SPEAKER',
        'mouse.md' => 'MOUSE',
        'teclado.md' => 'KEYBOARD',
    ];

    public function handle(): int
    {
        $dir = base_path((string) $this->option('ruta'));

        if (! is_dir($dir)) {
            $this->error('No existe la carpeta: '.$dir);

            return self::FAILURE;
        }

        $categories = IncidentCategory::query()->pluck('id', 'code');
        $disk = Storage::disk(config('incidencias.media.disk'));
        $cargadas = 0;
        $saltadas = 0;

        foreach (self::CATEGORIES as $file => $categoryCode) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;

            if (! is_readable($path)) {
                $this->warn('  Falta '.$file);

                continue;
            }

            $texto = (string) file_get_contents($path);
            $hash = hash('sha256', $texto);

            // El titulo sale del primer encabezado del propio archivo: asi no
            // hay dos sitios donde mantenerlo y no pueden discrepar.
            preg_match('/^#\s+(.+)$/m', $texto, $m);
            $titulo = trim($m[1] ?? pathinfo($file, PATHINFO_FILENAME));

            $document = KnowledgeDocument::updateOrCreate(
                ['title' => $titulo],
                [
                    'type' => 'guide',
                    'category_id' => $categories[$categoryCode] ?? null,
                    'summary' => 'Guía técnica del proyecto, pendiente de revisión por soporte.',
                    'status' => 'published',
                    'is_demo' => false,
                ]
            );

            if ($document->versions()->where('file_hash', $hash)->exists()) {
                $saltadas++;

                continue;
            }

            $stored = 'knowledge/'.Str::random(40).'.md';
            $disk->put($stored, $texto);

            $version = KnowledgeDocumentVersion::create([
                'document_id' => $document->id,
                'version' => ((int) $document->versions()->max('version')) + 1,
                'file_path' => $stored,
                'original_name' => $file,
                'file_hash' => $hash,
                'mime_type' => 'text/markdown',
                'file_size' => strlen($texto),
                'processing_status' => 'pending',
            ]);

            ProcessDocumentVersion::dispatch($version->id);
            $cargadas++;

            $this->line(sprintf('  %-24s v%d  %s', $file, $version->version, $titulo));
        }

        $this->newLine();
        $this->info(sprintf('%d guías cargadas, %d sin cambios.', $cargadas, $saltadas));

        if ($cargadas > 0) {
            // La indexacion va en cola porque vectorizar tarda. Si nadie la
            // ejecuta, los documentos se quedan en "pendiente" y el asistente
            // no los encuentra: no da error, solo silencio.
            $this->comment('Ejecuta «php artisan queue:work» para que se indexen.');
        }

        return self::SUCCESS;
    }
}
