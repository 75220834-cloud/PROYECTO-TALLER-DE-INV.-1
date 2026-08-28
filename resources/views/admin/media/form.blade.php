@extends('layouts.app')
@section('title', $asset->exists ? 'Editar imagen' : 'Subir imagen')
@section('heading', $asset->exists ? 'Editar «' . $asset->code . '»' : 'Subir una imagen')
@section('subheading', 'Lo que verá el docente cuando el paso mencione esta pieza')

@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $asset->exists ? route('admin.media.update', $asset) : route('admin.media.store') }}"
      class="max-w-2xl space-y-5">
    @csrf
    @if ($asset->exists) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-md border border-danger bg-danger-container px-4 py-3 text-sm text-on-danger-container">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)<li>· {{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-lg border border-outline-variant bg-surface-lowest p-5">
        <label class="block text-sm font-medium text-on-surface-variant">
            Fotografía {{ $asset->exists ? '(dejar vacío para conservar la actual)' : '' }}
        </label>

        @if ($asset->exists)
            <img src="{{ route('media.show', $asset) }}" alt="{{ $asset->alt_text }}"
                 class="mt-2 max-h-48 rounded border border-outline-variant">
        @endif

        <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
               class="mt-2 block w-full text-sm">

        {{-- Se explica por qué se rechaza el SVG: sin el motivo, quien lo
             intente pensará que es un capricho del formulario. --}}
        <p class="mt-2 text-xs text-on-surface-variant">
            JPG, PNG o WebP, máximo 8 MB. Los SVG no se aceptan: pueden contener código ejecutable.
            La imagen se reprocesa al subirla, lo que elimina los metadatos EXIF —incluida la
            ubicación donde se tomó la foto—.
        </p>
    </div>

    <div class="grid gap-4 rounded-lg border border-outline-variant bg-surface-lowest p-5 sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Código</label>
            <input type="text" name="code" value="{{ old('code', $asset->code) }}" required
                   placeholder="PUERTO-HDMI-01"
                   class="mt-1 block w-full rounded-md border-outline-variant font-mono text-sm">
            <p class="mt-1 text-xs text-on-surface-variant">Mayúsculas, números y guiones.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Título</label>
            <input type="text" name="title" value="{{ old('title', $asset->title) }}" required
                   placeholder="Puerto HDMI del proyector"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
        </div>

        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-on-surface-variant">
                Descripción para quien no puede ver la imagen <span class="text-danger">*</span>
            </label>
            <input type="text" name="alt_text" value="{{ old('alt_text', $asset->alt_text) }}" required
                   placeholder="Primer plano del puerto HDMI en el panel trasero del proyector"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
            <p class="mt-1 text-xs text-on-surface-variant">
                Obligatorio. Es lo que se muestra si la imagen no carga, que en la WiFi de un aula
                pasa a menudo.
            </p>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-on-surface-variant">Pie de foto</label>
            <input type="text" name="caption" value="{{ old('caption', $asset->caption) }}"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
        </div>
    </div>

    <div class="grid gap-4 rounded-lg border border-outline-variant bg-surface-lowest p-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <p class="text-sm font-medium text-on-surface-variant">Procedencia</p>
            <p class="mt-1 text-xs text-on-surface-variant">
                Obligatoria. Sin saber de dónde salió una imagen no se puede responder quién la tomó
                ni con qué permiso, y una captura de manual de fabricante sin autorización es un
                problema legal, no un descuido.
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">De dónde salió <span class="text-danger">*</span></label>
            <input type="text" name="source" value="{{ old('source', $asset->source) }}" required
                   placeholder="Fotografía propia — aula C305"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Licencia <span class="text-danger">*</span></label>
            <input type="text" name="license" value="{{ old('license', $asset->license) }}" required
                   placeholder="Propia / CC0 / dominio público"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
        </div>

        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-on-surface-variant">Atribución</label>
            <input type="text" name="attribution" value="{{ old('attribution', $asset->attribution) }}"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
        </div>
    </div>

    <div class="grid gap-4 rounded-lg border border-outline-variant bg-surface-lowest p-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <p class="text-sm font-medium text-on-surface-variant">Dónde se usa</p>
            <p class="mt-1 text-xs text-on-surface-variant">
                Una imagen de <strong>componente</strong> cuya clave coincida con la del paso (por
                ejemplo <code>hdmi_port</code>) se muestra sola en todos los pasos que mencionen esa
                pieza, sin asignarla una por una.
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Ámbito</label>
            <select name="scope_type" class="mt-1 block w-full rounded-md border-outline-variant text-sm">
                @foreach (['component' => 'Componente', 'category' => 'Categoría', 'equipment_type' => 'Tipo de equipo', 'generic' => 'Genérica'] as $v => $l)
                    <option value="{{ $v }}" @selected(old('scope_type', $asset->scope_type ?? 'component') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Clave</label>
            <input type="text" name="scope_key" value="{{ old('scope_key', $asset->scope_key) }}"
                   placeholder="hdmi_port"
                   class="mt-1 block w-full rounded-md border-outline-variant font-mono text-sm">
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-on-primary">
            {{ $asset->exists ? 'Guardar cambios' : 'Subir imagen' }}
        </button>
        <a href="{{ route('admin.media.index') }}" class="text-sm text-on-surface-variant underline underline-offset-2">Cancelar</a>
    </div>
</form>
@endsection
