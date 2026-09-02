<?php

namespace App\Support\ControlPedidos;

/**
 * Códigos tipados de movimiento en pedido_bma_historial_estados.accion.
 */
final class AccionesHistorialPedidoBma
{
    public const CREACION_BORRADOR = 'creacion_borrador';
    public const COMPLEMENTO = 'complemento';
    public const SOLICITUD_PESAJE = 'solicitud_pesaje';
    public const RESPUESTA_PESAJE = 'respuesta_pesaje';
    public const SOLICITUD_REPESAJE = 'solicitud_repesaje';
    public const ENVIO_AUXILIAR = 'envio_auxiliar';
    public const VOLVER_BORRADOR = 'volver_borrador';
    public const VALIDACION_PAGO = 'validacion_pago';
    public const CARGA_REMISION = 'carga_remision';
    public const ELIMINA_REMISION = 'elimina_remision';
    public const APROBACION = 'aprobacion';
    public const RECHAZO = 'rechazo';
    public const EMPAQUE = 'empaque';
    public const REVERTIR_EMPAQUE = 'revertir_empaque';
    public const CONSOLIDACION = 'consolidacion';
    public const DESCONSOLIDACION = 'desconsolidacion';
    public const ASIGNACION_GUIA = 'asignacion_guia';
    public const CARGA_GUIA_CLIENTE = 'carga_guia_cliente';
    public const ACTUALIZAR_GUIA = 'actualizar_guia';
    public const CARGA_GUIA_PDF = 'carga_guia_pdf';
    public const ELIMINA_GUIA_PDF = 'elimina_guia_pdf';
    public const INVALIDAR_GUIA = 'invalidar_guia';
    public const INCIDENCIA = 'incidencia';
    public const INCIDENCIA_SAF = 'incidencia_saf';
    public const ERROR_DATOS = 'error_datos';
    public const CORRECCION = 'correccion';
    public const CORRECCION_SAF = 'correccion_saf';
    public const ENVIO_FINAL = 'envio_final';
    public const REABRIR_ENVIO = 'reabrir_envio';
    public const ANEXO_PAGO_ENVIO = 'anexo_pago_envio';
    public const APROBAR_ANEXO = 'aprobar_anexo';
    public const RECHAZAR_ANEXO = 'rechazar_anexo';
    public const RESGUARDO = 'resguardo';
    public const LIBERAR_RESGUARDO = 'liberar_resguardo';
    public const CAMBIO_DIRECCION = 'cambio_direccion';
    public const CAMBIO_SUCURSAL_DESTINO = 'cambio_sucursal_destino';
    public const ALTA_EXHIBICION_PAGO = 'alta_exhibicion_pago';
    public const EDICION_EXHIBICION_PAGO = 'edicion_exhibicion_pago';
    public const BAJA_EXHIBICION_PAGO = 'baja_exhibicion_pago';
    public const REVISION_EXHIBICION_PAGO = 'revision_exhibicion_pago';
    public const RECHAZO_EXHIBICION_PAGO = 'rechazo_exhibicion_pago';
    public const SUSTITUCION_EXHIBICION_PAGO = 'sustitucion_exhibicion_pago';
    public const CANCELACION = 'cancelacion';
    public const DECISION_SIN_EXISTENCIA = 'decision_sin_existencia';
    public const STOCK_SIN_EXISTENCIA = 'stock_sin_existencia';
    public const REPORTE_SIN_EXISTENCIA = 'reporte_sin_existencia';
    public const CIERRE_CONSULTA = 'cierre_consulta';
    public const REABRIR_CONSULTA = 'reabrir_consulta';
    public const ACTUALIZAR_CONSULTA = 'actualizar_consulta';
    public const SESION_EVIDENCIA = 'sesion_evidencia';
    public const RETRASO_EMPAQUE = 'retraso_empaque';
    public const RETRASO_RECOLECCION = 'retraso_recoleccion';
    public const RETIRO_CAJA = 'retiro_caja';
    public const COSTOS_ENVIO = 'costos_envio';
    public const REAPERTURA_PAGO_COSTOS = 'reapertura_pago_costos';
    public const SOLICITUD_PREPARACION_TIENDA = 'solicitud_preparacion_tienda';
    public const RESPUESTA_PREPARACION_TIENDA = 'respuesta_preparacion_tienda';
    public const INCIDENCIA_PREPARACION_TIENDA = 'incidencia_preparacion_tienda';
    public const CORRECCION_PREPARACION_TIENDA = 'correccion_preparacion_tienda';
    public const LIBERACION_PREPARACION_TIENDA = 'liberacion_preparacion_tienda';
    public const TRASLADO_PREPARACION_CREADO = 'traslado_preparacion_creado';
    public const TRASLADO_PREPARACION_EN_CAMINO = 'traslado_preparacion_en_camino';
    public const TRASLADO_PREPARACION_RECIBIDO = 'traslado_preparacion_recibido';
    public const TRASLADO_PREPARACION_RECHAZADO = 'traslado_preparacion_rechazado';
    public const CARATULA_GENERADA = 'caratula_generada';
    public const CARATULA_REGENERADA = 'caratula_regenerada';
    public const CARATULA_COLOCADA = 'caratula_colocada';
    public const DESCARGA_IDENTIFICACION = 'descarga_identificacion_municipal';
    public const DESCARGA_CARATULA = 'descarga_caratula';
    public const ESPERA_PAGO = 'espera_pago';
    public const SALIDA_ESPERA_PAGO = 'salida_espera_pago';
    public const VENCIMIENTO_ESPERA_PAGO = 'vencimiento_espera_pago';
    public const CANCELACION_OPERATIVA_SOLICITADA = 'cancelacion_operativa_solicitada';
    public const LIBERACION_CANCELACION_TAREA = 'liberacion_cancelacion_tarea';
    public const INCIDENCIA_LIBERACION_CANCELACION = 'incidencia_liberacion_cancelacion';
    public const REACTIVACION_CANCELACION = 'reactivacion_cancelacion';
    public const FINALIZACION_CANCELACION_OPERATIVA = 'finalizacion_cancelacion_operativa';
    public const BLOQUEO_FINANCIERO_CANCELACION = 'bloqueo_financiero_cancelacion';
    public const CONFIRMACION_ADMIN_EXHIBICION = 'confirmacion_admin_exhibicion';
    public const CONFIRMACION_ADMIN_PEDIDO = 'confirmacion_admin_pedido';
    public const ERROR_ADMIN_REPORTE_PAGO = 'error_admin_reporte_pago';
    public const ENTREGA_PDV = 'entrega_pdv';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::CREACION_BORRADOR => 'Creación del borrador',
        self::COMPLEMENTO => 'Complemento de pedido',
        self::SOLICITUD_PESAJE => 'Solicitud de pesaje',
        self::RESPUESTA_PESAJE => 'Respuesta de CEDIS (pesaje)',
        self::SOLICITUD_REPESAJE => 'Solicitud de re-pesaje',
        self::CIERRE_CONSULTA => 'Cierre de consulta CEDIS',
        self::REABRIR_CONSULTA => 'Reapertura de consulta CEDIS',
        self::ACTUALIZAR_CONSULTA => 'Actualización de consulta CEDIS',
        self::ENVIO_AUXILIAR => 'Envío a auxiliar',
        self::VOLVER_BORRADOR => 'Conservado como borrador',
        self::VALIDACION_PAGO => 'Validación del pago',
        self::CARGA_REMISION => 'Carga de remisión',
        self::ELIMINA_REMISION => 'Eliminación de remisión',
        self::APROBACION => 'Aprobación / envío a CEDIS',
        self::RECHAZO => 'Rechazo',
        self::EMPAQUE => 'Empaque',
        self::REVERTIR_EMPAQUE => 'Reversión de empaque',
        self::CONSOLIDACION => 'Consolidación de empaque',
        self::DESCONSOLIDACION => 'Desconsolidación',
        self::ASIGNACION_GUIA => 'Asignación de guía',
        self::CARGA_GUIA_CLIENTE => 'Carga de guía del cliente',
        self::ACTUALIZAR_GUIA => 'Corrección de guía',
        self::CARGA_GUIA_PDF => 'Carga de PDF de guía',
        self::ELIMINA_GUIA_PDF => 'Eliminación de PDF de guía',
        self::INVALIDAR_GUIA => 'Invalidación de guía',
        self::INCIDENCIA => 'Incidencia',
        self::INCIDENCIA_SAF => 'Incidencia de saldo a favor',
        self::ERROR_DATOS => 'Error de datos',
        self::CORRECCION => 'Corrección',
        self::CORRECCION_SAF => 'Corrección de saldo a favor',
        self::ENVIO_FINAL => 'Paquetería recogió el paquete',
        self::REABRIR_ENVIO => 'Reapertura de envío',
        self::ANEXO_PAGO_ENVIO => 'Anexo de pago de envío',
        self::APROBAR_ANEXO => 'Aprobación de anexo',
        self::RECHAZAR_ANEXO => 'Rechazo de anexo',
        self::RESGUARDO => 'Resguardo / apartado',
        self::LIBERAR_RESGUARDO => 'Liberación de resguardo',
        self::CAMBIO_DIRECCION => 'Cambio de dirección',
        self::CAMBIO_SUCURSAL_DESTINO => 'Cambio de sucursal destino',
        self::ALTA_EXHIBICION_PAGO => 'Alta de exhibición de pago',
        self::EDICION_EXHIBICION_PAGO => 'Edición de exhibición de pago',
        self::BAJA_EXHIBICION_PAGO => 'Baja de exhibición de pago',
        self::REVISION_EXHIBICION_PAGO => 'Revisión de exhibición de pago',
        self::RECHAZO_EXHIBICION_PAGO => 'Rechazo de exhibición de pago',
        self::SUSTITUCION_EXHIBICION_PAGO => 'Sustitución de comprobante de pago',
        self::CANCELACION => 'Cancelación del pedido',
        self::DECISION_SIN_EXISTENCIA => 'Decisión por sin existencias',
        self::STOCK_SIN_EXISTENCIA => 'Existencias confirmadas (CEDIS)',
        self::REPORTE_SIN_EXISTENCIA => 'CEDIS reportó sin existencias',
        self::SESION_EVIDENCIA => 'Sesión de evidencias con celular',
        self::RETRASO_EMPAQUE => 'Retraso de empaque',
        self::RETRASO_RECOLECCION => 'Retraso de recolección',
        self::RETIRO_CAJA => 'Retiro de envío',
        self::COSTOS_ENVIO => 'Actualización de costos de envío',
        self::REAPERTURA_PAGO_COSTOS => 'Reapertura de validación de pago por costos',
        self::SOLICITUD_PREPARACION_TIENDA => 'Solicitud de preparación en Tienda',
        self::RESPUESTA_PREPARACION_TIENDA => 'Respuesta de preparación en Tienda',
        self::INCIDENCIA_PREPARACION_TIENDA => 'Incidencia de preparación en Tienda',
        self::CORRECCION_PREPARACION_TIENDA => 'Corrección de preparación en Tienda',
        self::LIBERACION_PREPARACION_TIENDA => 'Liberación de mercancía en Tienda',
        self::TRASLADO_PREPARACION_CREADO => 'Traspaso generado desde preparación',
        self::TRASLADO_PREPARACION_EN_CAMINO => 'Mercancía en traslado a CEDIS',
        self::TRASLADO_PREPARACION_RECIBIDO => 'CEDIS recibió traslado de Tienda',
        self::TRASLADO_PREPARACION_RECHAZADO => 'CEDIS rechazó traslado de Tienda',
        self::CARATULA_GENERADA => 'Carátula municipal generada',
        self::CARATULA_REGENERADA => 'Carátula municipal regenerada',
        self::CARATULA_COLOCADA => 'Carátula colocada en el paquete',
        self::DESCARGA_IDENTIFICACION => 'Descarga de identificación municipal',
        self::DESCARGA_CARATULA => 'Descarga de carátula PDF',
        self::ESPERA_PAGO => 'Entrada en espera de pago',
        self::SALIDA_ESPERA_PAGO => 'Salida de espera de pago',
        self::VENCIMIENTO_ESPERA_PAGO => 'Vencimiento de espera de pago',
        self::CANCELACION_OPERATIVA_SOLICITADA => 'Cancelación operativa solicitada',
        self::LIBERACION_CANCELACION_TAREA => 'Liberación física por cancelación',
        self::INCIDENCIA_LIBERACION_CANCELACION => 'Incidencia en liberación por cancelación',
        self::REACTIVACION_CANCELACION => 'Reactivación de pedido (cancelación revertida)',
        self::FINALIZACION_CANCELACION_OPERATIVA => 'Finalización de cancelación operativa',
        self::BLOQUEO_FINANCIERO_CANCELACION => 'Bloqueo por resolución financiera',
        self::CONFIRMACION_ADMIN_EXHIBICION => 'Confirmación administrativa de exhibición',
        self::CONFIRMACION_ADMIN_PEDIDO => 'Confirmación administrativa del pedido',
        self::ERROR_ADMIN_REPORTE_PAGO => 'Error reportado por Administración (pagos)',
        self::ENTREGA_PDV => 'Entrega presencial en sucursal (Punto de venta)',
    ];

    public static function etiqueta(?string $accion): ?string
    {
        if ($accion === null || $accion === '') {
            return null;
        }

        return self::ETIQUETAS[$accion] ?? $accion;
    }
}
