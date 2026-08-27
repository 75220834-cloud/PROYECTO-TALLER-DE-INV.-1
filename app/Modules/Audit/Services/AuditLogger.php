<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Auditoria de acciones administrativas (plan 11.5, 33).
 *
 * Cubre la configuracion: catalogos, usuarios, conocimiento, imagenes. El
 * ciclo de vida de las incidencias NO pasa por aqui: vive en
 * incident_events, que es append-only y esta pensado para reconstruir la
 * linea de tiempo de un ticket. Son dos preguntas distintas y por eso son
 * dos tablas distintas:
 *
 *   audit_logs        "quien cambio la configuracion del sistema"
 *   incident_events   "que le paso a esta incidencia"
 *
 * Mezclarlas obligaria a filtrar ruido administrativo cada vez que se
 * quiere entender un ticket.
 */
final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(string $action, ?Model $subject = null, array $changes = []): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $subject !== null ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : json_encode($changes, JSON_UNESCAPED_UNICODE),
            'ip_address' => Request::ip(),

            // Acotado al ancho de la columna: un User-Agent largo truncado
            // por el motor provocaria un error en modo estricto.
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
