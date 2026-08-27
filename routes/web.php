<?php

declare(strict_types=1);

use App\Modules\Analytics\Http\Controllers\DashboardController;
use App\Modules\Diagnostics\Http\Controllers\TeacherDiagnosticController;
use App\Modules\Equipment\Http\Controllers\EquipmentController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Incidents\Http\Controllers\SupportIncidentController;
use App\Modules\Incidents\Http\Controllers\TeacherIncidentController;
use App\Modules\Knowledge\Http\Controllers\KnowledgeController;
use App\Modules\Locations\Http\Controllers\BuildingController;
use App\Modules\Locations\Http\Controllers\FloorController;
use App\Modules\Locations\Http\Controllers\QrCodeController;
use App\Modules\Locations\Http\Controllers\RoomController;
use App\Modules\Locations\Http\Controllers\SiteController;
use App\Modules\Locations\Http\Controllers\TeacherLocationController;
use App\Modules\Media\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ZONA PUBLICA - Docente
|--------------------------------------------------------------------------
| Sin autenticacion: el docente escanea el QR generico y empieza (16.1).
|
| El limite de tasa es AMPLIO a proposito. Su unico objetivo es impedir que
| un tercero vuelque el catalogo completo de aulas de la universidad con un
| barrido automatico. No debe estorbar jamas a un docente real: en la WiFi
| institucional muchos comparten IP de salida, asi que un umbral estrecho
| bloquearia a usuarios legitimos (riesgo R18).
|
| La proteccion de verdad no esta aqui, sino en la creacion del ticket
| (16.4): mirar es gratis, movilizar a un tecnico no.
*/
Route::middleware('throttle:120,1')->group(function () {

    Route::get('/reportar', [TeacherLocationController::class, 'start'])->name('teacher.start');
    Route::get('/reportar/buscar', [TeacherLocationController::class, 'search'])->name('teacher.search');

    Route::get('/reportar/{site}/pabellones', [TeacherLocationController::class, 'buildings'])
        ->whereNumber('site')->name('teacher.buildings');

    Route::get('/reportar/{site}/{building}/pisos', [TeacherLocationController::class, 'floors'])
        ->whereNumber(['site', 'building'])->name('teacher.floors');

    Route::get('/reportar/{site}/{building}/{floor}/aulas', [TeacherLocationController::class, 'rooms'])
        ->whereNumber(['site', 'building', 'floor'])->name('teacher.rooms');

    Route::get('/reportar/{site}/{building}/{floor}/{room}/confirmar', [TeacherLocationController::class, 'confirm'])
        ->whereNumber(['site', 'building', 'floor', 'room'])->name('teacher.confirm');

    Route::post('/reportar/confirmar', [TeacherLocationController::class, 'store'])
        ->name('teacher.confirm.store');

    /*
     * Reporte de la incidencia. El borrador ya existe (se creo al confirmar
     * la ubicacion) y viaja por sesion, no por la URL: el identificador de
     * una incidencia no debe quedar en el historial del navegador de un
     * aula compartida.
     */
    Route::get('/reportar/problema', [TeacherIncidentController::class, 'chooseCategory'])->name('teacher.category');
    Route::post('/reportar/problema', [TeacherIncidentController::class, 'storeCategory'])->name('teacher.category.store');

    // Confirmacion de solucion: las tres opciones del plan (12).
    Route::get('/reportar/resultado', [TeacherIncidentController::class, 'outcome'])->name('teacher.outcome');
    Route::post('/reportar/resuelto', [TeacherIncidentController::class, 'markResolved'])->name('teacher.resolved');

    Route::get('/reportar/soporte', [TeacherIncidentController::class, 'escalateForm'])->name('teacher.escalate');
    Route::post('/reportar/soporte', [TeacherIncidentController::class, 'escalateStore'])->name('teacher.escalate.store');

    // Sumarse a un ticket ya abierto en lugar de crear otro (antiduplicados).
    Route::get('/reportar/sumarse/{uuid}', [TeacherIncidentController::class, 'joinForm'])->name('teacher.join');
    Route::post('/reportar/sumarse/{uuid}', [TeacherIncidentController::class, 'joinStore'])->name('teacher.join.store');

    // Diagnostico guiado paso a paso, con apoyo visual.
    Route::get('/reportar/diagnostico', [TeacherDiagnosticController::class, 'show'])
        ->name('teacher.diagnostic');
    Route::post('/reportar/diagnostico', [TeacherDiagnosticController::class, 'answer'])
        ->name('teacher.diagnostic.answer');

    // "¿Cuál es esa pieza?" — muestra el componente aislado.
    Route::get('/reportar/pieza/{componentKey}', [TeacherDiagnosticController::class, 'reference'])
        ->name('teacher.diagnostic.reference');

    Route::get('/reportar/listo/{uuid}', [TeacherIncidentController::class, 'done'])->name('teacher.done');

    // Imagenes del banco visual. Publicas a proposito: el docente no esta
    // autenticado y no hay nada sensible en el dibujo de un conector.
    Route::get('/imagen/{asset}', [MediaController::class, 'show'])
        ->whereNumber('asset')->name('media.show');
});

