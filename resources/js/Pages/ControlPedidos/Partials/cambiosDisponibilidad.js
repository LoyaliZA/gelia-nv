/** Conserva los índices guardados: dos solicitudes del mismo SKU son cambios distintos. */
function documentoDe(documentos, id) {
    return documentos.find((d) => Number(d.id) === Number(id));
}

function idsDocumentosAnexo(anexo) {
    const soporte = new Set((anexo.documentos_soporte || []).map((documento) => Number(documento.id || documento)).filter(Boolean));
    return [...new Set([anexo.documento_id, ...(anexo.documentos || []).map((documento) => documento.id)].filter((id) => id && !soporte.has(Number(id))))];
}

export function unidadRevisionId(tipo, linea = {}, clase = 'sku', documentoId = null, pieza = {}, indice = null) {
    const ancla = linea.linea_id || linea.solicitud_id || '';
    if (documentoId) return ancla ? `${tipo}:${ancla}:doc:${documentoId}` : `${tipo}:doc:${documentoId}`;
    const revision = pieza.revision_id || linea.sustituye_revision_id;
    if (revision) return ancla ? `${tipo}:${ancla}:rev:${revision}` : `${tipo}:rev:${revision}`;
    const sku = pieza.sku || linea.sku || '';
    if (ancla) return `${tipo}:${ancla}:sku:${sku}`;
    return `${tipo}:legacy:${indice ?? 0}:${sku}`;
}

/** Identidad canónica del servidor: si la línea ya trae sus unidades, se reutiliza su unidad_id. */
function idCanonico(linea, documentoId, calculado) {
    const unidades = (linea.unidades || []).filter((unidad) => unidad?.unidad_id);
    const porDocumento = documentoId ? unidades.find((unidad) => Number(unidad.documento_id) === Number(documentoId)) : null;
    if (porDocumento) return porDocumento.unidad_id;
    if (!documentoId && unidades.length === 1) return unidades[0].unidad_id;
    return calculado;
}

function persistidaDe(linea, unidadId) {
    return (linea.unidades || []).find((unidad) => unidad?.unidad_id === unidadId);
}

/** Reconciliación de una identidad provisional con la misma línea/documento ya materializados. */
export function equivalenciasUnidades(anteriores, actuales) {
    const equivalencias = new Map();
    for (const anterior of anteriores) {
        if (anteriores.filter(c => c.clave === anterior.clave).length !== 1) continue;
        if (actuales.some(c => c.clave === anterior.clave)) continue;
        const candidatas = actuales.filter(c => c.tipo === anterior.tipo
            && Number(c.documento_id || 0) === Number(anterior.documento_id || 0)
            && (anterior.linea_id && c.linea_id ? anterior.linea_id === c.linea_id
                : c.indice === anterior.indice && (!anterior.solicitud_id || anterior.solicitud_id === c.solicitud_id))
            && (anterior.esDocumento || c.sku === anterior.sku));
        if (candidatas.length === 1) equivalencias.set(anterior.clave, candidatas[0]);
    }
    return equivalencias;
}

function anexoPendienteRevision(anexo) {
    if (!anexo || anexo.respondida_at || anexo.pendiente_revision === false) return false;
    return !['disponible', 'con_detalle', 'rechazado'].includes(anexo.estado);
}

export function anexosPendientesRevision(solicitud = {}) {
    return (solicitud.anexo_sin_remision || []).some(anexoPendienteRevision);
}

/** Revisiones guardadas por unidad en un anexo aún sin responder: avance del ciclo en curso, no de la respuesta enviada. */
export function revisionesDelCicloEnCurso(solicitud = {}) {
    const ids = new Set();
    for (const anexo of solicitud.anexo_sin_remision || []) {
        if (!anexoPendienteRevision(anexo)) continue;
        for (const unidad of anexo.unidades || []) {
            for (const id of unidad?.revision_ids || []) ids.add(Number(id));
        }
    }
    return ids;
}

