<?php

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
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
