const CLAVE_ETAPA = (usuarioId, pedidoId) => `control_pedidos.etapa_ui.${usuarioId || '0'}.${pedidoId}`;

/** Misma instante aunque el listado y el JSON del guardado usen formatos distintos. */
export function mismaMarcaTiempo(a, b) {
    if (!a || !b) return false;
    if (a === b) return true;
    const ta = Date.parse(a);
    const tb = Date.parse(b);
    return Number.isFinite(ta) && ta === tb;
}

/**
 * El eco del propio guardado trae la base ya confirmada.
 * Si la base difiere, no es eco: otra escritura cambió el pedido, aunque el reloj coincida.
 */
export function esEcoDeEstaSesion({ pedido, baseConfirmada, updatedAtEsperado }) {
    const confirmada = baseConfirmada?.base_captura;
    const remota = pedido?.base_captura;
    if (confirmada && remota) {
        return remota === confirmada
            && Number(pedido?.revision_registro ?? -1) === Number(baseConfirmada?.revision_esperada ?? -2);
    }
    return mismaMarcaTiempo(pedido?.updated_at, updatedAtEsperado);
}

export function guardarEtapaUi(usuarioId, pedidoId, codigo) {
    if (!pedidoId || !codigo || typeof sessionStorage === 'undefined') return;
    try {
        sessionStorage.setItem(CLAVE_ETAPA(usuarioId, pedidoId), codigo);
    } catch {
        /* cuota o modo privado */
    }
}

/** Etapa de interfaz. Si la guardada ya no existe, la última que sigue siendo válida. */
export function etapaAlReabrir(usuarioId, pedidoId, etapasValidas) {
    const ids = (etapasValidas || []).map((e) => (typeof e === 'string' ? e : e?.id)).filter(Boolean);
    if (!ids.length) return { codigo: null, aviso: null };
    let guardada = null;
    try {
        guardada = pedidoId ? sessionStorage.getItem(CLAVE_ETAPA(usuarioId, pedidoId)) : null;
    } catch {
        guardada = null;
    }
    if (!guardada || ids.includes(guardada)) {
        return { codigo: guardada && ids.includes(guardada) ? guardada : ids[0], aviso: null };
    }
    return {
        codigo: ids[ids.length - 1],
        aviso: 'La etapa anterior ya no está disponible. Se mostró la última etapa válida.',
    };
}

/** Importe neto que el resumen financiero ya calculó. No registra el pago. */
export function importeNetoRestante(resumen) {
    const n = Math.round(Number(resumen?.pendiente || 0) * 100) / 100;
    return n > 0 ? n : 0;
}
