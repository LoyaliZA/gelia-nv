<?php

return [
    // Respuesta del modelo por llamada (tokens de salida).
    'max_tokens' => (int) env('GELIA_AI_MAX_TOKENS', 1200),

    // Modo thinking de DeepSeek (enabled|disabled). Solo se envía con modelos no heredados.
    'thinking' => env('GELIA_AI_THINKING', 'disabled'),

    // Límites por turno del ciclo de herramientas.
    'max_steps' => (int) env('GELIA_AI_MAX_STEPS', 6),
    'max_tool_calls' => (int) env('GELIA_AI_MAX_TOOL_CALLS', 12),
    'max_tool_calls_per_step' => (int) env('GELIA_AI_MAX_TOOL_CALLS_PER_STEP', 4),
    'max_repeated_calls' => (int) env('GELIA_AI_MAX_REPEATED_CALLS', 1),
    'max_turn_seconds' => (int) env('GELIA_AI_MAX_TURN_SECONDS', 90),
    'max_turn_tokens' => (int) env('GELIA_AI_MAX_TURN_TOKENS', 30000),
    'tool_result_max_chars' => (int) env('GELIA_AI_TOOL_RESULT_MAX_CHARS', 12000),

    // Presupuesto de historial recuperado del servidor.
    'history_max_messages' => (int) env('GELIA_AI_HISTORY_MAX_MESSAGES', 24),
    'history_budget_chars' => (int) env('GELIA_AI_HISTORY_BUDGET_CHARS', 12000),
    'history_message_max_chars' => (int) env('GELIA_AI_HISTORY_MESSAGE_MAX_CHARS', 1500),

    // Inventario.
    'inventario_limit_default' => 5,
    'inventario_limit_max' => 10,
    'inventario_stock_rows_max' => 8,

    // Propuestas de acción pendientes de confirmación.
    'propuesta_ttl_minutes' => (int) env('GELIA_AI_PROPUESTA_TTL', 30),

    'timeout_seconds' => (int) env('GELIA_AI_TIMEOUT', 45),

    // Piloto: conocimiento de productos, separado de existencias y del modelo de chat elegido.
    'recomendaciones' => [
        'enabled' => (bool) env('GELIA_AI_RECOMENDACIONES', true),
        'search_enabled' => (bool) env('GELIA_AI_SEARCH_ENABLED', true),
        'search_base_url' => env('GELIA_AI_SEARCH_BASE_URL', 'https://api.deepseek.com'),
        'search_timeout_seconds' => (int) env('GELIA_AI_SEARCH_TIMEOUT', 8),
        'cache_days' => (int) env('GELIA_AI_PERFILES_CACHE_DAYS', 30),
        // Alias de búsqueda: no cambia marcas ni notas del catálogo.
        'fabricantes' => [
            ['aliases' => ['Ariana Grande', 'Ariana G.', 'Ariana G'], 'nombre' => 'Ariana Grande', 'dominio' => 'arianagrandefragrances.com'],
        ],
    ],
];
