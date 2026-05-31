export function esMismoUsuario(a, b) {
    if (a == null || b == null) return false;
    return Number(a) === Number(b);
}

/**
 * Los mensajes por WebSocket vienen sin campos dependientes del viewer.
 * Recalcula es_propio y estado_lectura para quien está viendo el chat.
 */
export function normalizarMensajeParaViewer(mensaje, viewerUserId) {
    if (!mensaje || viewerUserId == null) return mensaje;

    const esPropio = esMismoUsuario(mensaje.user?.id, viewerUserId);

    return {
        ...mensaje,
        es_propio: esPropio,
        estado_lectura: esPropio ? (mensaje.estado_lectura ?? 'enviado') : 'enviado',
    };
}

/** Prioridad: leido > entregado > enviado */
const PRIORIDAD_ESTADO = { enviado: 0, entregado: 1, leido: 2 };

export function fusionarEstadoLectura(actual, nuevo) {
    if (!nuevo) return actual ?? 'enviado';
    if (!actual) return nuevo;
    return (PRIORIDAD_ESTADO[nuevo] ?? 0) >= (PRIORIDAD_ESTADO[actual] ?? 0) ? nuevo : actual;
}

/**
 * Aplica actualización de lectura (evento mensaje.leido) sobre un mensaje en lista.
 */
export function aplicarActualizacionLectura(mensajeLocal, mensajeActualizado, viewerUserId) {
    if (!mensajeLocal || !mensajeActualizado) return mensajeLocal;
    if (mensajeLocal.id !== mensajeActualizado.id) return mensajeLocal;
    if (!esMismoUsuario(mensajeLocal.user?.id, viewerUserId)) return mensajeLocal;

    const normalizado = normalizarMensajeParaViewer(mensajeActualizado, viewerUserId);

    return {
        ...mensajeLocal,
        estado_lectura: fusionarEstadoLectura(mensajeLocal.estado_lectura, normalizado.estado_lectura),
        lecturas_count: normalizado.lecturas_count ?? mensajeLocal.lecturas_count,
    };
}
