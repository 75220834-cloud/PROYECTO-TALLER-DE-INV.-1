<?php

declare(strict_types=1);

namespace App\Modules\Locations\Services;

use App\Modules\Audit\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

/**
 * Acciones compartidas por los CRUD de sede, pabellon, piso y aula:
 * eliminar con guarda de dependencias y activar/desactivar.
 *
 * Se implemento primero como trait, pero obligaba a que la ruta usara un
 * parametro generico ({model}) y eso rompia el enlace implicito de modelos
 * de Laravel. Como servicio, cada controlador conserva su firma natural
 * (Site $site, Room $room) y solo delega las seis lineas que de verdad son
 * identicas. Menos ingenioso y mas facil de seguir.
 */
final class LocationEntityActions
{
    public function __construct(
        private readonly LocationDependencyGuard $guard,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Eliminar solo si nada cuelga de la entidad. Si algo cuelga, se explica
     * que es y se ofrece la alternativa real: desactivar.
     */
    public function delete(Model $model, string $label, string $indexRoute): RedirectResponse
    {
        if (! $this->guard->canDelete($model)) {
            return back()->with('error', $this->guard->explain($model));
        }

        $this->audit->record('locations.delete', $model, [
            'code' => $model->getAttribute('code'),
        ]);

        $model->delete();

        return redirect()->route($indexRoute)->with('status', $label.' eliminado correctamente.');
    }

    /**
     * Activar/desactivar. Siempre permitido y reversible: es la operacion
     * que soporte usara a diario y no debe tener friccion.
     */
    public function toggle(Model $model, string $label): RedirectResponse
    {
        $wasActive = (bool) $model->getAttribute('is_active');

        $model->setAttribute('is_active', ! $wasActive);
        $model->save();

        $this->audit->record('locations.toggle_active', $model, [
            'from' => $wasActive,
            'to' => ! $wasActive,
        ]);

        $message = $wasActive ? $label.' desactivado.' : $label.' activado.';

        // Al desactivar, avisar del arrastre: desactivar un pabellon deja
        // fuera de servicio todas sus aulas sin tocar ni una fila de aulas,
        // y esa consecuencia no se ve desde la pantalla del pabellon.
        if ($wasActive && ($warning = $this->guard->deactivationWarning($model)) !== null) {
            $message .= ' '.$warning;
        }

        return back()->with('status', $message);
    }
}
