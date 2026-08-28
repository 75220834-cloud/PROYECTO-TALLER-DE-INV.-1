<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Models\AbuseRejection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Consulta de la auditoría y de los rechazos del antiabuso
 * (plan CU-A-11 y CU-A-06b, ambos del MVP).
 *
 * Son dos registros distintos y se muestran por separado a propósito:
 *
 *   AUDITORÍA   quién cambió la configuración del sistema. Responde a
 *               "¿por qué el catálogo dice ahora otra cosa?".
 *   RECHAZOS    qué solicitudes bloqueó el antiabuso y por qué motivo.
 *
 * El segundo importa más de lo que parece. Los umbrales del antiabuso son
 * PROVISIONALES: nadie los ha calibrado. Si aparecen muchos rechazos por IP,
 * lo más probable no es que haya un ataque, sino que se esté bloqueando a
 * docentes que comparten la salida de la WiFi institucional (riesgo R18).
 * Esa pantalla es la que permite verlo en datos en lugar de por intuición.
 */
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $logs = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->select('audit_logs.*', 'users.name as user_name')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->orderByDesc('audit_logs.id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'actions' => DB::table('audit_logs')->distinct()->orderBy('action')->pluck('action'),
            'action' => $request->string('action')->toString(),
        ]);
    }

    public function rejections(Request $request): View
    {
        $days = max(1, min(365, $request->integer('days', 30)));
        $from = now()->subDays($days)->startOfDay();

        $rejections = AbuseRejection::query()
            ->with(['room', 'category'])
            ->where('occurred_at', '>=', $from)
            ->orderByDesc('occurred_at')
            ->paginate(50)
            ->withQueryString();

        // Agregado por motivo: es lo que de verdad se mira para decidir si un
        // umbral está mal puesto. Una fila suelta no dice nada; una columna
        // llena de rechazos por IP sí.
        $byReason = AbuseRejection::query()
            ->where('occurred_at', '>=', $from)
            ->selectRaw('reason, COUNT(*) as total')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->pluck('total', 'reason');

        return view('admin.audit.rejections', [
            'rejections' => $rejections,
            'byReason' => $byReason,
            'days' => $days,
            'thresholds' => config('incidencias.abuse'),
        ]);
    }
}
