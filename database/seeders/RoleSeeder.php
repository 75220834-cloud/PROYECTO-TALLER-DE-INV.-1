<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos del personal interno (plan 5 y 16.2).
 *
 * El docente NO aparece aqui: no se autentica. Todo esto gobierna el panel
 * de soporte y administracion.
 *
 * Dos separaciones que importan y no son obvias:
 *
 *  - INVESTIGADOR es un rol de solo lectura con permiso de exportacion,
 *    separado del rol operativo. Si Italo usara su cuenta de administrador
 *    para extraer datos, cualquier accion suya quedaria mezclada con las de
 *    soporte en la auditoria y contaminaria las metricas del piloto.
 *  - TECNICO no puede administrar catalogos ni usuarios. No es
 *    desconfianza: es que un cambio accidental en el catalogo de aulas o de
 *    estados afecta a todos los tickets, incluidos los ya cerrados que la
 *    investigacion va a analizar.
 */
class RoleSeeder extends Seeder
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'locations.view' => 'Ver el catalogo de ubicaciones',
        'locations.manage' => 'Crear, editar y activar/desactivar ubicaciones',
        'equipment.view' => 'Ver el inventario de equipos',
        'equipment.manage' => 'Crear, editar y mover equipos',
        'catalogs.manage' => 'Administrar categorias, estados y prioridades',
        'incidents.view' => 'Ver incidencias',
        'incidents.assign' => 'Asignar incidencias a tecnicos',
        'incidents.update' => 'Cambiar estado, prioridad y registrar diagnostico',
        'incidents.close' => 'Cerrar y reabrir incidencias',
        'incidents.cancel' => 'Cancelar incidencias',
        'knowledge.view' => 'Consultar la base de conocimiento',
        'knowledge.manage' => 'Cargar, publicar y reindexar documentos',
        'media.manage' => 'Administrar el banco de imagenes',
        'diagnostics.manage' => 'Definir y versionar arboles de diagnostico',
        'dashboard.view' => 'Ver el tablero de metricas',
        'reports.view' => 'Consultar reportes',
        'reports.export' => 'Exportar el conjunto de datos de investigacion',
        'risk.view' => 'Consultar las senales de riesgo',
        'audit.view' => 'Consultar la auditoria',
        'abuse.view' => 'Consultar solicitudes rechazadas por antiabuso',
        'users.manage' => 'Administrar usuarios y roles',
    ];

    /** @var array<string, list<string>> */
    private const ROLES = [
        'admin' => ['*'],

        'coordinator' => [
            'locations.view', 'equipment.view', 'equipment.manage',
            'incidents.view', 'incidents.assign', 'incidents.update',
            'incidents.close', 'incidents.cancel',
            'knowledge.view', 'dashboard.view', 'reports.view',
            'risk.view', 'abuse.view',
        ],

        'technician' => [
            'locations.view', 'equipment.view',
            'incidents.view', 'incidents.assign', 'incidents.update', 'incidents.close',
            'knowledge.view', 'dashboard.view', 'risk.view',
        ],

        'knowledge_manager' => [
            'locations.view', 'equipment.view', 'incidents.view',
            'knowledge.view', 'knowledge.manage',
            'media.manage', 'diagnostics.manage',
        ],

        // Solo lectura + exportacion. Sin ningun permiso de escritura sobre
        // incidencias: el investigador observa el proceso, no participa en el.
        'researcher' => [
            'locations.view', 'equipment.view', 'incidents.view',
            'dashboard.view', 'reports.view', 'reports.export',
            'risk.view', 'abuse.view',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            $role->syncPermissions(
                $permissions === ['*'] ? array_keys(self::PERMISSIONS) : $permissions
            );
        }

        $this->command->info('  Roles: '.count(self::ROLES).' | Permisos: '.count(self::PERMISSIONS));
    }

    /** @return array<string, string> */
    public static function permissionDescriptions(): array
    {
        return self::PERMISSIONS;
    }
}
