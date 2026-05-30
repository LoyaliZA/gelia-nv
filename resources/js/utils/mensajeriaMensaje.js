/**
 * Los mensajes por WebSocket vienen sin campos dependientes del viewer.
 * Recalcula es_propio y estado_lectura para quien está viendo el chat.
 */
export function normalizarMensajeParaViewer(mensaje, viewerUserId) {
    if (!mensaje || viewerUserId == null) return mensaje;

    const esPropio = mensaje.user?.id === viewerUserId;

    return {
        ...mensaje,
        es_propio: esPropio,
        estado_lectura: esPropio ? (mensaje.estado_lectura ?? 'enviado') : 'enviado',
    };
}
