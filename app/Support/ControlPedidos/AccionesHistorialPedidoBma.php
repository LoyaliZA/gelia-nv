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
    ];

    public static function etiqueta(?string $accion): ?string
    {
        if ($accion === null || $accion === '') {
            return null;
        }

        return self::ETIQUETAS[$accion] ?? $accion;
    }
}
