<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Media\Models\MediaAsset;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoDiagnosticsSeeder;
use Database\Seeders\DemoMediaSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Panel del banco de imágenes (CU-A-13, A-14 y A-15).
 *
 * Es la pantalla por la que entra el apoyo visual del diagnóstico. Hasta
 * ahora solo podía llenarse desde un seeder, así que la parte que hace
 * utilizable el sistema para un docente que no sabe qué es un HDMI dependía
 * de que alguien tocara código.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(CatalogSeeder::class);

    Storage::fake(config('incidencias.media.disk'));

    $this->gestor = User::factory()->create();
    $this->gestor->assignRole('knowledge_manager');

    $this->tecnico = User::factory()->create();
    $this->tecnico->assignRole('technician');

    $this->campos = [
        'code' => 'PUERTO-HDMI-01',
        'title' => 'Puerto HDMI del proyector',
        'alt_text' => 'Primer plano del puerto HDMI en el panel del proyector',
        'source' => 'Fotografía propia — aula C305',
        'license' => 'Propia',
        'scope_type' => 'component',
        'scope_key' => 'hdmi_port',
    ];
});

it('el gestor de conocimiento puede subir una imagen', function () {
    $this->actingAs($this->gestor)->post(route('admin.media.store'), $this->campos + [
        'image' => UploadedFile::fake()->image('hdmi.jpg', 1600, 900),
    ])->assertRedirect(route('admin.media.index'));

    $asset = MediaAsset::where('code', 'PUERTO-HDMI-01')->firstOrFail();

    expect($asset->scope_key)->toBe('hdmi_port')
        ->and($asset->is_demo)->toBeFalse()
        ->and($asset->created_by)->toBe($this->gestor->id);

    Storage::disk(config('incidencias.media.disk'))->assertExists($asset->file_path);
});

it('un técnico no puede tocar el banco de imágenes', function () {
    $this->actingAs($this->tecnico)->get(route('admin.media.index'))->assertForbidden();
});

it('EXIGE texto alternativo, fuente y licencia', function () {
    $sinObligatorios = collect($this->campos)->except(['alt_text', 'source', 'license'])->all();

    $this->actingAs($this->gestor)->post(route('admin.media.store'), $sinObligatorios + [
        'image' => UploadedFile::fake()->image('hdmi.jpg'),
    ])->assertSessionHasErrors(['alt_text', 'source', 'license']);

    // Sin procedencia no se puede responder quién tomó la imagen ni con qué
    // permiso; sin alt_text es inaccesible y además inútil si no carga.
    expect(MediaAsset::count())->toBe(0);
});

it('RECHAZA los SVG por subida', function () {
    // Un SVG puede contener JavaScript ejecutable y servirlo sería un XSS de
    // manual (plan 16.2).
    $this->actingAs($this->gestor)->post(route('admin.media.store'), $this->campos + [
        'image' => UploadedFile::fake()->create('diagrama.svg', 10, 'image/svg+xml'),
    ])->assertSessionHasErrors('image');

    expect(MediaAsset::count())->toBe(0);
});

it('reprocesa la imagen en lugar de servir el archivo original', function () {
    $this->actingAs($this->gestor)->post(route('admin.media.store'), $this->campos + [
        'image' => UploadedFile::fake()->image('hdmi.jpg', 4000, 3000),
    ]);

    $asset = MediaAsset::firstOrFail();

    // El reprocesado es lo que elimina los metadatos EXIF —incluida la
    // ubicación donde se tomó la foto— y limita el peso servido al docente.
    expect($asset->width)->toBeLessThanOrEqual((int) config('incidencias.media.max_width'))
        ->and($asset->file_path)->not->toContain('hdmi.jpg')
        ->and($asset->mime_type)->toBe('image/webp');
});

it('no permite dos imágenes con el mismo código', function () {
    $this->actingAs($this->gestor)->post(route('admin.media.store'), $this->campos + [
        'image' => UploadedFile::fake()->image('a.jpg'),
    ]);

    $this->actingAs($this->gestor)->post(route('admin.media.store'), $this->campos + [
        'image' => UploadedFile::fake()->image('b.jpg'),
    ])->assertSessionHasErrors('code');

    expect(MediaAsset::count())->toBe(1);
});

it('permite ocultar una imagen sin borrarla', function () {
    $this->actingAs($this->gestor)->post(route('admin.media.store'), $this->campos + [
        'image' => UploadedFile::fake()->image('a.jpg'),
    ]);

    $asset = MediaAsset::firstOrFail();

    $this->actingAs($this->gestor)->patch(route('admin.media.toggle', $asset))->assertRedirect();

    // Ocultar y borrar no son lo mismo: una imagen retirada puede haber
    // ilustrado pasos de incidencias ya cerradas que la investigación
    // analizará después.
    expect($asset->fresh()->is_active)->toBeFalse()
        ->and(MediaAsset::count())->toBe(1);
});

it('deja constancia en la auditoría de quién subió cada imagen', function () {
    $this->actingAs($this->gestor)->post(route('admin.media.store'), $this->campos + [
        'image' => UploadedFile::fake()->image('a.jpg'),
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->gestor->id,
        'action' => 'media.created',
    ]);
});

it('el informe de cobertura enseña los pasos que no muestran su pieza', function () {
    $this->seed(DemoMediaSeeder::class);
    $this->seed(DemoDiagnosticsSeeder::class);

    // Se retira la imagen de un componente: el paso que la usaba queda al
    // descubierto y debe aparecer en el informe.
    MediaAsset::where('scope_type', 'component')->first()?->update(['is_active' => false]);

    $this->actingAs($this->gestor)->get(route('admin.media.coverage'))
        ->assertOk()
        ->assertSee('Falta la imagen de');
});

it('cuenta como cubierto un paso que hereda la imagen del componente', function () {
    $this->seed(DemoMediaSeeder::class);
    $this->seed(DemoDiagnosticsSeeder::class);

    // Mirar solo las imágenes asignadas paso a paso daría por descubierto
    // todo lo que se apoya en la imagen genérica del componente, que es el
    // caso normal.
    $this->actingAs($this->gestor)->get(route('admin.media.index'))
        ->assertOk()
        ->assertSee('Cobertura visual');
});
