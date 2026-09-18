<?php

return [

    'token_expiration_days' => (int) env('MOBILE_TOKEN_EXPIRATION_DAYS', 30),

    'field_policy_version' => 1,

    'serializer_version' => 1,

    'changes_page_size' => 200,

    'bootstrap_page_size' => 100,

    'bootstrap_ttl_hours' => 24,

    'notify_debounce_seconds' => 4,

    'campos' => [
        'base' => [
            'id',
            'numero_cliente',
            'nombre',
            'nombre_razon_social',
            'rfc',
            'lista_actual_id',
            'lista_descuento',
            'vendedor_id',
            'vendedor_original_id',
            'vendedor',
            'catalogo_tipo_cliente_id',
            'tipo_cliente',
            'es_inactivo',
            'es_heredado',
            'lista_bloqueada',
            'created_at',
            'updated_at',
        ],
        'credito' => [
            'monto_credito_autorizado',
            'dias_credito',
            'fecha_inicio_credito',
        ],
        'fiscal' => [
            'codigo_postal',
            'regimen_fiscal',
            'correo_electronico',
            'uso_factura',
            'direccion_fiscal',
            'colonia_fiscal',
            'municipio_fiscal',
            'estado_fiscal',
            'pais_fiscal',
        ],
    ],

    'permisos_campos' => [
        'base' => [],
        'credito' => ['cobranza.editar_credito'],
        'fiscal' => ['clientes.ver'],
    ],

    'permisos_alcance' => [
        'clientes.ver',
        'mis_clientes.gestionar',
    ],

    'permisos_relevantes' => [
        'clientes.ver',
        'mis_clientes.gestionar',
        'cobranza.editar_credito',
    ],

];
