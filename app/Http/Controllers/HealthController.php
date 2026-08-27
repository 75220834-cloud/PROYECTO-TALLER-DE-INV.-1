<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Assistant\Contracts\LlmProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Estado del sistema para monitoreo (plan 20.4).
 *
 * QUE CUENTA COMO "CAIDO" Y QUE NO — la decision que hace util este endpoint.
 *
 * Solo la base de datos degrada el estado global. Si falta, el sistema no
 * puede hacer nada.
 *
 * Ollama NO degrada el estado. Es deliberado y es coherente con toda la
 * arquitectura: sin modelo el sistema sigue funcionando completo en modo
 * determinista (plan 13.2). Marcarlo como caido haria sonar una alarma a las
 * tres de la mañana por algo que no impide a ningun docente reportar nada, y
 * una alarma que suena sin motivo se acaba silenciando — justo antes de la
 * vez que si importaba.
 *
 * Los trabajos fallidos en cola tampoco tumban el estado, pero se informan:
 * significan documentos sin indexar o notificaciones sin entregar, que es una
 * degradacion real que alguien debe mirar sin prisa.
 *
 * No requiere autenticacion, y por eso NO revela versiones, rutas, nombres de
 * base de datos ni mensajes de error: un endpoint de salud que describe la
 * instalacion es un regalo para quien la esta explorando.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->check(static fn () => DB::select('SELECT 1'));

        $checks = [
            'database' => $database,
            'queue' => $this->queue(),
            'llm' => $this->llm(),
            'storage' => $this->storage(),
        ];

        return response()->json([
            'status' => $database ? 'ok' : 'down',
            'checks' => $checks,
            'time' => now()->toIso8601String(),
        ], $database ? 200 : 503);
    }

    private function queue(): string
    {
        try {
            $failed = DB::table('failed_jobs')->count();

            return $failed === 0 ? 'ok' : "degraded:{$failed}";
        } catch (Throwable) {
            return 'unknown';
        }
    }

    private function llm(): string
    {
        try {
            return app(LlmProvider::class)->isAvailable() ? 'ok' : 'unavailable';
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    /**
     * Espacio libre en el disco donde viven documentos e imagenes. Un disco
     * lleno no rompe nada visible: simplemente las subidas empiezan a fallar
     * y los documentos dejan de indexarse en silencio.
     */
    private function storage(): string
    {
        $free = @disk_free_space(storage_path());

        if ($free === false) {
            return 'unknown';
        }

        return $free < 1024 ** 3 ? 'low' : 'ok';
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
