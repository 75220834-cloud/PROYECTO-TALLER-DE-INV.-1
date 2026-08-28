<?php

use App\Console\Commands\ImportCatalog;
use App\Console\Commands\PurgeDemoData;
use App\Console\Commands\SeedPilotStructure;
use App\Http\Middleware\SecurityHeaders;
use App\Modules\Analytics\Console\BuildSnapshotsCommand;
use App\Modules\Incidents\Console\PurgeAbandonedDrafts;
use App\Modules\Risk\Console\ComputeRiskCommand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        PurgeAbandonedDrafts::class,
        ComputeRiskCommand::class,
        BuildSnapshotsCommand::class,
        PurgeDemoData::class,
        SeedPilotStructure::class,
        ImportCatalog::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // En TODAS las respuestas, no solo en el panel: la parte publica es la
        // que recibe entrada de un usuario sin autenticar (plan 16.2).
        $middleware->append(SecurityHeaders::class);

        /*
         * A donde va alguien YA autenticado que abre /entrar.
         *
         * Por defecto Laravel lo manda a `/`, que en este sistema es la
         * puerta del DOCENTE: el tecnico pulsaba «Entrar» y acababa en
         * «elige tu pabellon», sin entender por que. Aqui la raiz no es una
         * pagina de inicio comun para todos — hay dos aplicaciones muy
         * distintas en el mismo dominio, y quien tiene cuenta pertenece a la
         * de dentro.
         */
        $middleware->redirectUsersTo('/panel');

        // Y al reves: quien pierde la sesion vuelve al formulario de acceso,
        // no a la pantalla del docente.
        $middleware->redirectGuestsTo('/entrar');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
