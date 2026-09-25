export const ESTADOS_FISICOS = ['bueno', 'regular', 'malo', 'danado', 'sin_existencia'];

export function requiereEvidencia(estado) {
    return estado === 'malo' || estado === 'danado';
}

export function requiereComentario(estado) {
    return requiereEvidencia(estado) || estado === 'sin_existencia';
}

export function revisionDesdeLinea(linea) {
    return {
        solicitud_traspaso_producto_id: linea.id,
        producto_id: linea.producto_id,
        sku: linea.sku,
        descripcion_producto: `${linea.sku} — ${linea.descripcion}`,
        estado_fisico: 'bueno',
        comentario: '',
        unica_pieza: false,
        mejor_ejemplar: false,
        evidencias: [],
        previews: [],
        expandido: false,
    };
}

/** @param {{ productos?: array }} traspaso */
export function inicializarRevisionesDesdeTraspaso(traspaso) {
    const lineas = traspaso?.productos || [];
    const revisiones = [];
    lineas.forEach((linea) => {
        const piezas = Math.max(1, Number(linea.piezas) || 1);
        for (let i = 0; i < piezas; i += 1) {
            revisiones.push(revisionDesdeLinea(linea));
        }
    });
    return revisiones;
}

export function conteoPorLinea(revisiones) {
    const map = {};
    (revisiones || []).forEach((r) => {
        const id = r.solicitud_traspaso_producto_id;
        if (id) map[id] = (map[id] || 0) + 1;
    });
    return map;
}

export function piezasPermitidasPorLinea(traspaso) {
    const map = {};
    (traspaso?.productos || []).forEach((l) => {
        map[l.id] = Math.max(1, Number(l.piezas) || 1);
    });
    return map;
}

export function productoPermitidoEnSolicitud(traspaso, producto) {
    if (!producto?.id) return false;
    return (traspaso?.productos || []).some((l) => String(l.producto_id) === String(producto.id));
}

export function puedeAgregarPieza(traspaso, revisiones, productoId) {
    const linea = (traspaso?.productos || []).find((l) => String(l.producto_id) === String(productoId));
    if (!linea) return false;
    const max = Math.max(1, Number(linea.piezas) || 1);
    const actuales = (revisiones || []).filter(
        (r) => String(r.solicitud_traspaso_producto_id) === String(linea.id)
    ).length;
    return actuales < max;
}

export function instanciasRevision(revisiones) {
    const porLinea = {};
    return (revisiones || []).map((r) => {
        const key = r.solicitud_traspaso_producto_id || r.sku;
        porLinea[key] = (porLinea[key] || 0) + 1;
        const total = (revisiones || []).filter(
            (x) => (x.solicitud_traspaso_producto_id || x.sku) === key
        ).length;
        return total > 1 ? `${porLinea[key]}/${total}` : null;
    });
}
