/** Eventos que agregan o actualizan un llamado visible en sala. */
export const PDV_SALA_TIPOS_LLAMADO = new Set([
    'turno.asignado',
    'turno.reatencion',
    'turno.transferido',
]);

/** Campos que no deben aparecer en payload/render de sala pública. */
export const PDV_SALA_CAMPOS_PROHIBIDOS = [
    'prioridad_vip',
    'prioridad_adulto_mayor',
    'prioridad_discapacidad',
    'cliente_id',
    'snapshot_cliente_nombre',
    'telefono',
    'rfc',
    'correo',
    'pedido_id',
];

export function llamadoDesdeDatosPublicos(datos = {}, extra = {}) {
    if (!datos || typeof datos !== 'object') return null;

    const turnoId = Number(datos.turno_id);
    const folio = String(datos.folio || '').trim();
    if (!turnoId || !folio) return null;

    return {
        turno_id: turnoId,
        folio,
        servicio: datos.servicio ?? null,
        estado: datos.estado ?? null,
        prioridad_diamante: Boolean(datos.prioridad_diamante),
        snapshot_nombre_llamado: String(datos.snapshot_nombre_llamado || '').trim() || null,
        atencion_primer_nombre: datos.atencion_primer_nombre
            ? String(datos.atencion_primer_nombre).trim()
            : null,
        llamado_at: extra.llamado_at ?? datos.llamado_at ?? null,
        event_id: extra.event_id ?? null,
        ocurrido_at: extra.ocurrido_at ?? null,
    };
}

export function llamadoDesdeEnvelope(envelope) {
    if (!envelope?.datos) return null;

    return llamadoDesdeDatosPublicos(envelope.datos, {
        event_id: envelope.event_id,
        ocurrido_at: envelope.ocurrido_at,
        llamado_at: envelope.ocurrido_at,
    });
}

export function esEventoLlamadoSala(envelope) {
    return PDV_SALA_TIPOS_LLAMADO.has(String(envelope?.tipo || ''));
}

export function esEventoQuitarLlamadoSala(envelope) {
    return String(envelope?.tipo || '') === 'atencion.cerrada';
}

export function aplicarEventoSala(llamados, envelope) {
    const lista = Array.isArray(llamados) ? [...llamados] : [];

    if (esEventoQuitarLlamadoSala(envelope)) {
        const turnoId = Number(envelope?.datos?.turno_id);
        if (!turnoId) return lista;

        return lista.filter((item) => item.turno_id !== turnoId);
    }

    if (!esEventoLlamadoSala(envelope)) {
        return lista;
    }

    const llamado = llamadoDesdeEnvelope(envelope);
    if (!llamado) return lista;

    const indice = lista.findIndex((item) => item.turno_id === llamado.turno_id);
    if (indice >= 0) {
        lista[indice] = { ...lista[indice], ...llamado };
    } else {
        lista.push(llamado);
    }

    return ordenarLlamadosSala(lista);
}

export function ordenarLlamadosSala(llamados) {
    return [...(llamados || [])].sort((a, b) => {
        const fechaA = new Date(a.llamado_at || a.ocurrido_at || 0).getTime();
        const fechaB = new Date(b.llamado_at || b.ocurrido_at || 0).getTime();
        if (fechaA !== fechaB) return fechaB - fechaA;
        return Number(b.turno_id) - Number(a.turno_id);
    });
}

export function fusionarEstadoSala(desdeApi = [], desdeEventos = []) {
    const mapa = new Map();

    desdeApi.forEach((item) => {
        const llamado = llamadoDesdeDatosPublicos(item, { llamado_at: item.llamado_at });
        if (llamado) mapa.set(llamado.turno_id, llamado);
    });

    desdeEventos.forEach((item) => {
        const llamado = llamadoDesdeDatosPublicos(item, {
            llamado_at: item.llamado_at,
            event_id: item.event_id,
            ocurrido_at: item.ocurrido_at,
        });
        if (llamado) mapa.set(llamado.turno_id, { ...mapa.get(llamado.turno_id), ...llamado });
    });

    return ordenarLlamadosSala([...mapa.values()]);
}

export function llamadoActualSala(llamados) {
    const ordenados = ordenarLlamadosSala(llamados);
    return ordenados[0] ?? null;
}

export function payloadSalaSinDatosSensibles(payload) {
    if (!payload || typeof payload !== 'object') return payload;

    const copia = JSON.parse(JSON.stringify(payload));
    const limpiar = (obj) => {
        if (!obj || typeof obj !== 'object') return;
        PDV_SALA_CAMPOS_PROHIBIDOS.forEach((campo) => {
            delete obj[campo];
        });
        Object.values(obj).forEach((valor) => {
            if (valor && typeof valor === 'object') limpiar(valor);
        });
    };

    limpiar(copia);
    return copia;
}

export function etiquetaServicioSala(servicio) {
    const valor = String(servicio || '').trim().toLowerCase();
    if (valor === 'ventas') return 'Ventas';
    return servicio ? String(servicio) : 'Ventas';
}
