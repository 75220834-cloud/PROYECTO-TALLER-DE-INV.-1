<?php

declare(strict_types=1);

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Processors\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Administración del banco de imágenes (plan CU-A-13, A-14 y A-15).
 *
 * Es la pantalla por la que Brayan sube las fotos de los equipos reales.
 * Hasta ahora el banco solo podía llenarse desde un seeder, lo que dejaba
 * la asistencia visual —el motivo por el que el diagnóstico guiado es
 * utilizable por un docente que no sabe qué es un HDMI— dependiendo de que
 * alguien tocara código.
 *
 * TRES CAMPOS SON OBLIGATORIOS Y NO ES BUROCRACIA (plan 10.8):
 *
 *   alt_text   sin él la imagen es inaccesible y además inútil cuando no
 *              carga, que en una WiFi de aula pasa a menudo.
 *   source     de dónde salió. Sin procedencia registrada no se puede
 *              responder quién la tomó ni con qué permiso.
 *   license    bajo qué condiciones se puede usar. Una foto de manual de
 *              fabricante sin permiso es un problema legal, no un descuido.
 *
 * NO HAY GENERACIÓN DE IMÁGENES POR IA, y es una decisión de seguridad: un
 * modelo produce puertos con el número de pines equivocado y conectores que
 * no existen. Una imagen plausible pero incorrecta es PEOR que ninguna,
 * porque manda al docente a manipular el componente equivocado.
 */
