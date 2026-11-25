/**
 * Los ajustes nacionales (anexo, retiro, sustitución) se guardan en `ajustes_nacionales`.
 * La revisión de CEDIS reutiliza la de Tienda (`cambiosDisponibilidad`), que lee
 * `anexo_sin_remision` y `retiro_piezas`; este adaptador traduce los ajustes pendientes a ese formato.
 */
export function ajustesPendientesNacional(pedido = {}) {
    const solicitud = pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || {};
    const ajustes = (Array.isArray(solicitud.ajustes_nacionales) ? solicitud.ajustes_nacionales : [])
        .filter((ajuste) => ['anexo', 'retiro', 'sustitucion'].includes(ajuste?.tipo));
    const corte = pedido.pesaje_respondido_at ? new Date(pedido.pesaje_respondido_at).getTime() : null;
    if (corte === null) return ajustes;
    const documentos = pedido.documentos || [];
    // Los ajustes anteriores no guardaban fecha: se infiere de sus documentos; sin documentos se consideran atendidos.
    const fechaDe = (ajuste) => {
        if (ajuste.registrado_at) return new Date(ajuste.registrado_at).getTime();
        const ids = new Set((ajuste.documento_ids || []).map(Number));
        const fechas = documentos.filter((doc) => ids.has(Number(doc.id)) && doc.created_at).map((doc) => new Date(doc.created_at).getTime());

        return fechas.length ? Math.max(...fechas) : 0;
    };

    return ajustes.filter((ajuste) => fechaDe(ajuste) > corte);
}

export function solicitudNacionalConCambios(pedido = {}, { incluirHistorial = false } = {}) {
    const solicitud = pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || {};
    if (!pedido.consulta_actualizacion_pendiente && !incluirHistorial) return solicitud;
    const documentos = pedido.documentos || [];
    const anexos = [];
    const retiros = [];
    const pendientes = pedido.consulta_actualizacion_pendiente ? ajustesPendientesNacional(pedido) : [];
    const anteriores = incluirHistorial ? (solicitud.ajustes_nacionales || [])
        .filter(ajuste => ajuste?.tipo === 'sustitucion' && !pendientes.includes(ajuste)) : [];
    for (const ajuste of [...pendientes, ...anteriores]) {
        const ids = new Set((ajuste.documento_ids || []).map(Number));
        const docs = documentos.filter((doc) => ids.has(Number(doc.id)) && doc.activo !== false);
        const base = { linea_id: ajuste.solicitud_id, solicitud_id: ajuste.solicitud_id, solicitado_at: ajuste.registrado_at, pendiente_revision: true };
        if (ajuste.tipo === 'retiro') {
            retiros.push({ ...base, pendiente_revision: false, piezas_solicitadas: ajuste.piezas || [], documentos_piezas: docs });
        }
        if (ajuste.tipo === 'anexo' || ajuste.tipo === 'sustitucion') {
            const alternativa = (ajuste.alternativas || [])[0];
            const originales = ajuste.tipo === 'sustitucion' ? (ajuste.piezas || []) : [];
            const historico = anteriores.includes(ajuste);
            const cambio = { ...base, pendiente_revision: !historico, movimiento_historico: historico,
                sustituye_revision_id: originales[0]?.revision_id, piezas_originales: originales };
            if (docs.length) {
                anexos.push({ ...cambio, documento_id: docs[0].id, documentos: docs, entrada: 'documento', descripcion: alternativa?.descripcion, sku: alternativa?.sku, cantidad: alternativa?.cantidad });
            } else if (alternativa) {
                anexos.push({ ...cambio, entrada: 'catalogo', sku: alternativa.sku, descripcion: alternativa.descripcion, cantidad: alternativa.cantidad, documentos: [] });
            } else if (originales.length && historico) {
                anexos.push({ ...cambio, entrada: 'documento', descripcion: 'Alternativa en captura' });
            }
        }
    }

    return {
        ...solicitud,
        anexo_sin_remision: [...(solicitud.anexo_sin_remision || []), ...anexos],
        retiro_piezas: [...(solicitud.retiro_piezas || []), ...retiros],
    };
}
