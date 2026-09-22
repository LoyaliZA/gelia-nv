<?php

return [
    'driver' => env('MEDIOS_DRIVER', env('APP_ENV') === 'testing' ? 'fake' : 'r2'),
    'disk' => env('MEDIOS_DISK', 'r2'),
    'prefijo_object_key' => env('MEDIOS_PREFIX', 'advertising/media'),
    'ttl_url_seg' => (int) env('MEDIOS_TTL_URL', 900),
    'ttl_lectura_seg' => (int) env('MEDIOS_TTL_LECTURA', 21600),
    'chunk_bytes' => (int) env('MEDIOS_CHUNK_BYTES', 32 * 1024 * 1024),
    'umbral_multipart_bytes' => (int) env('MEDIOS_MULTIPART_THRESHOLD', 32 * 1024 * 1024),
    'max_bytes' => (int) env('MEDIOS_MAX_BYTES', 2 * 1024 * 1024 * 1024),
    'expires_carga_min' => (int) env('MEDIOS_CARGA_EXPIRES_MIN', 360),
    'imagen_duracion_default' => 10,
    'mimes' => [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'video/mp4' => ['mp4'],
        'video/webm' => ['webm'],
        'video/quicktime' => ['mov'],
    ],
    'propositos' => [
        'pdv_publicidad' => 'pdv.publicidad.crear',
    ],
];
