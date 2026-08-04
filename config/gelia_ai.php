<?php

return [
    'max_history_turns' => (int) env('GELIA_AI_MAX_HISTORY_TURNS', 4),
    'max_tokens' => (int) env('GELIA_AI_MAX_TOKENS', 400),
    'max_tool_rounds' => (int) env('GELIA_AI_MAX_TOOL_ROUNDS', 2),
    'inventario_limit_default' => 3,
    'inventario_limit_max' => 5,
    'inventario_stock_rows_max' => 3,
    'timeout_seconds' => (int) env('GELIA_AI_TIMEOUT', 45),
];
