<?php

return [
    'disk' => env('PRODUCTOS_IMAGENES_DISK', 'local'),
    'prefijo' => env('PRODUCTOS_IMAGENES_PREFIJO', 'productos/imagenes'),
    'max_bytes' => 5 * 1024 * 1024,
    'max_pixeles' => 8_000_000,
    'miniatura_lado' => 480,
    'miniatura_calidad' => 82,
    'lote_max_archivos' => 20,
    'lote_max_bytes' => 40 * 1024 * 1024,
    'intento_horas' => 24,
    'reclamo_minutos' => 2,
    'galeria_por_pagina' => 12,
    'presencia_por_pagina' => 20,
];
