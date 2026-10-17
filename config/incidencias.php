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
    | Direccion publica y acceso desde internet
    |----------------------------------------------------------------------
    | El sistema tiene dos puertas con publicos distintos. El docente entra
    | desde su celular por la direccion publica, con datos moviles o con
    | cualquier red. El personal de soporte entra por la red interna.
    |
    | PUBLIC_URL es la direccion que va DENTRO DEL QR. No se deduce de la
    | peticion a proposito: el QR se genera desde el panel, y el panel se
    | abre en la PC de soporte como http://localhost. Deducirla produciria
    | un cartel que apunta a localhost, que en el celular del docente no
    | abre nada, y eso se descubriria con los carteles ya pegados.
    */
    'public_url' => env('PUBLIC_URL'),

    // Quien puede reenviar peticiones en nombre de otro. Por defecto, solo
    // el propio equipo: es por donde entra el tunel. Sin esto, todos los
    // docentes llegarian con la IP 127.0.0.1 y los limites antiabuso por
    // red tratarian a la universidad entera como un solo usuario.
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) (env('TRUSTED_PROXIES') ?: '127.0.0.1,::1')),
    ))),

    /*
    |----------------------------------------------------------------------
    | Copias de seguridad
    |----------------------------------------------------------------------
    | Diarias. Conviene que la carpeta este en OTRO disco o en una unidad de
    | red: una copia en el mismo disco que la base no sobrevive a su averia.
    */
    // `?:` y no el valor por defecto de env(): una linea `RESPALDO_CARPETA=`
    // vacia en el .env llega como texto vacio, no como ausente, y la copia
    // acabaria en la carpeta desde la que se ejecuto el comando.
    'respaldo' => [
        'carpeta' => env('RESPALDO_CARPETA') ?: storage_path('app'.DIRECTORY_SEPARATOR.'respaldos'),
        'conservar' => (int) (env('RESPALDO_CONSERVAR') ?: 14),
        'mysqldump' => env('RESPALDO_MYSQLDUMP') ?: (PHP_OS_FAMILY === 'Windows'
            ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe'
            : 'mysqldump'),
    ],

    'panel' => [
        // El panel de soporte solo se abre desde la red interna. Desde
        // internet solo existe la parte del docente; el resto responde 404,
        // como si no existiera, incluida la pantalla de inicio de sesion.
        'solo_red_interna' => (bool) env('PANEL_SOLO_RED_INTERNA', true),

        // Redes desde las que se considera que alguien esta "dentro". Por
        // defecto, el propio equipo y los rangos privados. Soporte puede
        // estrecharlo a su subred, o dejar solo 127.0.0.1 para que el panel
        // se abra unicamente en la PC del servidor.
        'redes' => array_values(array_filter(array_map(
            'trim',
            // Vacio no significa "ninguna red": eso cerraria el panel tambien
            // en la propia PC y nadie podria entrar a corregirlo.
            explode(',', (string) (env('PANEL_REDES') ?: '127.0.0.1/32,::1/128,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16')),
        ))),
    ],

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
        /*
         * Similitud coseno minima para que un fragmento cuente como
         * relevante cuando NO coincidio en el indice de texto completo.
         *
         * Coincidir lexicamente ya es señal suficiente: significa que
         * comparte vocabulario con la pregunta. Este piso protege el otro
         * camino, el semantico, donde la salvaguarda del prefiltro
         * garantiza que siempre haya candidatos aunque ninguno sirva.
         *
         * 0.65 es provisional. Se midio con cinco preguntas de prueba: las
         * ajenas al dominio dieron hasta 0.602 y las legitimas desde 0.582,
         * asi que las bandas SE SOLAPAN y este umbral por si solo no
         * separa. Por eso hay una segunda condicion —agreement_rank— y por
         * eso los dos numeros hay que calibrarlos con el conjunto
         * etiquetado, igual que los umbrales de confianza.
         */
        'relevance_floor' => (float) env('RETRIEVAL_RELEVANCE_FLOOR', 0.65),

        /*
         * Posicion maxima en AMBOS rankings para que la coincidencia cuente
         * como acuerdo entre las dos vias.
         *
         * Es el discriminador que si funciono: en las preguntas legitimas el
         * mejor fragmento estaba arriba en los dos rankings; en las ajenas,
         * el que ganaba por lexico caia al puesto 22 o mas en el semantico.
         */
        'agreement_rank' => (int) env('RETRIEVAL_AGREEMENT_RANK', 10),

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
