<?php

return [
    'app_id' => env('TIENDANUBE_APP_ID'),
    'app_secret' => env('TIENDANUBE_APP_SECRET'),
    'store_id' => env('TIENDANUBE_STORE_ID'),
    'access_token' => env('TIENDANUBE_ACCESS_TOKEN'),
    /*
     * Precedencia de URL:
     * 1) TIENDANUBE_API_BASE si está definido (legado, sin store_id).
     * 2) si no: {api_host}/{api_version}/{store_id}.
     * Versiones permitidas: v1, 2025-03. Default v1 hasta MIG-06.
     */
    'api_host' => env('TIENDANUBE_API_HOST', 'https://api.tiendanube.com'),
    'api_version' => env('TIENDANUBE_API_VERSION', 'v1'),
    'api_base' => env('TIENDANUBE_API_BASE'),
    'user_agent' => env('TIENDANUBE_USER_AGENT', 'Gelianv'),
    'user_agent_contact' => env('TIENDANUBE_USER_AGENT_CONTACT'),
    'per_page' => (int) env('TIENDANUBE_PER_PAGE', 50),
    'retry_sleep_ms' => (int) env('TIENDANUBE_RETRY_SLEEP_MS', 1500),
    'webhook_url' => env('TIENDANUBE_WEBHOOK_URL'),
    'webhook_max_attempts' => (int) env('TIENDANUBE_WEBHOOK_MAX_ATTEMPTS', 10),
    'webhook_lease_seconds' => (int) env('TIENDANUBE_WEBHOOK_LEASE_SECONDS', 300),
    'webhook_backoff_seconds' => (int) env('TIENDANUBE_WEBHOOK_BACKOFF_SECONDS', 60),
    'webhook_recover_limit' => (int) env('TIENDANUBE_WEBHOOK_RECOVER_LIMIT', 50),
    /*
     * Depuración de productos/categorías no vistos en un sync completo.
     * Default false en MIG-02: no borrar huérfanos hasta validar contrato 2025-03.
     * No afecta reemplazo de imágenes/variantes cuando el payload trae esas claves.
     */
    'sync_prune_enabled' => filter_var(env('TIENDANUBE_SYNC_PRUNE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'sync_prune_confirm_threshold' => (int) env('TIENDANUBE_SYNC_PRUNE_CONFIRM_THRESHOLD', 10),
    'sync_lease_seconds' => (int) env('TIENDANUBE_SYNC_LEASE_SECONDS', 120),
    'sync_stale_minutes' => (int) env('TIENDANUBE_SYNC_STALE_MINUTES', 15),
    'sync_vistos_retencion_dias' => (int) env('TIENDANUBE_SYNC_VISTOS_RETENCION_DIAS', 14),
    'image_import_max_entries' => (int) env('TIENDANUBE_IMAGE_IMPORT_MAX_ENTRIES', 500),
    'image_import_max_file_bytes' => (int) env('TIENDANUBE_IMAGE_IMPORT_MAX_FILE_BYTES', 10 * 1024 * 1024),
    'image_import_max_total_uncompressed_bytes' => (int) env('TIENDANUBE_IMAGE_IMPORT_MAX_TOTAL_BYTES', 200 * 1024 * 1024),
    'image_import_retention_days' => (int) env('TIENDANUBE_IMAGE_IMPORT_RETENTION_DAYS', 7),
    'image_import_claim_seconds' => (int) env('TIENDANUBE_IMAGE_IMPORT_CLAIM_SECONDS', 600),
    /*
     * Eventos registrables vía POST /webhooks de la API.
     * No incluir store/redact ni customers/*: son webhooks de privacidad (LGPD)
     * y la API responde 422 "Invalid event" al intentar crearlos así.
     */
    'webhook_events' => [
        'app/uninstalled',
        'product/created',
        'product/updated',
        'product/deleted',
        'category/created',
        'category/updated',
        'category/deleted',
    ],
];