Route::redirect('/', '/reportar');

/*
|--------------------------------------------------------------------------
| AUTENTICACION - Personal interno
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'show'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/salir', [LoginController::class, 'destroy'])
    ->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| PANEL INTERNO - Soporte y administracion
|--------------------------------------------------------------------------
| Cada grupo declara el permiso que exige. Un tecnico no puede administrar
| catalogos: no es desconfianza, es que un cambio accidental en el catalogo
| de aulas afecta a TODOS los tickets, incluidos los ya cerrados que la
| investigacion va a analizar (plan RoleSeeder).
*/
Route::middleware('auth')->prefix('panel')->name('support.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('can:dashboard.view')->name('dashboard');

    // La exportacion es un acceso masivo a datos del piloto: permiso propio
    // y queda auditada.
    Route::get('/exportar', [DashboardController::class, 'export'])
        ->middleware('can:reports.export')->name('dashboard.export');

    Route::middleware('can:incidents.view')->group(function () {
        Route::get('/incidencias', [SupportIncidentController::class, 'index'])->name('incidents.index');
        Route::get('/incidencias/{incident}', [SupportIncidentController::class, 'show'])
            ->whereNumber('incident')->name('incidents.show');
    });

    Route::middleware('can:incidents.assign')->group(function () {
        Route::post('/incidencias/{incident}/asignar', [SupportIncidentController::class, 'assign'])
            ->whereNumber('incident')->name('incidents.assign');
        Route::post('/incidencias/{incident}/tomar', [SupportIncidentController::class, 'take'])
            ->whereNumber('incident')->name('incidents.take');
    });

    Route::middleware('can:incidents.update')->group(function () {
        Route::post('/incidencias/{incident}/estado', [SupportIncidentController::class, 'transition'])
            ->whereNumber('incident')->name('incidents.transition');
        Route::post('/incidencias/{incident}/resolver', [SupportIncidentController::class, 'resolve'])
            ->whereNumber('incident')->name('incidents.resolve');
    });

    Route::middleware('can:incidents.close')->group(function () {
        Route::post('/incidencias/{incident}/cerrar', [SupportIncidentController::class, 'close'])
            ->whereNumber('incident')->name('incidents.close');
        Route::post('/incidencias/{incident}/reabrir', [SupportIncidentController::class, 'reopen'])
            ->whereNumber('incident')->name('incidents.reopen');
    });

    Route::middleware('can:incidents.cancel')->group(function () {
        Route::post('/incidencias/{incident}/cancelar', [SupportIncidentController::class, 'cancel'])
            ->whereNumber('incident')->name('incidents.cancel');
    });
});