export function revisionesNoEnviadasTienda(solicitud = {}) {
    const ids = new Set();
    if (solicitud.modalidad_entrega !== 'tienda' || solicitud.area_revision !== 'preparacion_tienda') return ids;
    for (const anexo of solicitud.anexo_sin_remision || []) {
        for (const unidad of anexo.unidades || []) {
            const vigentes = unidad.resultado === 'incidencia' ? (unidad.revision_ids || []).map(Number) : [];
            for (const [id, origen] of Object.entries(solicitud.revision_origenes || {})) {
                if (origen === unidad.unidad_id && (anexoPendienteRevision(anexo) || !vigentes.includes(Number(id)))) ids.add(Number(id));
            }
            if (anexoPendienteRevision(anexo)) for (const id of unidad.revision_ids || []) ids.add(Number(id));
        }
    }
    return ids;
}

/** El ciclo vigente es lo pedido después de la última respuesta; lo anterior queda en historial. */
export function cambioDelCicloActual(cambio, solicitud = {}) {
    const corte = solicitud.revision_disponibilidad?.respondida_at;
    if (!corte) return true;
    const fecha = cambio.solicitado_at || cambio.fecha || null;
    if (cambio.tipo === 'retiro' || cambio.tipo === 'confirmacion') {
        return !fecha || fecha >= corte;
    }
    if (cambio.pendiente || !cambio.respondida_at) return true;
    return Boolean(fecha && fecha >= corte);
}

export function esRespuestaAlmacen(cambio) {
    if (cambio.tipo === 'confirmacion') return true;
    if (cambio.tipo === 'retiro') return false;
    return Boolean(!cambio.avance_pendiente && !cambio.pendiente && (cambio.resultado || cambio.respondida_at));
}

/** Ventas ve respuestas abiertas y solicitudes contraídas; CEDIS/Tienda ve el ciclo pedido abierto. */
export function particionarCambiosConsulta(cambios, solicitud = {}, vista = 'ventas') {
    if (vista === 'revision') {
        const actuales = cambios.filter((cambio) => cambioDelCicloActual(cambio, solicitud));
        return { actuales, anteriores: cambios.filter((cambio) => !cambioDelCicloActual(cambio, solicitud)) };
    }
    const actuales = cambios.filter(esRespuestaAlmacen);
    return { actuales, anteriores: cambios.filter((cambio) => !esRespuestaAlmacen(cambio)) };
}

function ficha(linea, extra, tienda = false) {
    const guardada = persistidaDe(linea, extra.unidad_id);
    const retiro = extra.tipo === 'retiro';
    // El envío pertenece al anexo original; la fecha de una unidad solo acredita su avance.
    const avancePendiente = tienda && anexoPendienteRevision(linea);
    const atendida = retiro
        || linea.pendiente_revision === false
        || ['disponible', 'con_detalle', 'rechazado'].includes(linea.estado)
        || (Boolean(linea.respondida_at) && linea.pendiente_revision !== true && !(linea.unidades || []).some((unidad) => unidad?.unidad_id && !unidad.resultado));
    const resultado = guardada?.resultado || extra.resultado || (atendida && !guardada ? (linea.estado === 'con_detalle' || linea.respuesta_cambio === 'incidencia' ? 'incidencia' : 'correcto') : null);
    return {
        ...linea,
        ...extra,
        resultado,
        persistida: Boolean(guardada?.resultado) || (atendida && !guardada),
        pendiente: retiro ? false : !resultado,
        respuesta_cambio: resultado || linea.respuesta_cambio,
        avance_pendiente: avancePendiente,
        guardada_at: guardada?.respondida_at,
        guardada_por: guardada?.respondida_por,
        respondida_at: tienda ? linea.respondida_at || (!avancePendiente ? guardada?.respondida_at : null) : guardada?.respondida_at || (resultado ? linea.respondida_at : null),
        respondida_por: tienda ? linea.respondida_por || (!avancePendiente ? guardada?.respondida_por : null) : guardada?.respondida_por,
    };
}

