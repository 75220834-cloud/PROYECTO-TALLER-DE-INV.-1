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

        // Los arboles de diagnostico reales tambien son estructura
        // operativa: sin ellos, en el servidor, el docente elige su
        // problema y el sistema le contesta que no hay procedimiento.
        // Van despues del catalogo porque se enlazan a sus categorias.
        $this->call(PilotDiagnosticsSeeder::class);

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

        // Las imagenes se resuelven por su clave de componente al mostrar
        // cada paso, asi que no importa que lleguen despues de los arboles.
        //
        // DemoDiagnosticsSeeder ya no se llama: escribia arboles DEMO sobre
        // Proyector y Parlante y pisaba a los reales. Sigue existiendo
        // porque las pruebas lo usan como escenario controlado.
        $this->call(DemoMediaSeeder::class);
    }
}
