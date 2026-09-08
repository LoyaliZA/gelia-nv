/** Tiempo que permanece visible un evento en la cola antes de expirar. */
export const PDV_COLA_EXPIRACION_MS = 60_000;

/** Máximo de eventos visibles en la cola. */
export const PDV_COLA_MAX_VISIBLE = 8;

/** Máximo de IDs recordados para deduplicación. */
export const PDV_COLA_MAX_IDS_VISTOS = 200;

export const PDV_ESTADO_CONEXION = {
    desconectado: 'desconectado',
    conectando: 'conectando',
    conectado: 'conectado',
    degradado: 'degradado',
};

const ETIQUETAS_EVENTO = {
    'turno.alta': 'Nuevo turno en cola',
    'turno.asignado': 'Turno asignado',
    'turno.reatencion': 'Reatención de turno',
    'turno.transferido': 'Turno transferido',
    'atencion.cerrada': 'Atención cerrada',
    'atencion.prorroga': 'Prórroga de atención',
    'atencion.espera_proximo_vencer': 'Turno próximo a vencer',
    'atencion.prorroga_proximo_vencer': 'Prórroga próxima a vencer',
    'resguardo.recepcion_esperada_creada': 'Recepción esperada de resguardo',
    'jornada.abierta': 'Jornada abierta',
    'jornada.cerrada': 'Jornada cerrada',
    'jornada.cierre_manual': 'Cierre manual de jornada',
    'jornada.ampliada': 'Jornada ampliada',
    'pausa.iniciada': 'Pausa iniciada',
    'pausa.finalizada': 'Pausa finalizada',
};

export function normalizarEnvelopePdv(payload) {
    if (!payload || typeof payload !== 'object') return null;
    const eventId = String(payload.event_id || '').trim();
    if (!eventId) return null;

    return {
        event_id: eventId,
        tipo: String(payload.tipo || 'pdv.cambio'),
        dominio: String(payload.dominio || ''),
        audiencia: String(payload.audiencia || ''),
        sucursal_id: Number(payload.sucursal_id) || null,
        version: Number(payload.version) || 0,
        payload_version: Number(payload.payload_version) || 1,
        ocurrido_at: payload.ocurrido_at || new Date().toISOString(),
        datos: payload.datos && typeof payload.datos === 'object' ? payload.datos : {},
    };
}

export function mensajeAlertaPdv(envelope) {
    const tipo = envelope?.tipo || '';
    if (ETIQUETAS_EVENTO[tipo]) return ETIQUETAS_EVENTO[tipo];

    if (tipo.startsWith('resguardo.')) {
        return 'Actualización de resguardo';
    }
    if (tipo.startsWith('jornada.')) {
        return 'Actualización de operación';
    }
    if (tipo.startsWith('turno.') || tipo.startsWith('atencion.')) {
        return 'Actualización de turnos';
    }

    return 'Actualización en punto de venta';
}

export function ordenarColaPdv(alertas) {
    return [...alertas].sort((a, b) => {
        const fechaA = new Date(a.ocurrido_at || 0).getTime();
        const fechaB = new Date(b.ocurrido_at || 0).getTime();
        if (fechaA !== fechaB) return fechaA - fechaB;
        return String(a.event_id).localeCompare(String(b.event_id));
    });
}

export function encolarAlertaPdv(colaActual, idsVistos, envelope, ahoraMs = Date.now()) {
    const normalizado = normalizarEnvelopePdv(envelope);
    if (!normalizado) {
        return { cola: colaActual, idsVistos, encolado: false };
    }

    if (idsVistos.has(normalizado.event_id)) {
        return { cola: colaActual, idsVistos, encolado: false };
    }

    const idsSiguiente = new Set(idsVistos);
    idsSiguiente.add(normalizado.event_id);
    if (idsSiguiente.size > PDV_COLA_MAX_IDS_VISTOS) {
        const recortados = [...idsSiguiente].slice(-PDV_COLA_MAX_IDS_VISTOS);
        idsSiguiente.clear();
        recortados.forEach((id) => idsSiguiente.add(id));
    }

    const entrada = {
        ...normalizado,
        mensaje: mensajeAlertaPdv(normalizado),
        recibido_at_ms: ahoraMs,
        visible_hasta_ms: ahoraMs + PDV_COLA_EXPIRACION_MS,
    };

    const sinDuplicado = colaActual.filter((item) => item.event_id !== normalizado.event_id);
    const cola = ordenarColaPdv([...sinDuplicado, entrada]).slice(-PDV_COLA_MAX_VISIBLE);

    return { cola, idsVistos: idsSiguiente, encolado: true, entrada };
}

export function expirarColaPdv(cola, ahoraMs = Date.now()) {
    return cola.filter((item) => (item.visible_hasta_ms ?? 0) > ahoraMs);
}

export function mapearEstadoConexionPdv(estadoPusher) {
    const estado = String(estadoPusher || '').toLowerCase();
    if (estado === 'connected') return PDV_ESTADO_CONEXION.conectado;
    if (estado === 'connecting') return PDV_ESTADO_CONEXION.conectando;
    if (estado === 'unavailable' || estado === 'failed') return PDV_ESTADO_CONEXION.degradado;
    return PDV_ESTADO_CONEXION.desconectado;
}

export function conexionDegradadaPdv(estadoConexion) {
    return estadoConexion !== PDV_ESTADO_CONEXION.conectado;
}

export function etiquetaConexionTiempoRealPdv(estadoConexion) {
    if (estadoConexion === PDV_ESTADO_CONEXION.conectado) return 'En vivo';
    if (estadoConexion === PDV_ESTADO_CONEXION.conectando) return 'Reconectando';
    return 'Sin conexión';
}

export function mensajeConexionDegradadaPdv(estadoConexion) {
    if (estadoConexion === PDV_ESTADO_CONEXION.conectando) {
        return 'Reconectando actualizaciones en tiempo real…';
    }
    if (estadoConexion === PDV_ESTADO_CONEXION.degradado) {
        return 'Sin conexión en tiempo real. Se conserva el último estado conocido; puedes usar Actualizar como respaldo.';
    }
    return 'Sin conexión en tiempo real. Se conserva el último estado conocido; puedes usar Actualizar como respaldo.';
}
