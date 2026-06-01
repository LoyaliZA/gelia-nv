<?php

return [

    'folio_prefijo' => env('RH_FOLIO_PREFIJO', 'COL'),

    'folio_separador' => env('RH_FOLIO_SEPARADOR', '-'),

    'folio_padding' => (int) env('RH_FOLIO_PADDING', 6),

    'folio_incluir_anio' => filter_var(env('RH_FOLIO_INCLUIR_ANIO', false), FILTER_VALIDATE_BOOL),

    'dias_periodo_pago' => (int) env('RH_DIAS_PERIODO_PAGO', 30),

    'decimales_salario_minuto' => (int) env('RH_DECIMALES_SALARIO_MINUTO', 8),

    'he_folio_prefijo' => env('RH_HE_FOLIO_PREFIJO', 'HE'),

    'he_folio_padding' => (int) env('RH_HE_FOLIO_PADDING', 6),

    'he_multiplicador_pago' => (float) env('RH_HE_MULTIPLICADOR_PAGO', 2.00),

    'he_minutos_minimos' => (int) env('RH_HE_MINUTOS_MINIMOS', 30),

    'inc_folio_prefijo' => env('RH_INC_FOLIO_PREFIJO', 'INC'),

    'inc_folio_padding' => (int) env('RH_INC_FOLIO_PADDING', 6),

];
