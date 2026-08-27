<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La auditoria administrativa deja de guardar la IP en claro.
 *
 * POR QUE ESTE CAMBIO
 *
 * El criterio de aceptacion del plan (27.1) es taxativo: "ninguna direccion
 * IP se almacena en claro". El modelo de datos (11.3), sin embargo, definia
 * `audit_logs.ip_address` como texto plano. Eran dos partes del mismo plan en
 * contradiccion, y la revision de seguridad de la fase 9 es exactamente el
 * momento de resolverla en lugar de dejarla pasar.
 *
 * Se resuelve a favor del criterio de aceptacion. El argumento a favor de
 * conservarla —saber "desde donde" actuo un usuario interno— pesa poco aqui:
 * la tabla ya guarda `user_id`, que identifica al actor mucho mejor que una
 * IP de la red interna, y el HMAC sigue permitiendo agrupar acciones del
 * mismo origen cuando se investiga una cuenta comprometida.
 *
 * LAS IP YA GUARDADAS SE DESCARTAN, no se convierten. Hashearlas conservaria
 * el dato bajo otra forma durante la migracion misma, y son datos de
 * desarrollo sin valor. Perder unas filas de auditoria de pruebas es
 * preferible a arrastrar direcciones que se decidio no retener.
 *
 * EXCEPCION CONOCIDA Y DECLARADA: la tabla `sessions` de Laravel guarda
 * `ip_address` en claro. Es infraestructura del framework y alterarla romperia
 * el manejo de sesiones. Se acota con retencion: una sesion se borra al
 * cerrarla y expira por inactividad, asi que el dato no se acumula. Queda
 * anotado aqui para que nadie afirme en el informe que el sistema no guarda
 * ninguna IP: guarda una, es la del personal interno autenticado, y es
 * efimera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->renameColumn('ip_address', 'ip_hash');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('ip_hash', 64)->nullable()->change();
        });

        DB::table('audit_logs')->update(['ip_hash' => null]);
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->renameColumn('ip_hash', 'ip_address');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->change();
        });
    }
};
