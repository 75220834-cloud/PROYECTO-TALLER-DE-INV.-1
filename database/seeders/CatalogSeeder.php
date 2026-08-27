<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catalogos base: prioridades, estados y categorias.
 *
 * Ojo con la distincion: esto NO son datos demo. Son la estructura
 * operativa del sistema, y por eso no llevan is_demo ni desaparecen al
 * purgar la demostracion. Las categorias son las trece del enunciado y se
 * esperan reales; lo que si debera confirmarse con soporte es si usan otras
 * o llaman distinto a estas (plan 22.1).
 *
 * Se usa upsert por `code` para que volver a sembrar no duplique nada y no
 * pise los nombres que el administrador haya editado desde el panel.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ---- Prioridades ------------------------------------------------
        // level: mayor = mas urgente. El SLA queda NULO a proposito: no
        // conocemos los tiempos de respuesta reales de soporte y ponerlos
        // ahora seria inventar un compromiso institucional (plan 69).
        DB::table('incident_priorities')->upsert([
            ['code' => 'LOW', 'name' => 'Baja', 'level' => 1, 'color' => '#6b7280', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'MEDIUM', 'name' => 'Media', 'level' => 2, 'color' => '#2563eb', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'HIGH', 'name' => 'Alta', 'level' => 3, 'color' => '#ea580c', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CRITICAL', 'name' => 'Critica', 'level' => 4, 'color' => '#dc2626', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['level', 'color', 'is_active', 'updated_at']);

        // ---- Estados ----------------------------------------------------
        // is_open marca lo que cuenta como abierto: es lo que consulta el
        // tope antiabuso por aula y lo que filtra la bandeja de soporte.
        // DRAFT es open pero invisible para soporte: existe para medir
        // abandonos, no para movilizar a nadie (plan 7.1).
        DB::table('incident_statuses')->upsert([
            ['code' => 'DRAFT', 'name' => 'Borrador', 'is_open' => true, 'is_terminal' => false, 'is_resolved' => false, 'sort_order' => 0, 'color' => '#9ca3af', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DIAGNOSING', 'name' => 'En diagnostico', 'is_open' => true, 'is_terminal' => false, 'is_resolved' => false, 'sort_order' => 1, 'color' => '#8b5cf6', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'NEW', 'name' => 'Nuevo', 'is_open' => true, 'is_terminal' => false, 'is_resolved' => false, 'sort_order' => 2, 'color' => '#2563eb', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'IN_PROGRESS', 'name' => 'En atencion', 'is_open' => true, 'is_terminal' => false, 'is_resolved' => false, 'sort_order' => 3, 'color' => '#ea580c', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ESCALATED', 'name' => 'Escalado', 'is_open' => true, 'is_terminal' => false, 'is_resolved' => false, 'sort_order' => 4, 'color' => '#dc2626', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'RESOLVED', 'name' => 'Resuelta', 'is_open' => false, 'is_terminal' => false, 'is_resolved' => true, 'sort_order' => 5, 'color' => '#16a34a', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CLOSED', 'name' => 'Cerrada', 'is_open' => false, 'is_terminal' => true, 'is_resolved' => true, 'sort_order' => 6, 'color' => '#166534', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CANCELLED', 'name' => 'Cancelada', 'is_open' => false, 'is_terminal' => true, 'is_resolved' => false, 'sort_order' => 7, 'color' => '#6b7280', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['is_open', 'is_terminal', 'is_resolved', 'sort_order', 'color', 'is_active', 'updated_at']);

        $priorities = DB::table('incident_priorities')->pluck('id', 'code');

        // ---- Categorias -------------------------------------------------
        // teacher_label es lo que ve el docente: describe el SINTOMA, no el
        // componente. Un docente que no sabe que es un HDMI si sabe que "no
        // se ve la imagen". Esa diferencia decide si el sistema se usa.
        $categories = [
            ['HDMI_VIDEO', 'HDMI / Video', 'No se ve la imagen', 'HIGH', 1],
            ['PROJECTOR', 'Proyector', 'El proyector no funciona', 'HIGH', 2],
            ['SCREEN', 'Pantalla', 'Problema con la pantalla', 'MEDIUM', 3],
            ['AUDIO', 'Audio', 'No hay sonido', 'MEDIUM', 4],
            ['MICROPHONE', 'Microfono', 'El microfono no funciona', 'MEDIUM', 5],
            ['SPEAKERS', 'Parlantes', 'Los parlantes no suenan', 'MEDIUM', 6],
            ['COMPUTER', 'Computadora', 'La computadora no funciona', 'HIGH', 7],
            ['KEYBOARD', 'Teclado', 'El teclado no responde', 'LOW', 8],
            ['MOUSE', 'Mouse', 'El mouse no responde', 'LOW', 9],
            ['NETWORK', 'Conexion de red', 'No hay conexion de red', 'MEDIUM', 10],
            ['INTERNET', 'Internet', 'No hay internet', 'MEDIUM', 11],
            ['SOFTWARE', 'Software', 'Un programa no funciona', 'LOW', 12],
            ['OTHER', 'Otro', 'Otro problema', 'MEDIUM', 13],
        ];

        $rows = [];
        foreach ($categories as [$code, $name, $label, $priority, $order]) {
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'teacher_label' => $label,
                'default_priority_id' => $priorities[$priority],
                'sort_order' => $order,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('incident_categories')->upsert(
            $rows,
            ['code'],
            ['teacher_label', 'default_priority_id', 'sort_order', 'is_active', 'updated_at']
        );

        // ---- Tipos de equipo --------------------------------------------
        $cats = DB::table('incident_categories')->pluck('id', 'code');

        $types = [
            ['PROJECTOR', 'Proyector', 'PROJECTOR', 1],
            ['DESKTOP_PC', 'Computadora de escritorio', 'COMPUTER', 2],
            ['SCREEN', 'Pantalla de proyeccion', 'SCREEN', 3],
            ['SPEAKERS', 'Parlantes', 'SPEAKERS', 4],
            ['MICROPHONE', 'Microfono', 'MICROPHONE', 5],
            ['MONITOR', 'Monitor', 'SCREEN', 6],
            ['NETWORK_POINT', 'Punto de red', 'NETWORK', 7],
        ];

        $typeRows = [];
        foreach ($types as [$code, $name, $catCode, $order]) {
            $typeRows[] = [
                'code' => $code,
                'name' => $name,
                'default_category_id' => $cats[$catCode],
                'sort_order' => $order,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('equipment_types')->upsert(
            $typeRows,
            ['code'],
            ['default_category_id', 'sort_order', 'is_active', 'updated_at']
        );
    }
}
