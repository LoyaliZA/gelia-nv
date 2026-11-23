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

const round2 = (n) => Math.round(Number(n || 0) * 100) / 100;

/** Importe neto que el resumen financiero ya calculó. No registra el pago. */
export function importeNetoRestante(resumen, montoYaContado = 0) {
    const n = round2(Number(resumen?.pendiente || 0) + Math.max(0, Number(montoYaContado || 0)));
    return n > 0 ? n : 0;
}

/** Pendiente (positivo) o excedente (negativo) si esta exhibición queda en montoPropuesto. */
export function deltaPendienteExhibicion(resumen, { montoPropuesto = 0, montoYaContado = 0 } = {}) {
    return round2(importeNetoRestante(resumen, montoYaContado) - Number(montoPropuesto || 0));
}

/** Envío para proyectar cobertura: captura del formulario o, si falta, el resumen ya calculado. */
export function costoEnvioParaResumenVivo({
    omiteEnvio = false,
    costoEnvio = null,
    totalMercancia = 0,
    costoSeguro = 0,
    aplicaSeguro = false,
    resumen = null,
} = {}) {
    if (omiteEnvio) return 0;
    const capturado = Number(costoEnvio);
    if (Number.isFinite(capturado) && capturado > 0) return round2(capturado);
    const desdeTotales = Number(resumen?.totales_envio?.costo_para_cobertura);
    if (Number.isFinite(desdeTotales) && desdeTotales > 0) return round2(desdeTotales);
    const inferido = round2(
        Number(resumen?.total_a_cubrir || 0)
        - Number(totalMercancia || 0)
        - (aplicaSeguro ? Number(costoSeguro || 0) : 0),
    );
    return inferido > 0 ? inferido : 0;
}
