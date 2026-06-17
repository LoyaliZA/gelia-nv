<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enviar copia por correo en AlertaSolicitud
    |--------------------------------------------------------------------------
    | Desactivado por defecto: un fallo SMTP no debe impedir sonido/toast/Reverb.
    | Activar cuando MAIL_* esté configurado: ALERTAS_ENVIAR_CORREO=true
    */
    'enviar_correo' => env('ALERTAS_ENVIAR_CORREO', false),

    /*
    |--------------------------------------------------------------------------
    | Tonos de notificación disponibles
    |--------------------------------------------------------------------------
    | Agregar entradas aquí al subir nuevos MP3 en public/assets/sounds/
    */
    'tonos' => [
        [
            'id'      => 'default',
            'nombre'  => 'Campana clásica',
            'archivo' => 'notification.mp3',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preferencias de alertas por defecto (nuevos usuarios)
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'canales' => [
            'sonido'     => true,
            'voz'        => true,
            'escritorio' => true,
            'app'        => true,
        ],
        'tono_id' => 'default',
        'mensajeria_voz' => 'solo_aviso',
        'tipos'   => [
            'nueva'                    => true,
            'reparada'                 => true,
            'rechazada'                => true,
            'pago_rechazado'           => true,
            'pago_confirmado'          => true,
            'actualizacion'            => true,
            'alerta_pago_insuficiente' => true,
            'alerta_ascenso_lista'     => true,
            'consulta_nueva'           => true,
            'consulta_respondida'      => true,
            'rollback_confirmado'      => true,
            'cancelacion_solicitada'   => true,
            'cancelada'                => true,
            'resumen_vencidos'         => true,
            'activo_asignado'          => true,
            'activo_devuelto'          => true,
            'activo_transferido'       => true,
            'activo_mantenimiento'     => true,
            'activo_baja'              => true,
            'activo_vencimiento'       => true,
            'activo_mantenimiento_proximo' => true,
            'resumen_activos'          => true,
            'mensaje_nuevo'            => true,
            'woocommerce_sync_completada' => true,
            'woocommerce_sync_fallida'    => true,
            'soporte_ticket_nuevo'        => true,
            'soporte_respuesta_agente'    => true,
            'soporte_respuesta_usuario'   => true,
        ],
    ],

];
