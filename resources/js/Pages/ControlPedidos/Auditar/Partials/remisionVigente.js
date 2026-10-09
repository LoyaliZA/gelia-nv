// Los documentos desactivados se conservan para la bitácora del pedido.
export function remisionVigenteDe(pedido) {
    return (pedido?.documentos || []).find((documento) => documento.tipo === 'remision'
        && documento.activo !== false && documento.activo !== 0
        && documento.activo !== '0' && !documento.sustituido_at);
}
