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
    /*
     * Depuración de productos/categorías no vistos en un sync completo.
     * Default false en MIG-02: no borrar huérfanos hasta validar contrato 2025-03.
     * No afecta reemplazo de imágenes/variantes cuando el payload trae esas claves.
     */
    'sync_prune_enabled' => filter_var(env('TIENDANUBE_SYNC_PRUNE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'webhook_url' => env('TIENDANUBE_WEBHOOK_URL'),
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
