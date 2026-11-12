/** Conserva los índices guardados: dos solicitudes del mismo SKU son cambios distintos. */
export function cambiosDisponibilidad(solicitud = {}, documentos = []) {
    const decisiones = Object.values(solicitud.acciones_disponibilidad || {});
    const corte = solicitud.revision_disponibilidad?.respondida_at;
    const doc = id => documentos.find(d => Number(d.id) === Number(id));
    const anexos = (solicitud.anexo_sin_remision || []).map((a, indice) => ({
        ...a, clave: `anexo-${indice}`, tipo: 'anexo', indice,
        documentos: [...new Set([a.documento_id, ...(a.documentos || []).map((documento) => documento.id)].filter(Boolean))].map((id) => doc(id) || (a.documentos || []).find((documento) => Number(documento.id) === Number(id))).filter(Boolean),
        esDocumento: a.entrada === 'documento' || !a.sku,
        pendiente: !a.respondida_at,
        retirada: decisiones.find(d => d.accion === 'sustituir' && Number(d.revision_id) === Number(a.sustituye_revision_id)),
    }));
    const retiros = (solicitud.retiro_piezas || []).map((r, indice) => ({
        ...r, clave: `retiro-${indice}`, tipo: 'retiro', indice, esDocumento: r.entrada === 'documento',
        documentos: (r.documentos_piezas || []).map(d => doc(d.id) || (d.url ? d : null)).filter(Boolean),
        descripcion: (r.piezas_solicitadas || r.piezas || []).map(p => `${p.descripcion}${p.sku ? ` · ${p.sku}` : ''}`).join(', '),
        pendiente: (r.estado || 'pendiente') === 'pendiente' || Boolean(corte && r.solicitado_at >= corte),
    }));
    const sueltas = decisiones.filter(d => !anexos.some(a => Number(a.sustituye_revision_id) === Number(d.revision_id))
        && !retiros.some(r => (r.piezas_solicitadas || r.piezas || []).some(p => Number(p.revision_id) === Number(d.revision_id))))
        .map(d => ({ ...d, clave: `decision-${d.revision_id}`, tipo: d.accion === 'confirmar' ? 'confirmacion' : 'retiro', documentos: [],
            pendiente: Boolean(corte && d.fecha >= corte) }));
    return [...anexos, ...retiros, ...sueltas].sort((a, b) => Number(Boolean(b.esDocumento)) - Number(Boolean(a.esDocumento)));
}
