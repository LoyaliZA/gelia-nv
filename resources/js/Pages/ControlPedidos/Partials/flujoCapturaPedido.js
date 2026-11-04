const CODIGOS_TIENDA = ['RECOGE_TIENDA', 'RECOGE_TIENDA_TRANSFERENCIA', 'RECOGE_TIENDA_CREDITO'];

/** Las marcas de captura distinguen el flujo nuevo de los pedidos del piloto. */
export function esCapturaTienda(pedido) {
    const solicitud = pedido?.solicitud_preparacion;
    if (!solicitud || typeof solicitud !== 'object') return false;
    if (['municipal', 'nacional'].includes(solicitud.modalidad_entrega)) return false;
    if (solicitud.modalidad_entrega === 'tienda') return true;
    const marca = Object.hasOwn(solicitud, 'atencion_tienda') || Object.hasOwn(solicitud, 'piezas_tienda');
    return marca && (!solicitud.codigo_modalidad || CODIGOS_TIENDA.includes(solicitud.codigo_modalidad));
}

export function usaRegistroActual(pedido) {
    if (!pedido) return true;
    const solicitud = pedido.solicitud_preparacion;
    if (esCapturaTienda(pedido)) return true;
    // El alta nueva permite guardar antes de elegir la modalidad.
    if (solicitud && Object.hasOwn(solicitud, 'modalidad_entrega')) {
        return !solicitud.modalidad_entrega;
    }
    // Borradores sin tipo ni operación iniciada pueden continuar la captura actual.
    // No inferir el flujo por fecha, cliente ni por usa_preparacion_tienda.
    return !solicitud
        && pedido.estatus?.fase_ciclo === 'BORRADOR'
        && !pedido.origen_id && !pedido.origen
        && !pedido.pesaje_solicitado_at && !pedido.pesaje_respondido_at
        && !pedido.pago_validado_at && !pedido.numero_remision
        && !pedido.tarea_preparacion && !pedido.instruccion_entrega_vigente
        && !pedido.resguardo_apartado_at
        && !(pedido.pagos_exhibicion || []).length
        && !(pedido.documentos || []).some((doc) => doc.tipo === 'remision');
}
