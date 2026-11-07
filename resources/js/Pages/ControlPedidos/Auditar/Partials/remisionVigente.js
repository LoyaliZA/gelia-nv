import { fechaCalendario } from '../../../../utils/fechaCalendario';

export function fechaInicialRemision(pedido) {
    return pedido?.solicitud_preparacion?.remision_tienda?.emitida_at?.slice(0, 10)
        || pedido?.fecha?.slice(0, 10)
        || fechaCalendario(pedido?.created_at);
}

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
