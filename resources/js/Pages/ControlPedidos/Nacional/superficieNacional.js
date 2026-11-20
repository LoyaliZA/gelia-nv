export function esNacional(pedido) {
    return (pedido?.solicitud_preparacion?.modalidad_entrega || pedido?.modalidad_entrega) === 'nacional';
}

export function cambioATiendaNacional(pedido) {
    const cambio = pedido?.cambio_a_tienda || pedido?.solicitud_preparacion?.cambio_a_tienda;

    return esNacional(pedido) && Boolean(cambio);
}

export function sucursalRecoleccionNacional(pedido, sucursales = []) {
    const cambio = pedido?.cambio_a_tienda || pedido?.solicitud_preparacion?.cambio_a_tienda;
    const id = Number(pedido?.sucursal_destino_id || cambio?.sucursal_destino_id || 0);
    const conocida = pedido?.sucursal_destino?.nombre
        || sucursales.find((item) => Number(item.id) === id)?.nombre;

    return conocida || (id ? `Sucursal ${id}` : '');
}

export function vinculoFisicoNacional(pedido) {
    const propio = pedido?.vinculo_resguardo || pedido?.solicitud_preparacion?.vinculo_resguardo;
    if (propio?.estado) {
        return { pedidoId: Number(pedido.vinculo_pedido_id || pedido.id), vinculo: propio };
    }
    for (const hijo of pedido?.complementos || []) {
        const marca = hijo?.solicitud_preparacion?.vinculo_resguardo;
        if (marca?.estado) {
            return { pedidoId: Number(hijo.id), vinculo: marca };
        }
    }

    return null;
}

export function integracionFisicaPendiente(pedido) {
    const dato = vinculoFisicoNacional(pedido);
    if (!dato) return false;

    return !(dato.vinculo.estado === 'recibido_cedis' && dato.vinculo.integrado === true);
}

export function piezasComplementoNacional(pedido) {
    const ids = new Set((pedido?.solicitud_preparacion?.complementos_nacionales || [])
        .map((item) => Number(item?.pedido_id))
        .filter((id) => id > 0));

    return (pedido?.complementos || []).filter((hijo) => (
        ids.has(Number(hijo.id)) || Boolean(hijo?.solicitud_preparacion?.complemento_nacional)
    ));
}