Route::middleware('auth')->prefix('panel')->name('admin.')->group(function () {

    Route::middleware('can:locations.manage')->group(function () {
        // El nombre del parametro debe coincidir con el de la firma del
        // controlador (Site $site, Room $room...): asi funciona el enlace
        // implicito de modelos de Laravel. Un {model} generico no enlazaria.
        foreach ([
            'sites' => [SiteController::class, 'site'],
            'buildings' => [BuildingController::class, 'building'],
            'floors' => [FloorController::class, 'floor'],
            'rooms' => [RoomController::class, 'room'],
        ] as $slug => [$controller, $param]) {
            Route::get("/{$slug}", [$controller, 'index'])->name("{$slug}.index");
            Route::get("/{$slug}/nuevo", [$controller, 'create'])->name("{$slug}.create");
            Route::post("/{$slug}", [$controller, 'store'])->name("{$slug}.store");
            Route::get("/{$slug}/{{$param}}/editar", [$controller, 'edit'])->whereNumber($param)->name("{$slug}.edit");
            Route::put("/{$slug}/{{$param}}", [$controller, 'update'])->whereNumber($param)->name("{$slug}.update");
            Route::delete("/{$slug}/{{$param}}", [$controller, 'destroy'])->whereNumber($param)->name("{$slug}.destroy");
            Route::patch("/{$slug}/{{$param}}/activar", [$controller, 'toggle'])->whereNumber($param)->name("{$slug}.toggle");
        }

        Route::get('/qr', [QrCodeController::class, 'show'])->name('qr.show');
        Route::get('/qr/cartel', [QrCodeController::class, 'poster'])->name('qr.poster');
        Route::get('/qr/descargar', [QrCodeController::class, 'download'])->name('qr.download');
    });

    Route::middleware('can:equipment.view')->group(function () {
        Route::get('/equipos', [EquipmentController::class, 'index'])->name('equipment.index');
    });

    /*
     * Base de conocimiento. Ver esta separado de gestionar: el tecnico
     * consulta los procedimientos, pero solo el gestor de conocimiento los
     * carga y publica. Un documento mal publicado cambia lo que el
     * asistente le dice a TODOS los docentes.
     */
    Route::middleware('can:knowledge.view')->group(function () {
        Route::get('/conocimiento', [KnowledgeController::class, 'index'])->name('knowledge.index');
        Route::get('/conocimiento/{document}', [KnowledgeController::class, 'show'])
            ->whereNumber('document')->name('knowledge.show');
        Route::get('/conocimiento/version/{version}/descargar', [KnowledgeController::class, 'download'])
            ->whereNumber('version')->name('knowledge.download');
    });

    Route::middleware('can:knowledge.manage')->group(function () {
        Route::get('/conocimiento/nuevo', [KnowledgeController::class, 'create'])->name('knowledge.create');
        Route::post('/conocimiento', [KnowledgeController::class, 'store'])->name('knowledge.store');
        Route::get('/conocimiento/{document}/editar', [KnowledgeController::class, 'edit'])
            ->whereNumber('document')->name('knowledge.edit');
        Route::put('/conocimiento/{document}', [KnowledgeController::class, 'update'])
            ->whereNumber('document')->name('knowledge.update');
        Route::post('/conocimiento/{document}/version', [KnowledgeController::class, 'addVersion'])
            ->whereNumber('document')->name('knowledge.version');
        Route::post('/conocimiento/{document}/publicar', [KnowledgeController::class, 'publish'])
            ->whereNumber('document')->name('knowledge.publish');
        Route::post('/conocimiento/{document}/despublicar', [KnowledgeController::class, 'unpublish'])
            ->whereNumber('document')->name('knowledge.unpublish');
        Route::post('/conocimiento/{document}/archivar', [KnowledgeController::class, 'archive'])
            ->whereNumber('document')->name('knowledge.archive');
        Route::post('/conocimiento/version/{version}/reindexar', [KnowledgeController::class, 'reindex'])
            ->whereNumber('version')->name('knowledge.reindex');
    });

    Route::middleware('can:equipment.manage')->group(function () {
        Route::get('/equipos/nuevo', [EquipmentController::class, 'create'])->name('equipment.create');
        Route::post('/equipos', [EquipmentController::class, 'store'])->name('equipment.store');
        Route::get('/equipos/{equipment}/editar', [EquipmentController::class, 'edit'])->whereNumber('equipment')->name('equipment.edit');
        Route::put('/equipos/{equipment}', [EquipmentController::class, 'update'])->whereNumber('equipment')->name('equipment.update');
    });
});
