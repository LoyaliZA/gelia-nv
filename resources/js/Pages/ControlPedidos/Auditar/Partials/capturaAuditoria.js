/** Campos y diálogos de la ficha de auditoría que no deben perderse al cambiar de envío. */
export function hayCapturaPendienteAuditoria({
    numeroRemision = '',
    numeroGuardado = '',
    fechaEmision = '',
    fechaGuardada = '',
    rechazoAbierto = false,
    motivoAnexoAbierto = false,
    errorDatosAbierto = false,
    liberarCapturaAbierto = false,
    confirmacion = null,
} = {}) {
    const confirmacionAjena = Boolean(confirmacion) && confirmacion.accion !== 'cambiar_envio';

    return String(numeroRemision ?? '') !== String(numeroGuardado ?? '')
        || String(fechaEmision ?? '') !== String(fechaGuardada ?? '')
        || Boolean(rechazoAbierto)
        || Boolean(motivoAnexoAbierto)
        || Boolean(errorDatosAbierto)
        || Boolean(liberarCapturaAbierto)
        || confirmacionAjena;
}
