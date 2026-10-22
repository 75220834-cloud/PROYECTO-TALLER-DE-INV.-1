<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Auditoria, en palabras
|--------------------------------------------------------------------------
| Lo que se guarda es el codigo de la accion y el nombre de la clase; lo que
| se lee en el panel es esto. Una accion o un campo sin traduccion se
| muestra tal cual, para que nunca desaparezca un registro.
*/

return [

    'actions' => [
        'category.created' => 'Creó un tipo de problema',
        'category.toggled' => 'Mostró u ocultó un tipo de problema',
        'category.updated' => 'Editó un tipo de problema',
        'equipment.create' => 'Registró un equipo',
        'equipment.move' => 'Movió un equipo de aula',
        'equipment.update' => 'Editó un equipo',
        'flow.draft_created' => 'Creó un borrador de procedimiento',
        'flow.duplicated' => 'Duplicó un procedimiento',
        'flow.published' => 'Publicó un procedimiento',
        'knowledge.archived' => 'Archivó un documento',
        'knowledge.created_from_incident' => 'Creó un artículo desde una incidencia',
        'knowledge.published' => 'Publicó un documento',
        'knowledge.reindex' => 'Volvió a indexar un documento',
        'knowledge.unpublished' => 'Retiró un documento',
        'knowledge.version_uploaded' => 'Subió una versión de un documento',
        'locations.create' => 'Creó una ubicación',
        'locations.delete' => 'Eliminó una ubicación',
        'locations.toggle_active' => 'Activó o desactivó una ubicación',
        'locations.update' => 'Editó una ubicación',
        'media.created' => 'Subió una imagen',
        'media.toggled' => 'Mostró u ocultó una imagen',
        'media.updated' => 'Editó una imagen',
        'research.dataset_exported' => 'Exportó los datos de investigación',
        'user.created' => 'Creó un usuario',
        'user.updated' => 'Editó un usuario',
    ],

    'subjects' => [
        'AbuseRejection' => 'Rechazo',
        'Building' => 'Pabellón',
        'DiagnosticFlow' => 'Procedimiento',
        'DiagnosticFlowVersion' => 'Versión de procedimiento',
        'DiagnosticStep' => 'Paso',
        'Equipment' => 'Equipo',
        'EquipmentType' => 'Tipo de equipo',
        'Floor' => 'Piso',
        'Incident' => 'Incidencia',
        'IncidentCategory' => 'Tipo de problema',
        'KnowledgeDocument' => 'Documento',
        'KnowledgeDocumentVersion' => 'Versión de documento',
        'MediaAsset' => 'Imagen',
        'Room' => 'Aula',
        'Site' => 'Sede',
        'User' => 'Usuario',
    ],

    'fields' => [
        'brand' => 'Marca',
        'building_id' => 'Pabellón',
        'capacity' => 'Capacidad',
        'category' => 'Tipo de problema',
        'code' => 'Código',
        'criticality' => 'Criticidad',
        'desde_version' => 'Desde la versión',
        'equipment_type_id' => 'Tipo de equipo',
        'file' => 'Archivo',
        'floor_id' => 'Piso',
        'from' => 'Antes',
        'from_room_id' => 'Desde el aula',
        'incident_id' => 'Incidencia',
        'is_active' => 'Activo',
        'label' => 'Etiqueta',
        'model' => 'Modelo',
        'name' => 'Nombre',
        'number' => 'Número',
        'password_changed' => 'Cambió la contraseña',
        'role' => 'Rol',
        'role_from' => 'Rol anterior',
        'role_to' => 'Rol nuevo',
        'room_id' => 'Aula',
        'self_service' => 'Autoatención',
        'serial_number' => 'Número de serie',
        'site_id' => 'Sede',
        'status' => 'Estado',
        'ticket' => 'Ticket',
        'title' => 'Título',
        'to' => 'Después',
        'to_room_id' => 'Hacia el aula',
        'type' => 'Tipo',
        'version' => 'Versión',
        'via' => 'Desde',
    ],

];
