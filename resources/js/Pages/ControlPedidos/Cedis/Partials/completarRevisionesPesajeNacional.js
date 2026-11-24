const skuDe = (valor) => String(valor || '').trim().toLowerCase();

export function piezasVigentesNacional(pedido = {}) {
    const solicitud = pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || {};
    if (solicitud.modalidad_entrega !== 'nacional' || solicitud.captura_nacional !== 'compartida') return [];
    return solicitud.piezas_nacionales_vigentes || solicitud.piezas_nacionales || [];
}

function revisionDePieza(pieza, extra = {}) {
    const solicitada = Number(pieza.cantidad || pieza.cantidad_solicitada || 1);
    return {
        client_uuid: pieza.id,
        producto_id: pieza.producto_id || null,
        sku: pieza.sku || '',
        descripcion_producto: pieza.descripcion || pieza.descripcion_producto || '',
        cantidad_solicitada: solicitada,
        cantidad_encontrada: extra.cantidad_encontrada ?? solicitada,
        estado_fisico: extra.estado_fisico || 'bueno',
        comentario: extra.comentario || '',
        unica_pieza: Boolean(extra.unica_pieza),
        mejor_ejemplar: Boolean(extra.mejor_ejemplar),
        evidencias: extra.evidencias || [],
        previews: extra.previews || [],
        piezaSolicitada: true,
    };
}

function revisionDeExcepcion(excepcion) {
    const faltante = excepcion.condicion === 'faltante';
    return {
        client_uuid: excepcion.client_uuid || excepcion.clave || null,
        producto_id: excepcion.producto_id || null,
        sku: excepcion.sku || '',
        descripcion_producto: excepcion.descripcion || '',
        cantidad_solicitada: Number(excepcion.cantidad) || 1,
        cantidad_encontrada: faltante ? 0 : 1,
        estado_fisico: faltante ? 'sin_existencia' : 'regular',
        comentario: faltante ? 'Sin existencias' : (excepcion.detalle || ''),
        unica_pieza: Boolean(excepcion.unica_pieza),
        mejor_ejemplar: Boolean(excepcion.mejor_ejemplar),
        evidencias: excepcion.archivos || excepcion.evidencias || [],
        previews: excepcion.previews || [],
        piezaSolicitada: false,
    };
}

/**
 * Completa las piezas vigentes de nacional 2.0: Todo OK confirma el conjunto;
 * las excepciones solo cubren afectadas y el resto queda Bueno.
 */
export function completarRevisionesPesajeNacional(solicitadas = [], excepciones = [], confirmacion = false, revisionesEnviadas = []) {
    const vigentes = Array.isArray(solicitadas) ? solicitadas : [];
    if (confirmacion) {
        return vigentes.map((pieza) => revisionDePieza(pieza));
    }
    if (!excepciones.length) {
        return revisionesEnviadas;
    }

    const cubetas = vigentes.map((pieza) => ({
        pieza,
        faltantes: 0,
        detalles: [],
    }));
    const extras = [];

    excepciones.forEach((excepcion) => {
        const sku = skuDe(excepcion.sku);
        let cubeta = null;
        if (excepcion.client_uuid) cubeta = cubetas.find((item) => item.pieza.id === excepcion.client_uuid);
        if (!cubeta && sku) {
            cubeta = cubetas.find((item) => {
                const capacidad = Number(item.pieza.cantidad || 1) - item.faltantes - item.detalles.length;
                return skuDe(item.pieza.sku) === sku && capacidad > 0;
            });
        }
        if (!cubeta) {
            extras.push(excepcion);
            return;
        }
        if (excepcion.condicion === 'faltante') cubeta.faltantes += 1;
        else cubeta.detalles.push(excepcion);
    });

    const resultado = cubetas.map(({ pieza, faltantes, detalles }) => {
        const solicitada = Number(pieza.cantidad || 1);
        const faltante = Math.min(faltantes, solicitada);
        const encontrada = solicitada - faltante;
        if (encontrada === 0) {
            return revisionDePieza(pieza, {
                cantidad_encontrada: 0,
                estado_fisico: 'sin_existencia',
                comentario: 'Sin existencias',
            });
        }
        if (detalles.length) {
            const primera = detalles[0];
            return revisionDePieza(pieza, {
                cantidad_encontrada: encontrada,
                estado_fisico: 'regular',
                comentario: detalles.map((item) => item.detalle).filter(Boolean).join(' · ') || primera.detalle || '',
                unica_pieza: detalles.some((item) => item.unica_pieza),
                mejor_ejemplar: detalles.some((item) => item.mejor_ejemplar),
                evidencias: detalles.flatMap((item) => item.archivos || item.evidencias || []),
                previews: detalles.flatMap((item) => item.previews || []),
            });
        }
        if (faltante > 0) {
            return revisionDePieza(pieza, {
                cantidad_encontrada: encontrada,
                comentario: `Sin existencias (${faltante} de ${solicitada})`,
            });
        }
        return revisionDePieza(pieza);
    });

    return [...resultado, ...extras.map(revisionDeExcepcion)];
}

export function excepcionesDesdeRevisiones(revisiones = []) {
    return revisiones
        .filter((revision) => revision.estado_fisico && revision.estado_fisico !== 'bueno')
        .map((revision, indice) => ({
            descripcion: String(revision.descripcion_producto || '').replace(/^[^—]+—\s*/, ''),
            sku: revision.sku || '',
            producto_id: revision.producto_id || null,
            clave: revision.client_uuid || `rev-${indice}`,
            client_uuid: revision.client_uuid || null,
            revision_id: revision.id || revision.revision_id || null,
            cantidad: 1,
            condicion: revision.estado_fisico === 'sin_existencia' ? 'faltante' : 'detalle',
            detalle: revision.estado_fisico === 'sin_existencia' ? 'Sin existencias' : (revision.comentario || ''),
            unica_pieza: Boolean(revision.unica_pieza),
            mejor_ejemplar: Boolean(revision.mejor_ejemplar),
            archivos: revision.evidencias || [],
            previews: revision.previews || [],
        }));
}
