<?php

return [

    'enabled' => env('WEBPUSH_ENABLED', true),

    'vapid' => [
        'subject' => env('WEBPUSH_VAPID_SUBJECT', env('APP_URL', 'mailto:admin@gelia.local')),
        'public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
    ],

    'defaults' => [
        'icon' => '/favicon.svg',
        'badge' => '/favicon.svg',
    ],

];
