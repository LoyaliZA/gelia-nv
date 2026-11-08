import { etiquetaTipoMensaje } from '@/utils/mensajeriaRespuesta';

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

export function conversacionConMensajeNuevo(conversacion, mensaje, chatAbierto) {
    const previo = conversacion.ultimo_mensaje_at
        ? new Date(conversacion.ultimo_mensaje_at).getTime()
        : 0;
    const llegada = mensaje?.created_at ? new Date(mensaje.created_at).getTime() : 0;
    const yaEstaba = previo > 0 && llegada > 0 && previo >= llegada;

    return {
        ...conversacion,
        ultimo_mensaje_preview: mensaje.contenido?.trim()
            ? mensaje.contenido
            : etiquetaTipoMensaje(mensaje.tipo),
        ultimo_mensaje_at: mensaje.created_at || conversacion.ultimo_mensaje_at,
        unread_count: chatAbierto
            ? 0
            : (yaEstaba ? (conversacion.unread_count || 0) : (conversacion.unread_count || 0) + 1),
    };
}

export function aplicarCursorLectura({ conversaciones, mensajes, conversacionActivaId, detalle }) {
    if (!detalle?.conversacion_id) {
        return { conversaciones, mensajes };
    }

    const id = Number(detalle.conversacion_id);

    if (detalle.alcance === 'lector') {
        return {
            conversaciones: conversaciones.map((c) => (
                Number(c.id) === id ? { ...c, unread_count: 0 } : c
            )),
            mensajes,
        };
    }

    if (detalle.alcance !== 'autor' || Number(conversacionActivaId) !== id) {
        return { conversaciones, mensajes };
    }

    const mapa = new Map((detalle.mensajes || []).map((m) => [Number(m.id), m.estado_lectura]));
    const hasta = detalle.leido_hasta_id != null ? Number(detalle.leido_hasta_id) : null;
    const estadoHasta = detalle.estado_lectura || 'leido';

    return {
        conversaciones,
        mensajes: mensajes.map((m) => {
            if (mapa.has(Number(m.id))) {
                return {
                    ...m,
                    estado_lectura: fusionarEstadoLectura(m.estado_lectura, mapa.get(Number(m.id))),
                };
            }
            if (hasta != null && m.es_propio && Number(m.id) > 0 && Number(m.id) <= hasta) {
                return {
                    ...m,
                    estado_lectura: fusionarEstadoLectura(m.estado_lectura, estadoHasta),
                };
            }
            return m;
        }),
    };
}

export function conservarPendientes(servidor, locales) {
    const pendientes = (locales || []).filter((m) => m.pendiente);
    const base = servidor || [];
    if (!pendientes.length) return base;
    const ids = new Set(base.map((m) => m.id));
    return [...base, ...pendientes.filter((m) => !ids.has(m.id))];
}

export function fusionarMensajesHilo(previos, frescos) {
    const porId = new Map((frescos || []).map((m) => [m.id, m]));
    const vistos = new Set();
    const mezcla = (previos || []).map((m) => {
        vistos.add(m.id);
        if (!porId.has(m.id)) return m;
        return { ...m, ...porId.get(m.id) };
    });

    (frescos || []).forEach((m) => {
        if (!vistos.has(m.id)) mezcla.push(m);
    });

    return mezcla.sort((a, b) => {
        const ap = a.pendiente ? 1 : 0;
        const bp = b.pendiente ? 1 : 0;
        if (ap !== bp) return ap - bp;
        return Number(a.id) - Number(b.id);
    });
}
