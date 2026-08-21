/**
 * Elige dirección verificada para el pedido: selección actual → principal → primera.
 * @param {Array<{id: *, es_principal?: boolean}>} dirs
 * @param {{ direccionId?: * }} [opts]
 */
export function elegirDireccionParaPedido(dirs, { direccionId = null } = {}) {
    const lista = Array.isArray(dirs) ? dirs : [];
    if (!lista.length) return null;
    if (direccionId != null && direccionId !== '') {
        const sel = lista.find((d) => String(d.id) === String(direccionId));
        if (sel) return sel;
    }
    return lista.find((d) => d.es_principal) || lista[0] || null;
}

/** Misma regla que CrearSnapshotDireccionPedido::manualEstaCompleta (lado cliente). */
export function manualDireccionCompleta(campos) {
    if (!campos) return false;
    const nombre = String(campos.nombre_destinatario || '').trim();
    const estado = String(campos.estado || '').trim();
    if (!nombre || !estado) return false;
    if (campos.domicilio_irregular) {
        const refs = String(campos.referencias || '').trim();
        const muni = String(campos.municipio || '').trim();
        const ciudad = String(campos.ciudad || '').trim();
        return refs.length >= 10 && Boolean(muni || ciudad);
    }
    return Boolean(String(campos.calle || '').trim())
        && Boolean(String(campos.colonia || '').trim())
        && /^\d{5}$/.test(String(campos.codigo_postal || '').trim())
        && Boolean(String(campos.municipio || '').trim());
}

/** Texto corto de qué falta para guardar/enviar excepción o catálogo. */
export function faltantesManualDireccion(campos) {
    const c = campos || {};
    const out = [];
    if (!String(c.nombre_destinatario || '').trim()) out.push('nombre del destinatario');
    if (!String(c.estado || '').trim()) out.push('estado');
    if (c.domicilio_irregular) {
        if (String(c.referencias || '').trim().length < 10) out.push('referencias (mín. 10 caracteres)');
        if (!String(c.municipio || '').trim() && !String(c.ciudad || '').trim()) out.push('municipio o ciudad');
    } else {
        if (!String(c.calle || '').trim()) out.push('calle');
        if (!String(c.colonia || '').trim()) out.push('colonia');
        if (!/^\d{5}$/.test(String(c.codigo_postal || '').trim())) out.push('código postal (5 dígitos)');
        if (!String(c.municipio || '').trim()) out.push('municipio');
    }
    return out;
}