export function cambiosDisponibilidad(solicitud = {}, documentos = []) {
    const tienda = solicitud.modalidad_entrega === 'tienda' && solicitud.area_revision === 'preparacion_tienda';
    const decisiones = Object.values(solicitud.acciones_disponibilidad || {});
    const corte = solicitud.revision_disponibilidad?.respondida_at;
    const anexos = (solicitud.anexo_sin_remision || []).flatMap((a, indice) => {
        const ids = idsDocumentosAnexo(a);
        const docs = ids.map((id) => documentoDe(documentos, id) || (a.documentos || []).find((documento) => Number(documento.id) === Number(id))).filter(Boolean);
        const retirada = decisiones.find(d => d.accion === 'sustituir' && Number(d.revision_id) === Number(a.sustituye_revision_id));
        if (docs.length) {
            return docs.map((documento) => {
                const unidad_id = idCanonico(a, documento.id, unidadRevisionId('anexo', a, 'captura', documento.id, {}, indice));
                return ficha(a, {
                    clave: unidad_id, unidad_id, tipo: 'anexo', clase: 'captura', indice,
                    documento_id: documento.id, documentos: [documento], esDocumento: true, retirada,
                    sku: a.sku, descripcion: a.descripcion, cantidad: a.cantidad,
                }, tienda);
            });
        }
        const esDocumento = a.entrada === 'documento';
        const unidad_id = idCanonico(a, null, unidadRevisionId('anexo', a, esDocumento ? 'captura' : 'sku', null, {}, indice));
        return [ficha(a, {
            clave: unidad_id, unidad_id, tipo: 'anexo', clase: esDocumento ? 'captura' : 'sku', indice,
            documentos: [], esDocumento, retirada,
        }, tienda)];
    });
    const retiros = (solicitud.retiro_piezas || []).flatMap((r, indice) => {
        const docs = (r.documentos_piezas || []).map(d => documentoDe(documentos, d.id) || (d.url ? d : null)).filter(Boolean);
        const peticion = (r.documentos_peticion || []).map(d => documentoDe(documentos, d.id) || (d.url ? d : null)).filter(Boolean);
        const descripcion = (r.piezas_solicitadas || r.piezas || []).map(p => `${p.descripcion}${p.sku ? ` · ${p.sku}` : ''}`).join(', ');
        if (docs.length) {
            return docs.map((documento) => {
                const unidad_id = idCanonico(r, documento.id, unidadRevisionId('retiro', r, 'captura', documento.id, {}, indice));
                return ficha(r, {
                    clave: unidad_id, unidad_id, tipo: 'retiro', clase: 'captura', indice,
                    documento_id: documento.id, documentos: [documento], documentosPeticion: peticion,
                    esDocumento: true, descripcion,
                });
            });
        }
        const piezas = r.piezas_solicitadas || r.piezas || [];
        if (piezas.length) {
            return piezas.map((pieza) => {
                const unidad_id = unidadRevisionId('retiro', r, 'sku', null, pieza, indice);
                return ficha(r, {
                    clave: unidad_id, unidad_id, tipo: 'retiro', clase: 'sku', indice,
                    documentos: [], documentosPeticion: peticion, esDocumento: false,
                    sku: pieza.sku, descripcion: pieza.descripcion, cantidad: pieza.cantidad,
                });
            });
        }
        const unidad_id = unidadRevisionId('retiro', r, 'sku');
        return [ficha(r, {
            clave: unidad_id, unidad_id, tipo: 'retiro', clase: 'sku', indice,
            documentos: [], documentosPeticion: peticion, esDocumento: false, descripcion,
        })];
    });
    const sueltas = decisiones.filter(d => !anexos.some(a => Number(a.sustituye_revision_id) === Number(d.revision_id))
        && !retiros.some(r => (r.piezas_solicitadas || r.piezas || []).some(p => Number(p.revision_id) === Number(d.revision_id))))
        .map(d => ({ ...d, clave: `decision-${d.revision_id}`, tipo: d.accion === 'confirmar' ? 'confirmacion' : 'retiro', clase: 'sku', documentos: [],
            pendiente: false }));  // Decisión de Ventas ya registrada: se informa, no se revisa.
    return [...anexos, ...retiros, ...sueltas].sort((a, b) => Number(Boolean(b.esDocumento)) - Number(Boolean(a.esDocumento)));
}
