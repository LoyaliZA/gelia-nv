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
