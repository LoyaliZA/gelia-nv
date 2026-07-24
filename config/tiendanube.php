<?php

return [
    'app_id' => env('TIENDANUBE_APP_ID'),
    'app_secret' => env('TIENDANUBE_APP_SECRET'),
    'store_id' => env('TIENDANUBE_STORE_ID'),
    'access_token' => env('TIENDANUBE_ACCESS_TOKEN'),
    'api_base' => env('TIENDANUBE_API_BASE', 'https://api.tiendanube.com/v1'),
    'user_agent' => env('TIENDANUBE_USER_AGENT', 'Gelianv'),
    'per_page' => (int) env('TIENDANUBE_PER_PAGE', 50),
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
