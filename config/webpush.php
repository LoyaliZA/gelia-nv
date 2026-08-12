<?php

return [

    'enabled' => env('WEBPUSH_ENABLED', true),

    'vapid' => [
        // Subject VAPID debe seguir el entorno (APP_URL). Solo override si WEBPUSH_VAPID_SUBJECT no está vacío.
        'subject' => ($s = env('WEBPUSH_VAPID_SUBJECT')) !== null && $s !== ''
            ? $s
            : env('APP_URL', 'mailto:admin@gelia.local'),
        'public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
    ],

    'defaults' => [
        'icon' => '/favicon.svg',
        'badge' => '/favicon.svg',
    ],

];
