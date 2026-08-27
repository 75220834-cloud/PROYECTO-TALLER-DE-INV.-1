<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuracion del dominio
|--------------------------------------------------------------------------
| Ningun valor institucional, umbral ni ruta se escribe en el codigo.
| Todo llega desde el entorno para que el sistema sea agnostico del host
| (plan 21 y 22) y para que los umbrales se puedan calibrar sin desplegar.
*/

return [

    // Guarda de los datos DEMO. Vive en config y no se lee con env()
    // directamente: con la configuracion cacheada, env() devuelve null
    // fuera de este directorio y la guarda quedaria inutilizada.
    'allow_demo_seed' => (bool) env('ALLOW_DEMO_SEED', false),

    // Contrasena de los usuarios DEMO. Sin valor por defecto a
    // proposito: si no se define, el seeder genera una aleatoria.
    // Jamas una clave fija en el repositorio.
    'demo_user_password' => env('DEMO_USER_PASSWORD'),

    // Zona horaria de PRESENTACION. El almacenamiento es SIEMPRE UTC.
    // Mezclar zonas en la base corromperia en silencio todos los
    // indicadores de tiempo de la investigacion (plan 11.1).
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'America/Lima'),

    /*
    |----------------------------------------------------------------------
    | Asistente
    |----------------------------------------------------------------------
    | Los umbrales de confianza se CALIBRAN contra un conjunto etiquetado
    | (plan 13.5). Se dejan nulos a proposito: mientras no existan datos,
    | el sistema opera en modo conservador y escala mas de la cuenta, que
    | es el error barato. Inventar un numero aqui seria fingir precision.
    */
    'assistant' => [
        'confidence_high' => env('ASSISTANT_CONFIDENCE_HIGH') !== null && env('ASSISTANT_CONFIDENCE_HIGH') !== ''
            ? (float) env('ASSISTANT_CONFIDENCE_HIGH')
            : null,
        'confidence_low' => env('ASSISTANT_CONFIDENCE_LOW') !== null && env('ASSISTANT_CONFIDENCE_LOW') !== ''
            ? (float) env('ASSISTANT_CONFIDENCE_LOW')
            : null,
        'max_diagnostic_steps' => (int) env('ASSISTANT_MAX_DIAGNOSTIC_STEPS', 8),
    ],

    /*
    |----------------------------------------------------------------------
    | Proveedor de IA (plan 13.3)
    |----------------------------------------------------------------------
    | El sistema DEBE funcionar completo con provider = null.
    | Si esto deja de ser cierto, es un defecto.
    */
    'llm' => [
        'provider' => env('LLM_PROVIDER', 'null'),
        'base_url' => env('LLM_BASE_URL', 'http://localhost:11434'),
        'model' => env('LLM_MODEL'),
        'timeout' => (int) env('LLM_TIMEOUT_SECONDS', 30),
        'embedding_model' => env('EMBEDDING_MODEL'),
        'embedding_dimensions' => (int) env('EMBEDDING_DIMENSIONS', 768),
    ],

    /*
    |----------------------------------------------------------------------
    | Recuperacion (RAG) - plan 14.3
    |----------------------------------------------------------------------
    | MariaDB 10.4 no tiene indice vectorial: se prefiltra en SQL y se
    | calcula el coseno en PHP solo sobre los candidatos.
    |
    | prefilter_min es la SALVAGUARDA critica: si el filtro lexico devuelve
    | menos candidatos que este umbral, se amplia a toda la categoria. Sin
    | ella, el prefiltrado anularia la busqueda vectorial justo en las
    | parafrasis, que es para lo que sirve.
    */
    'retrieval' => [
        'vector_store' => env('VECTOR_STORE', 'mysql_inapp'),
        'prefilter_limit' => (int) env('RETRIEVAL_PREFILTER_LIMIT', 300),
        'prefilter_min' => (int) env('RETRIEVAL_PREFILTER_MIN', 20),
        'latency_budget_ms' => (int) env('RETRIEVAL_LATENCY_BUDGET_MS', 800),
        'top_k' => 10,
        'context_limit' => 4,
        'rrf_k' => 60,
    ],

    /*
    |----------------------------------------------------------------------
    | Banco de imagenes (plan 13.6 y Anexo A)
    |----------------------------------------------------------------------
    | Presupuesto de peso: se sirve a un celular en un aula, de pie, con
    | una clase esperando. Excederse aqui penaliza en el peor momento.
    */
    'media' => [
        'max_width' => (int) env('MEDIA_MAX_WIDTH', 800),
        'max_bytes' => (int) env('MEDIA_MAX_BYTES', 153600),
        'thumbnail_width' => 160,
        'format' => env('MEDIA_FORMAT', 'webp'),
        'strip_exif' => (bool) env('MEDIA_STRIP_EXIF', true),
        'accepted_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'disk' => 'local',
        'path' => 'media',
    ],

    /*
    |----------------------------------------------------------------------
    | Antiabuso (plan 16.4)
    |----------------------------------------------------------------------
    | Protege la ACCION costosa (crear un ticket que mueve a un tecnico),
    | no el acceso. El diagnostico es libre.
    |
    | VALORES PROVISIONALES: se calibran en la 1a semana del piloto
    | observando incident_abuse_rejections. Documentar el ajuste.
    */
    'abuse' => [
        'duplicate_window_minutes' => (int) env('ABUSE_DUPLICATE_WINDOW_MINUTES', 15),
        'max_active_per_room' => (int) env('ABUSE_MAX_ACTIVE_PER_ROOM', 3),
        'max_tickets_per_device_hour' => (int) env('ABUSE_MAX_TICKETS_PER_DEVICE_HOUR', 3),

        // Deliberadamente laxo: en la WiFi institucional muchos docentes
        // comparten IP de salida. Un umbral estrecho bloquearia usuarios
        // legitimos (riesgo R18). Nunca es el control principal.
        'max_tickets_per_ip_hour' => (int) env('ABUSE_MAX_TICKETS_PER_IP_HOUR', 30),

        'min_form_seconds' => (int) env('ABUSE_MIN_FORM_SECONDS', 3),
        'similarity_threshold' => (float) env('ABUSE_SIMILARITY_THRESHOLD', 0.85),
        'draft_purge_hours' => (int) env('DRAFT_PURGE_HOURS', 24),
    ],

    /*
    |----------------------------------------------------------------------
    | Modulo predictivo (plan 15)
    |----------------------------------------------------------------------
    | Umbrales de activacion declarados POR ADELANTADO para no elegir el
    | modelo despues de ver que metrica salio mejor.
    */
    'risk' => [
        'horizon_days' => (int) env('RISK_HORIZON_DAYS', 14),
        'model' => env('RISK_MODEL', 'baseline-recency'),
        'activation' => [
            'logistic' => ['min_observations' => 200, 'min_positives' => 40],
            'tree' => ['min_observations' => 500, 'min_positives' => 100],
        ],
        'bands' => ['low' => 0.33, 'medium' => 0.66],
    ],
];
