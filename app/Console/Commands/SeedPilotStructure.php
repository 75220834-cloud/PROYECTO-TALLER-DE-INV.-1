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
    protected $signature = 'piloto:sembrar';

    protected $description = 'Purga los datos demo y siembra la estructura aproximada del piloto';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Esto no se ejecuta en producción: sembraría aulas aproximadas en el servidor del piloto.');

            return self::FAILURE;
        }

        $aulas = DB::table('rooms')->where('is_demo', true)->count();

        /*
         * NO se pregunta, y aquí sí es la decisión correcta aunque borre.
         *
         * Este comando solo toca datos de demostración: lo que Brayan
         * importe de verdad no lleva la marca y sobrevive. Además es
         * reversible —basta volver a ejecutarlo— y está bloqueado en
         * producción unas líneas más arriba. Una pregunta aquí solo añadiría
         * un paso que se contesta sin leer.
         *
         * (Y en la práctica, el prompt interactivo rompía el comando en la
         * consola de Windows de este equipo.)
         */
        if ($aulas > 0) {
            $this->warn("Se borrarán {$aulas} aulas de demostración y todo lo asociado.");
            $this->line('Los datos reales que hayas importado NO se tocan.');
            $this->newLine();
        }

        $this->call('demo:purge', ['--force' => true]);

        // Si algo impidió la purga, sembrar encima reintroduciría la segunda
        // sede y el docente vería una pantalla que en el aula no existirá.
        if (DB::table('sites')->where('is_demo', true)->exists()) {
            $this->error('Quedaron datos de demostración sin purgar; no se sembró nada.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => PilotStructureSeeder::class, '--force' => true]);

        $this->newLine();
        $this->line('Listo. Entra en <options=bold>/reportar</> para recorrer el flujo del docente.');

        return self::SUCCESS;
    }
}
