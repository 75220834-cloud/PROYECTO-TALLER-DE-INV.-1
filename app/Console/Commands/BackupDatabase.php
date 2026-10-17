<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Copia de seguridad de la base de datos.
 *
 * En esa base viven los datos de la investigacion: cada incidencia, cada
 * tiempo, cada encuesta. Una medicion de campo no se repite, y un disco
 * que falla sin copia se lleva el estudio con el.
 *
 * Se programa a diario y conserva las ultimas N copias. La carpeta es
 * configurable para que pueda apuntar a otro disco: una copia en el mismo
 * disco que la base protege de un error humano, no de una averia.
 *
 * LA CONTRASENA NO VA EN LA LINEA DE ORDENES
 *
 * Pasarla como argumento la expone en la lista de procesos de Windows. Se
 * escribe en un archivo temporal que mysqldump lee y que se borra al
 * terminar, pase lo que pase.
 */
final class BackupDatabase extends Command
{
    protected $signature = 'respaldo:crear';

    protected $description = 'Crea una copia de seguridad de la base de datos y conserva las más recientes';

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));
        $dir = (string) config('incidencias.respaldo.carpeta');
        $keep = max(1, (int) config('incidencias.respaldo.conservar'));

        File::ensureDirectoryExists($dir);

        // Hora local en el nombre, no UTC: quien busca «la copia de hoy» mira
        // el reloj de la pared. Con UTC, la copia de la 01:30 en Lima se
        // llamaria como si fuera del dia siguiente a las 06:30.
        $stamp = now(config('incidencias.display_timezone'))->format('Y-m-d_His');
        $file = $dir.DIRECTORY_SEPARATOR.'incidencias-'.$stamp.'.sql';
        $credentials = tempnam(sys_get_temp_dir(), 'resp');

        File::put($credentials, implode("\n", [
            '[client]',
            'user="'.addcslashes((string) $db['username'], '"\\').'"',
            'password="'.addcslashes((string) $db['password'], '"\\').'"',
            'host="'.$db['host'].'"',
            'port='.(int) $db['port'],
        ])."\n");

        try {
            $result = Process::timeout(600)->run([
                (string) config('incidencias.respaldo.mysqldump'),
                // Tiene que ser el PRIMER argumento: mysqldump lo ignora en
                // cualquier otra posicion.
                '--defaults-extra-file='.$credentials,
                // Copia coherente sin bloquear: los docentes pueden seguir
                // reportando mientras se hace.
                '--single-transaction',
                '--routines',
                '--default-character-set=utf8mb4',
                '--result-file='.$file,
                (string) $db['database'],
            ]);
        } finally {
            File::delete($credentials);
        }

        if ($result->failed()) {
            File::delete($file);
            $this->error('No se pudo crear la copia: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $this->info('Copia creada: '.$file);

        // Las mas antiguas fuera; el nombre lleva la fecha, asi que ordenar
        // por nombre es ordenar por antiguedad.
        $all = collect(File::glob($dir.DIRECTORY_SEPARATOR.'incidencias-*.sql'))->sort()->values();

        foreach ($all->slice(0, max(0, $all->count() - $keep)) as $old) {
            File::delete($old);
            $this->line('  Eliminada por antigua: '.basename($old));
        }

        return self::SUCCESS;
    }
}
