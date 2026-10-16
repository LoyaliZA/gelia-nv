const clave = (p) => (p?.revision_id ? `id:${p.revision_id}` : `nuevo:${p?.client_uuid || ''}`);

const pieza = (p) => ({
    clave: clave(p),
    revision_id: p?.revision_id || null,
    etiqueta: p?.label || p?.descripcion_producto || p?.sku || 'Pieza',
    producto_id: p?.producto_id || null,
    sku: String(p?.sku || '').trim(),
    estado: p?.estado || p?.estado_fisico || 'bueno',
    solicitada: p?.cantidad_solicitada == null || p?.cantidad_solicitada === '' ? null : Number(p.cantidad_solicitada),
    encontrada: p?.cantidad_encontrada == null || p?.cantidad_encontrada === '' ? null : Number(p.cantidad_encontrada),
});

const mismoProducto = (a, b) => {
    if (a.producto_id && b.producto_id) return String(a.producto_id) === String(b.producto_id);
    return a.sku !== '' && a.sku === b.sku;
};

const cantidadDistinta = (a, b) => a.solicitada !== b.solicitada || a.encontrada !== b.encontrada;

/**
 * Compara la revisión anterior con la que se va a guardar.
 * La identidad es el renglón guardado; una observación sola no anuncia cambio de piezas.
 */
export function resumenActualizacionConsulta(antes = [], despues = []) {
    const previos = (antes || []).map(pieza).filter((p) => p.clave !== 'nuevo:');
    const siguientes = (despues || []).map(pieza);
    const porClave = new Map(previos.map((p) => [p.clave, p]));
    const usadas = new Set();
    const agregadas = [];
    const sustituidas = [];
    const cantidades = [];
    const condiciones = [];

    siguientes.forEach((p) => {
        const previo = porClave.get(p.clave);
        if (!previo) {
            agregadas.push(p);
            return;
        }
        usadas.add(p.clave);
        if (!mismoProducto(previo, p)) {
            sustituidas.push({ antes: previo, despues: p });
            return;
        }
        if (cantidadDistinta(previo, p)) cantidades.push({ antes: previo, despues: p });
        if (previo.estado !== p.estado) condiciones.push({ antes: previo, despues: p });
    });

    const retiradas = previos.filter((p) => p.revision_id && !usadas.has(p.clave));
    const cambioPiezas = agregadas.length + retiradas.length + sustituidas.length + cantidades.length + condiciones.length > 0;

    return {
        agregadas,
        retiradas,
        sustituidas,
        cantidades,
        condiciones,
        cambioPiezas,
        sinCambioPiezas: !cambioPiezas,
    };
}
