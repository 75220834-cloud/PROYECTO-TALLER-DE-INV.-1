<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Knowledge\Models\KnowledgeDocument;
use App\Modules\Knowledge\Models\KnowledgeDocumentVersion;
use App\Modules\Knowledge\Services\KnowledgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administración de la base de conocimiento (plan 41).
 *
 * El flujo que exige el plan es visible de principio a fin:
 *
 *   documento -> validación -> procesamiento -> indexación -> disponible
 *
 * El estado de cada etapa se muestra en la lista. Quien sube un documento
 * tiene que poder ver en qué punto está y por qué falló si falló, sin
 * pedirle a nadie que mire los logs.
 */
class KnowledgeController extends Controller
{
    public function __construct(private readonly KnowledgeService $knowledge) {}

    public function index(Request $request): View
    {
        $documents = KnowledgeDocument::query()
            ->with(['category', 'versions'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.knowledge.index', [
            'documents' => $documents,
            'categories' => IncidentCategory::active()->orderBy('sort_order')->get(),
            'types' => KnowledgeDocument::typeLabels(),
            'statusLabels' => KnowledgeDocumentVersion::statusLabels(),
        ]);
    }

    public function create(): View
    {
        return view('admin.knowledge.form', [
            'document' => new KnowledgeDocument(['status' => 'draft']),
            'categories' => IncidentCategory::active()->orderBy('sort_order')->get(),
            'types' => KnowledgeDocument::typeLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, requireFile: true);

        $document = KnowledgeDocument::create([
            'title' => $data['title'],
            'type' => $data['type'],
            'category_id' => $data['category_id'] ?? null,
            'summary' => $data['summary'] ?? null,
            'status' => 'draft',
            'created_by' => $request->user()->id,
            'is_demo' => false,
        ]);

        try {
            $this->knowledge->addVersion($document, $request->file('document'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('admin.knowledge.show', $document)
            ->with('status', 'Documento cargado. Se está procesando en segundo plano.');
    }

    public function show(KnowledgeDocument $document): View
    {
        $document->load(['category', 'versions', 'creator']);

        return view('admin.knowledge.show', [
            'document' => $document,
            'statusLabels' => KnowledgeDocumentVersion::statusLabels(),
        ]);
    }

    public function edit(KnowledgeDocument $document): View
    {
        return view('admin.knowledge.form', [
            'document' => $document,
            'categories' => IncidentCategory::active()->orderBy('sort_order')->get(),
            'types' => KnowledgeDocument::typeLabels(),
        ]);
    }

    /** Sube una versión nueva del mismo documento. */
    public function addVersion(Request $request, KnowledgeDocument $document): RedirectResponse
    {
        $request->validate([
            'document' => ['required', 'file', 'max:20480', Rule::file()->extensions(['pdf', 'docx', 'doc', 'txt', 'md'])],
        ], [
            'document.required' => 'Selecciona el archivo.',
            'document.max' => 'El archivo no puede pesar más de 20 MB.',
        ]);

        try {
            $this->knowledge->addVersion($document, $request->file('document'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Versión nueva cargada. Se está procesando.');
    }

    public function update(Request $request, KnowledgeDocument $document): RedirectResponse
    {
        $data = $this->validated($request, requireFile: false);

        $document->update([
            'title' => $data['title'],
            'type' => $data['type'],
            'category_id' => $data['category_id'] ?? null,
            'summary' => $data['summary'] ?? null,
        ]);

        return redirect()->route('admin.knowledge.show', $document)->with('status', 'Documento actualizado.');
    }

    public function publish(KnowledgeDocument $document): RedirectResponse
    {
        // Publicar un documento cuya indexación falló haría que el asistente
        // pareciera no saber algo que sí está cargado. Mejor decirlo aquí.
        if (! $document->currentVersion()?->isIndexed()) {
            return back()->with('error', 'No se puede publicar: el documento todavía no está indexado.');
        }

        $this->knowledge->publish($document);

        return back()->with('status', 'Documento publicado. Ya está disponible para el asistente.');
    }

    public function unpublish(KnowledgeDocument $document): RedirectResponse
    {
        $this->knowledge->unpublish($document);

        return back()->with('status', 'Documento despublicado.');
    }

    public function archive(KnowledgeDocument $document): RedirectResponse
    {
        $this->knowledge->archive($document);

        return back()->with('status', 'Documento archivado. Deja de alimentar al asistente, pero se conserva.');
    }

    public function reindex(KnowledgeDocumentVersion $version): RedirectResponse
    {
        $this->knowledge->reindex($version);

        return back()->with('status', 'Reindexación en cola.');
    }

    /**
     * Descarga del archivo original.
     *
     * Por controlador y no desde el directorio público: los documentos de
     * soporte pueden contener información interna y solo debe verlos quien
     * tiene permiso (plan 16.2).
     */
    public function download(KnowledgeDocumentVersion $version): StreamedResponse
    {
        $disk = Storage::disk(config('incidencias.media.disk'));

        abort_unless($disk->exists($version->file_path), 404);

        return $disk->download($version->file_path, $version->original_name);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $requireFile): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(array_keys(KnowledgeDocument::typeLabels()))],
            'category_id' => ['nullable', 'integer', Rule::exists('incident_categories', 'id')],
            'summary' => ['nullable', 'string', 'max:1000'],
        ];

        if ($requireFile) {
            // Extensiones explícitas: `file` por sí solo aceptaría cualquier
            // cosa, incluido un ejecutable renombrado.
            $rules['document'] = [
                'required', 'file', 'max:20480',
                Rule::file()->extensions(['pdf', 'docx', 'doc', 'txt', 'md']),
            ];
        }

        return $request->validate($rules, [
            'title.required' => 'El título es obligatorio.',
            'document.required' => 'Selecciona el archivo.',
            'document.max' => 'El archivo no puede pesar más de 20 MB.',
        ]);
    }
}
