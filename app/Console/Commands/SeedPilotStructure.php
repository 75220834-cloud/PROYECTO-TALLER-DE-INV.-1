<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\PilotStructureSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deja la base lista para probar el sistema a la escala del piloto.
 *
 * Purga primero y siembra después, y ese orden no es capricho: si quedara la
 * sede de demostración anterior, el docente vería una pantalla extra para
 * elegir entre dos sedes. Justo la pantalla que el sistema omite cuando solo
 * hay una, y justo la que hay que poder probar tal como será en el aula.
 *
 * Los datos que siembra son APROXIMADOS y quedan marcados como demostración.
 * Ver `PilotStructureSeeder` para qué parte es real y qué parte no.
 */
class SeedPilotStructure extends Command
{
    protected $signature = 'piloto:sembrar {--force : No pedir confirmación}';

    protected $description = 'Purga los datos demo y siembra la estructura aproximada del piloto';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Esto no se ejecuta en producción: sembraría aulas aproximadas en el servidor del piloto.');

            return self::FAILURE;
        }

        $this->call('demo:purge', ['--force' => $this->option('force')]);

        // Si el usuario canceló la purga, quedan datos demo y sembrar encima
        // reintroduciría la segunda sede. Mejor parar y decirlo.
        if (DB::table('sites')->where('is_demo', true)->exists()) {
            $this->warn('Quedan datos de demostración sin purgar; no se sembró nada.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => PilotStructureSeeder::class, '--force' => true]);

        $this->newLine();
        $this->line('Listo. Entra en <options=bold>/reportar</> para recorrer el flujo del docente.');

        return self::SUCCESS;
    }
}
