/**
 * Código visual: 8699 (1.ª), 8699-1 (2.ª), 8699-2 (3.ª)…
 */
export function codigoDireccionCliente(numeroCliente, numeroDireccion) {
    const cliente = String(numeroCliente ?? '').trim();
    if (!cliente) return '';

    const n = Number(numeroDireccion);
    if (!Number.isFinite(n) || n <= 1) {
        return cliente;
    }

    return `${cliente}-${n - 1}`;
}

/**
 * Label completo para el selector de direcciones (código + domicilio).
 */
export function labelOpcionDireccion(numeroCliente, direccion = {}) {
    const codigo = codigoDireccionCliente(numeroCliente, direccion.numero_direccion);
    const recibe = direccion.nombre_destinatario
        ? `Recibe: ${direccion.nombre_destinatario}`
        : null;

    const lineaCalle = [
        direccion.calle,
        direccion.numero_exterior ? `Ext. ${direccion.numero_exterior}` : null,
        direccion.numero_interior ? `Int. ${direccion.numero_interior}` : null,
    ].filter(Boolean).join(' ');

    const lugar = [
        direccion.colonia ? `Col. ${direccion.colonia}` : null,
        direccion.municipio || direccion.ciudad || null,
        direccion.estado || null,
        direccion.codigo_postal ? `C.P. ${direccion.codigo_postal}` : null,
    ].filter(Boolean).join(', ');

    const domicilio = [lineaCalle, lugar].filter(Boolean).join(', ')
        || direccion.direccion_resumida
        || null;

    return [codigo, recibe, domicilio].filter(Boolean).join(' — ');
}
