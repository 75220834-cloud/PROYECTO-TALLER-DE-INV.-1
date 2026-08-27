<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Audit\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta el conjunto de datos para el análisis de la investigación
 * (plan 26.bis, CU-A-12).
 *
 * QUÉ SE EXPORTA Y QUÉ NO
 *
 * Se exportan las variables que el análisis necesita: ubicación, categoría,
 * tiempos, tipo de resolución, prioridad, pasos ejecutados.
 *
 * NO se exportan `device_key` ni `ip_hash`. Sirven al antiabuso dentro del
 * sistema y no aportan nada al análisis; sacarlos de aquí sería mover
 * identificadores de origen a una hoja de cálculo que después circula por
 * correo. Tampoco se exporta `reporter_hint`: es un dato de contacto que el
 * docente dio para que soporte pudiera ubicarlo, no para acabar en un
 * conjunto de datos.
 *
 * La descripción libre SÍ se exporta, porque es material de análisis
 * cualitativo, pero conviene revisarla antes de compartirla fuera: un
 * docente puede haber escrito su nombre o su número sin que nadie se lo
 * pidiera.
 */
final class DatasetExporter
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function stream(): StreamedResponse
    {
        // La exportación se audita: es un acceso masivo a datos del piloto y
        // debe quedar constancia de quién lo hizo y cuándo.
        $this->audit->record('research.dataset_exported');

        $filename = 'incidencias-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'wb');

            // BOM para que Excel en Windows abra las tildes correctamente.
            // Sin esto el equipo recibe un CSV lleno de caracteres rotos y
            // acaba corrigiéndolo a mano, que es como se estropean los datos.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'uuid', 'ticket', 'aula', 'pabellon', 'piso', 'criticidad_aula',
                'categoria', 'prioridad', 'estado', 'bloquea_clase',
                'tipo_resolucion', 'evito_desplazamiento',
                'confirmado_en', 'reportado_en', 'primera_respuesta_en',
                'resuelto_en', 'cerrado_en',
                'min_hasta_primera_respuesta', 'min_hasta_resolucion',
                'pasos_diagnostico', 'pasos_con_imagen', 'reaperturas',
                'reportes_adicionales', 'facilidad_1a5', 'descripcion',
            ], ';');

            DB::table('incidents')
                ->leftJoin('rooms', 'rooms.id', '=', 'incidents.room_id')
                ->leftJoin('floors', 'floors.id', '=', 'rooms.floor_id')
                ->leftJoin('buildings', 'buildings.id', '=', 'floors.building_id')
                ->leftJoin('incident_categories as c', 'c.id', '=', 'incidents.category_id')
                ->leftJoin('incident_priorities as p', 'p.id', '=', 'incidents.priority_id')
                ->leftJoin('incident_statuses as s', 's.id', '=', 'incidents.status_id')
                ->leftJoin('satisfaction_responses as sr', 'sr.incident_id', '=', 'incidents.id')
                ->where('incidents.is_draft', false)
                ->selectRaw('
                    incidents.uuid, incidents.ticket_number, rooms.code AS aula,
                    buildings.name AS pabellon, floors.label AS piso,
                    rooms.criticality, c.name AS categoria, p.name AS prioridad,
                    s.name AS estado, incidents.blocks_class, incidents.resolution_type,
                    incidents.confirmed_at, incidents.reported_at, incidents.first_response_at,
                    incidents.resolved_at, incidents.closed_at, incidents.reopened_count,
                    incidents.reported_description, sr.ease_score,
                    TIMESTAMPDIFF(MINUTE, incidents.reported_at, incidents.first_response_at) AS min_respuesta,
                    TIMESTAMPDIFF(MINUTE, incidents.confirmed_at, incidents.resolved_at) AS min_resolucion,
                    (SELECT COUNT(*) FROM diagnostic_answers da WHERE da.incident_id = incidents.id) AS pasos,
                    (SELECT COUNT(*) FROM diagnostic_answers da2 WHERE da2.incident_id = incidents.id AND da2.media_shown IS NOT NULL) AS pasos_imagen,
                    (SELECT COUNT(*) FROM incidents m WHERE m.merged_into_id = incidents.id) AS adicionales
                ')
                ->orderBy('incidents.id')
                // Por trozos: un piloto puede acumular miles de filas y
                // cargarlas todas en memoria acabaría en un error de límite.
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $r) {
                        fputcsv($out, [
                            $r->uuid, $r->ticket_number, $r->aula, $r->pabellon, $r->piso,
                            $r->criticality, $r->categoria, $r->prioridad, $r->estado,
                            $r->blocks_class ? 1 : 0,
                            $r->resolution_type,
                            in_array($r->resolution_type, ['assistant', 'remote'], true) ? 1 : 0,
                            $r->confirmed_at, $r->reported_at, $r->first_response_at,
                            $r->resolved_at, $r->closed_at,
                            $r->min_respuesta, $r->min_resolucion,
                            $r->pasos, $r->pasos_imagen, $r->reopened_count,
                            $r->adicionales, $r->ease_score,
                            // Se normalizan los saltos de línea: un salto sin
                            // escapar parte la fila y descuadra el archivo.
                            str_replace(["\r", "\n"], ' ', (string) $r->reported_description),
                        ], ';');
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
