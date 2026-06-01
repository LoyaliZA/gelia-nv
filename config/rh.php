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

    'he_tarifa_hora_fija' => (float) env('RH_HE_TARIFA_HORA_FIJA', 39.00),

    'he_usar_tarifa_fija' => filter_var(env('RH_HE_USAR_TARIFA_FIJA', true), FILTER_VALIDATE_BOOL),

    'he_gracia_minutos_despues_salida' => (int) env('RH_HE_GRACIA_MINUTOS_DESPUES_SALIDA', 30),

    'inc_folio_prefijo' => env('RH_INC_FOLIO_PREFIJO', 'DED'),

    'inc_folio_padding' => (int) env('RH_INC_FOLIO_PADDING', 6),

    'pre_folio_prefijo' => env('RH_PRE_FOLIO_PREFIJO', 'PRE'),

    'pre_folio_padding' => (int) env('RH_PRE_FOLIO_PADDING', 6),

];
