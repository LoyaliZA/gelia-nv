/** El PDF de remisión se adjunta junto con el número escrito en la ficha. */
export function prepararAdjuntoRemision(numeroEscrito, numeroGuardado) {
    const numero = String(numeroEscrito || '').trim();
    if (!numero) {
        return { listo: false, guardarAntes: false, numero: '' };
    }
    return {
        listo: true,
        guardarAntes: numero !== String(numeroGuardado || '').trim(),
        numero,
    };
}

// Los documentos desactivados se conservan para la bitácora del pedido.
export function remisionVigenteDe(pedido) {
    return (pedido?.documentos || []).find((documento) => documento.tipo === 'remision'
        && documento.activo !== false && documento.activo !== 0
        && documento.activo !== '0' && !documento.sustituido_at);
}
