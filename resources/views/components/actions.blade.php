@props(['editRoute', 'toggleRoute', 'destroyRoute', 'active' => true, 'blocked' => null])

<div class="flex items-center justify-end gap-2 text-sm">
    <a href="{{ $editRoute }}" class="text-on-surface-variant underline-offset-2 hover:text-primary hover:underline">Editar</a>

    <form method="POST" action="{{ $toggleRoute }}">
        @csrf @method('PATCH')
        <button type="submit" class="text-on-surface-variant underline-offset-2 hover:text-primary hover:underline">
            {{ $active ? 'Desactivar' : 'Activar' }}
        </button>
    </form>

    @if ($blocked)
        {{-- No se oculta el boton: se explica POR QUE no se puede borrar.
             Un boton que desaparece sin motivo deja al administrador
             pensando que la interfaz falla. --}}
        <span class="cursor-help text-outline-variant" title="{{ $blocked }}">Eliminar</span>
    @else
        <form method="POST" action="{{ $destroyRoute }}"
              data-confirm="¿Eliminar definitivamente? Esta acción no se puede deshacer.">
            @csrf @method('DELETE')
            <button type="submit" class="text-danger underline-offset-2 hover:text-on-danger-container hover:underline">Eliminar</button>
        </form>
    @endif
</div>
