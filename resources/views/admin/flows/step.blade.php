@extends('layouts.app')
@section('title', $step->exists ? 'Editar paso' : 'Nuevo paso')
@section('heading', $step->exists ? 'Editar paso «' . $step->step_key . '»' : 'Nuevo paso')
@section('subheading', $version->flow->category->name . ' · versión ' . $version->version)

@section('content')
<form method="POST" class="max-w-2xl space-y-5"
      action="{{ $step->exists ? route('admin.flows.steps.update', $step) : route('admin.flows.steps.store', $version) }}">
    @csrf
    @if ($step->exists) @method('PUT') @endif

    @if ($errors->any())
        <div class="rounded-md border border-danger bg-danger-container px-4 py-3 text-sm text-on-danger-container">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)<li>· {{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-4 rounded-lg border border-outline-variant bg-surface-lowest p-5">
        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Pregunta para el docente</label>
            <textarea name="prompt_text" rows="2" required
                      placeholder="¿El cable está bien conectado en el proyector?"
                      class="mt-1 block w-full rounded-md border-outline-variant text-sm">{{ old('prompt_text', $step->prompt_text) }}</textarea>
            <p class="mt-1 text-xs text-on-surface-variant">
                Habla como se habla en el aula. Evita términos que un docente pueda no conocer, o
                acompáñalos con la imagen del componente más abajo.
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Aclaración (opcional)</label>
            <input type="text" name="help_text" value="{{ old('help_text', $step->help_text) }}"
                   class="mt-1 block w-full rounded-md border-outline-variant text-sm">
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-on-surface-variant">Clave del paso</label>
                <input type="text" name="step_key" value="{{ old('step_key', $step->step_key) }}" required
                       placeholder="revisar_cable"
                       class="mt-1 block w-full rounded-md border-outline-variant font-mono text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-on-surface-variant">Orden</label>
                <input type="number" name="sort_order" min="0" max="999"
                       value="{{ old('sort_order', $step->sort_order ?? 0) }}"
                       class="mt-1 block w-full rounded-md border-outline-variant text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-on-surface-variant">Pieza que se muestra</label>
                <input type="text" name="component_key" value="{{ old('component_key', $step->component_key) }}"
                       placeholder="hdmi_port"
                       class="mt-1 block w-full rounded-md border-outline-variant font-mono text-sm">
            </div>
        </div>

        <p class="text-xs text-on-surface-variant">
            La <strong>pieza</strong> enlaza con el banco de imágenes: si existe una imagen de
            componente con esa misma clave, el docente la ve junto a la pregunta. Es lo que hace
            entendible «revisa el cable HDMI» para quien no sabe cuál es.
        </p>
    </div>

    <div class="space-y-4 rounded-lg border border-outline-variant bg-surface-lowest p-5">
        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Respuestas posibles</label>

            <textarea name="respuestas" rows="5"
                      placeholder="si|Sí, está bien conectado|revisar_imagen&#10;no|No, estaba suelto&#10;no_se|No estoy seguro|ver_foto"
                      class="mt-1 block w-full rounded-md border-outline-variant font-mono text-sm">{{ old('respuestas', $step->exists ? collect($step->answer_options ?? [])->map(fn ($o) => $o['value'].'|'.$o['label'].(isset(($step->next_step_map ?? [])[$o['value']]) ? '|'.$step->next_step_map[$o['value']] : ''))->implode("\n") : '') }}</textarea>

            {{-- Se explica el formato con un ejemplo en vez de con una
                 gramática: quien escribe estos procedimientos es soporte, no
                 un programador. --}}
            <div class="mt-2 text-xs text-on-surface-variant">
                <p>Una por línea, con el formato <code class="font-mono">clave|lo que lee el docente|paso siguiente</code>.</p>
                <p class="mt-1">
                    Si omites el paso siguiente, esa respuesta lleva a «¿se solucionó?». Es lo que se
                    quiere después de pedirle una acción correctiva.
                </p>

                @if ($otros->isNotEmpty())
                    <p class="mt-2">
                        Pasos a los que puedes saltar:
                        @foreach ($otros as $otro)
                            <code class="font-mono">{{ $otro->step_key }}</code>@if (! $loop->last), @endif
                        @endforeach
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="space-y-3 rounded-lg border border-outline-variant bg-surface-lowest p-5">
        <label class="flex cursor-pointer items-start gap-3">
            <input type="hidden" name="is_terminal" value="0">
            <input type="checkbox" name="is_terminal" value="1" class="mt-1"
                   @checked(old('is_terminal', $step->is_terminal))>
            <span>
                <span class="block text-sm font-medium text-on-surface">Este paso cierra el procedimiento</span>
                <span class="block text-xs text-on-surface-variant">
                    Al llegar aquí no se pregunta más. Todo árbol necesita al menos uno, o el docente
                    recorre pasos sin llegar nunca al final.
                </span>
            </span>
        </label>

        <div>
            <label class="block text-sm font-medium text-on-surface-variant">Al cerrar…</label>
            <select name="terminal_outcome" class="mt-1 block w-full rounded-md border-outline-variant text-sm sm:w-64">
                <option value="escalate" @selected(old('terminal_outcome', $step->terminal_outcome) === 'escalate')>
                    Avisar a soporte
                </option>
                <option value="resolved" @selected(old('terminal_outcome', $step->terminal_outcome) === 'resolved')>
                    Dar por resuelto
                </option>
            </select>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-on-primary">
            {{ $step->exists ? 'Guardar paso' : 'Añadir paso' }}
        </button>
        <a href="{{ route('admin.flows.show', $version) }}" class="text-sm text-on-surface-variant underline underline-offset-2">Cancelar</a>
    </div>
</form>
@endsection