class MediaLibraryController extends Controller
{
    public function __construct(
        private readonly ImageProcessor $processor,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $assets = MediaAsset::query()
            ->when($request->filled('scope'), fn ($q) => $q->where('scope_type', $request->string('scope')))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($sub) use ($request) {
                $term = '%'.$request->string('q').'%';
                $sub->where('code', 'like', $term)->orWhere('title', 'like', $term);
            }))
            ->orderBy('scope_type')
            ->orderBy('code')
            ->paginate(24)
            ->withQueryString();

        return view('admin.media.index', [
            'assets' => $assets,
            'scope' => $request->string('scope')->toString(),
            'q' => $request->string('q')->toString(),
            'coverage' => $this->coverage(),
        ]);
    }

    public function create(): View
    {
        return view('admin.media.form', ['asset' => new MediaAsset]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $file = $request->file('image');

        // El archivo se REPROCESA siempre, nunca se sirve el original: eso
        // elimina los metadatos EXIF —incluida la geolocalización de dónde
        // se tomó la foto— y neutraliza cargas maliciosas embebidas en una
        // imagen (plan 16.2).
        $stored = $this->processor->store($file, $data['code']);

        $asset = MediaAsset::create($data + $stored + [
            'type' => 'photo',
            'is_active' => true,
            'is_demo' => false,
            'created_by' => $request->user()?->id,
        ]);

        $this->audit->record('media.created', $asset, ['code' => $asset->code]);

        return redirect()->route('admin.media.index')
            ->with('status', "Imagen «{$asset->code}» cargada.");
    }

    public function edit(MediaAsset $asset): View
    {
        return view('admin.media.form', ['asset' => $asset]);
    }

    public function update(Request $request, MediaAsset $asset): RedirectResponse
    {
        $data = $this->validated($request, $asset);
        $stored = [];

        if ($request->hasFile('image')) {
            $stored = $this->processor->store($request->file('image'), $data['code']);

            // El archivo anterior se borra DESPUÉS de guardar el nuevo: si
            // el reprocesado falla, la imagen vieja sigue ahí y el paso de
            // diagnóstico no se queda sin ilustración.
            $this->processor->delete($asset->file_path, $asset->thumbnail_path);
        }

        $asset->update($data + $stored);

        $this->audit->record('media.updated', $asset, ['code' => $asset->code]);

        return redirect()->route('admin.media.index')
            ->with('status', "Imagen «{$asset->code}» actualizada.");
    }

    public function toggle(MediaAsset $asset): RedirectResponse
    {
        $asset->update(['is_active' => ! $asset->is_active]);

        $this->audit->record('media.toggled', $asset, ['is_active' => $asset->is_active]);

        return back()->with('status', $asset->is_active
            ? "«{$asset->code}» vuelve a mostrarse a los docentes."
            : "«{$asset->code}» ya no se muestra a los docentes.");
    }

    /**
     * Informe de cobertura visual (CU-A-15).
     *
     * Un paso que dice "revisa el cable HDMI" sin imagen es un defecto, no
     * una omisión menor: es exactamente el docente que no sabe qué es un
     * HDMI el que se queda sin poder hacer nada.
     */
    public function coverageReport(): View
    {
        return view('admin.media.coverage', ['pending' => $this->uncoveredSteps()]);
    }

    /** @return array{total: int, covered: int, percent: int} */
    private function coverage(): array
    {
        $total = DB::table('diagnostic_steps')->whereNotNull('component_key')->count();
        $pending = count($this->uncoveredSteps());

        return [
            'total' => $total,
            'covered' => $total - $pending,
            'percent' => $total === 0 ? 100 : (int) round((($total - $pending) / $total) * 100),
        ];
    }

    /**
     * Pasos que mencionan una pieza física y no resuelven ninguna imagen.
     *
     * Se comprueban las DOS vías de la cascada: imagen asignada al paso e
     * imagen del componente. Mirar solo la primera daría por descubierto
     * cualquier paso que se apoya en la imagen genérica del componente, que
     * es el caso normal.
     *
     * @return list<object>
     */
    private function uncoveredSteps(): array
    {
        return DB::table('diagnostic_steps as s')
            ->join('diagnostic_flow_versions as v', 'v.id', '=', 's.flow_version_id')
            ->join('diagnostic_flows as f', 'f.id', '=', 'v.flow_id')
            ->leftJoin('incident_categories as c', 'c.id', '=', 'f.category_id')
            ->whereNotNull('s.component_key')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('diagnostic_step_media as m')
                ->join('media_assets as a', 'a.id', '=', 'm.media_asset_id')
                ->whereColumn('m.step_id', 's.id')
                ->where('a.is_active', true))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('media_assets as a2')
                ->where('a2.scope_type', 'component')
                ->whereColumn('a2.scope_key', 's.component_key')
                ->where('a2.is_active', true))
            ->select('s.step_key as key', 's.prompt_text', 's.component_key', 'c.name as category')
            ->orderBy('s.step_key')
            ->get()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?MediaAsset $asset): array
    {
        $unique = 'unique:media_assets,code'.($asset !== null ? ",{$asset->id}" : '');

        return $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9\-]+$/', $unique],
            'title' => ['required', 'string', 'max:150'],

            // Los tres obligatorios del plan 10.8. Sin ellos no se publica.
            'alt_text' => ['required', 'string', 'max:255'],
            'source' => ['required', 'string', 'max:255'],
            'license' => ['required', 'string', 'max:120'],

            'caption' => ['nullable', 'string', 'max:255'],
            'attribution' => ['nullable', 'string', 'max:255'],
            'scope_type' => ['required', 'in:component,category,equipment_type,generic'],
            'scope_key' => ['nullable', 'string', 'max:80'],

            // Solo mapas de bits. Un SVG puede llevar JavaScript ejecutable y
            // servirlo sería un XSS de manual, así que no se acepta por
            // subida (plan 16.2).
            'image' => [$asset === null ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'code.regex' => 'El código solo admite mayúsculas, números y guiones. Por ejemplo: PUERTO-HDMI-01',
            'alt_text.required' => 'Describe la imagen: sin esto es inaccesible y además inútil si no carga.',
            'source.required' => 'Indica de dónde salió la imagen.',
            'license.required' => 'Indica bajo qué licencia se puede usar.',
            'image.mimes' => 'Solo JPG, PNG o WebP. Los SVG no se aceptan por seguridad.',
        ]);
    }
}
