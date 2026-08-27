<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Catalogos y roles: estructura operativa, NO son datos demo.
        // Se siembran siempre, en cualquier entorno.
        $this->call(RoleSeeder::class);
        $this->call(CatalogSeeder::class);

        // Datos DEMO: bloqueados en produccion salvo bandera explicita
        // (plan 21). Sembrar aulas ficticias en el servidor del piloto
        // contaminaria los datos de la investigacion, que es exactamente
        // el error que el plan prohibe.
        if (app()->environment('production') && ! config('incidencias.allow_demo_seed')) {
            $this->command->warn('  Datos DEMO omitidos: entorno de produccion.');

            return;
        }

        $this->call(DemoUsersSeeder::class);
        $this->call(DemoLocationsSeeder::class);

        // El banco visual va ANTES de los arboles: los pasos se enlazan a
        // las imagenes por su clave de componente.
        $this->call(DemoMediaSeeder::class);
        $this->call(DemoDiagnosticsSeeder::class);
    }
}
