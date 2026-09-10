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
 * purgar la demostracion.
 *
 * Las categorias salen del CHECKLIST REAL de soporte: son exactamente los
 * ocho equipos que ese documento inventaria en cada aula, mas una salida
 * para lo que no es ninguno de ellos. No son una lista teorica: si el
 * checklist no registra microfonos, el sistema no puede ofrecer un boton
 * de microfono, porque no sabria de que aula tiene y de cual no.
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
        // El orden es por frecuencia esperada de averia, no alfabetico: el
        // docente lee de arriba abajo y se detiene en cuanto reconoce su
        // problema. Poner el mouse antes que el proyector le costaria una
        // lectura completa de la lista a casi todo el mundo.
        //
        // teacher_label describe el SINTOMA, no el componente. Un docente que
        // no sabe que es un HDMI si sabe que "no se ve la imagen". Esa
        // diferencia decide si el sistema se usa.
        $categories = [
            ['COMPUTER', 'Computadora', 'La computadora no prende o no responde', 'HIGH', 1],
            ['PROJECTOR', 'Proyector', 'El proyector no prende', 'HIGH', 2],
            ['HDMI', 'HDMI', 'No se ve la imagen en el proyector', 'HIGH', 3],
            ['PROJECTOR_REMOTE', 'Control del proyector', 'El control del proyector no responde', 'MEDIUM', 4],
            ['SCREEN', 'Ecran', 'El ecran no baja o no sube', 'MEDIUM', 5],
            ['SPEAKER', 'Parlante', 'No se escucha el audio', 'MEDIUM', 6],
            ['MOUSE', 'Mouse', 'El mouse no se mueve', 'LOW', 7],
            ['KEYBOARD', 'Teclado', 'El teclado no escribe', 'LOW', 8],

            // Salida para lo que no es ninguno de los ocho equipos: internet
            // caido, un programa que no abre, un cable raro. Sin esta opcion
            // ese docente no tiene donde reportar y sale del sistema, que es
            // justo el comportamiento que el piloto quiere medir que
            // desaparece. No lleva diagnostico: va directo a soporte.
            ['OTHER', 'Otro problema', 'Otro problema', 'MEDIUM', 9],
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

        /*
         * Las categorias que ya no se ofrecen se DESACTIVAN, no se borran.
         *
         * Borrarlas romperia las incidencias historicas que apuntan a ellas
         * —la clave foranea lo impide, y con razon—: un ticket cerrado en
         * marzo bajo la categoria "Microfono" tiene que seguir siendo
         * interpretable aunque hoy esa categoria ya no exista. Desactivada
         * desaparece de la lista del docente y sigue explicando el pasado.
         */
        DB::table('incident_categories')
            ->whereNotIn('code', array_column($categories, 0))
            ->update(['is_active' => false, 'updated_at' => $now]);

        // ---- Tipos de equipo --------------------------------------------
        $cats = DB::table('incident_categories')->pluck('id', 'code');

        /*
         * Un tipo de equipo por cada columna del checklist, y cada uno
         * apunta a su categoria.
         *
         * Esa relacion es lo que permite la regla mas util de la pantalla
         * del docente: se le muestran SOLO los botones de los equipos que
         * su aula tiene registrados. Si el checklist dice que C201-A no
         * tiene parlante, el boton de parlante no aparece ahi — y con eso
         * se vuelve imposible abrir un ticket sobre un equipo inexistente,
         * que seria un dato falso dentro de la investigacion.
         */
        $types = [
            ['COMPUTER', 'Computadora del docente', 'COMPUTER', 1],
            ['PROJECTOR', 'Proyector', 'PROJECTOR', 2],
            ['HDMI_CABLE', 'Cable HDMI', 'HDMI', 3],
            ['PROJECTOR_REMOTE', 'Control del proyector', 'PROJECTOR_REMOTE', 4],
            ['SCREEN', 'Ecran', 'SCREEN', 5],
            ['SPEAKER', 'Parlante', 'SPEAKER', 6],
            ['MOUSE', 'Mouse', 'MOUSE', 7],
            ['KEYBOARD', 'Teclado', 'KEYBOARD', 8],
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

        // Mismo criterio que con las categorias: desactivar, nunca borrar.
        // Hay equipos registrados que apuntan a estos tipos.
        DB::table('equipment_types')
            ->whereNotIn('code', array_column($types, 0))
            ->update(['is_active' => false, 'updated_at' => $now]);
    }
}
