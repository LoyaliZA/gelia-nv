<?php

$appUrl = (string) env('APP_URL', 'http://localhost');

return [

    'enabled' => filter_var(env('WEBAUTHN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Relying Party
    |--------------------------------------------------------------------------
    */

    'relying_party' => [
        'name' => env('WEBAUTHN_RP_NAME', env('WEBAUTHN_NAME', env('APP_NAME', 'GELIA-NV'))),
        'id' => env('WEBAUTHN_RP_ID', env('WEBAUTHN_ID', parse_url($appUrl, PHP_URL_HOST))),
    ],

    'origins' => env('WEBAUTHN_ORIGINS', $appUrl),

    'challenge' => [
        'bytes' => 16,
        'timeout' => 120,
        'key' => '_webauthn',
    ],
];
