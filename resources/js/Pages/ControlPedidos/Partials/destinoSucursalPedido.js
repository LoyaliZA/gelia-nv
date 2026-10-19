/**
 * Visibilidad y payload de sucursal destino — consume flags/catálogos del backend (1D).
 */

export function modalidadesRequierenDestino(catalogos = {}) {
    return catalogos?.destino_sucursal_config?.modalidades_requieren_destino || [];
}

export function destinoSucursalVisible({
    requiereLogistica = false,
    codigoModalidad = '',
    modalidadesRequierenDestino = [],
    salidaEsPdv = false,
    tieneAlmacenSalida = false,
} = {}) {
    const obligatorio = requiereCapturaDestinoSucursal({
        requiereLogistica,
        codigoModalidad,
        modalidadesRequierenDestino,
    });
    const codigo = String(codigoModalidad || '').trim();
    const opcionalTraslado = !obligatorio
        && Boolean(requiereLogistica)
        && Boolean(tieneAlmacenSalida)
        && codigo === '';

    return { muestra: obligatorio || opcionalTraslado, obligatorio };
}

export function requiereCapturaDestinoSucursal({
    requiereLogistica = false,
    codigoModalidad = '',
    modalidadesRequierenDestino = [],
} = {}) {
    const codigo = String(codigoModalidad || '').trim();
    if (codigo) {
        return modalidadesRequierenDestino.includes(codigo);
    }
    return !requiereLogistica;
}

export function destinoSucursalParaPayload({
    muestra,
    sucursalDestinoId,
    esAutoguardado = false,
} = {}) {
    if (!muestra) {
        return esAutoguardado ? {} : { sucursal_destino_id: null };
    }
    const id = sucursalDestinoId === '' || sucursalDestinoId == null
        ? null
        : Number(sucursalDestinoId);
    return { sucursal_destino_id: Number.isFinite(id) ? id : null };
}

export function resolverCodigoModalidadEfectiva({
    codigoModalidadPreparacion = '',
    tareaPreparacion = null,
} = {}) {
    return tareaPreparacion?.modalidad?.codigo || codigoModalidadPreparacion || '';
}

/** Espejo de PedidoBma::esTrasladoInternoAPdv para captura sin ID. */
export function esTrasladoInternoPdv(pedido, almacen = pedido?.almacen, codigoModalidad) {
    const marca = pedido?.solicitud_preparacion?.traslado_interno_pdv
        ?? pedido?.solicitud_preparacion_json?.traslado_interno_pdv;
    if (marca === false) return false;
    const codigo = codigoModalidad ?? pedido?.tarea_preparacion?.modalidad?.codigo
        ?? pedido?.tarea_preparacion_vigente?.modalidad?.codigo
        ?? pedido?.solicitud_preparacion?.codigo_modalidad
        ?? pedido?.solicitud_preparacion_json?.codigo_modalidad;
    if (codigo && !['RECOGE_TIENDA', 'RECOGE_TIENDA_TRANSFERENCIA', 'RECOGE_TIENDA_CREDITO'].includes(codigo)) return false;
    if (!pedido?.sucursal_destino_id || !pedido?.almacen_id || !almacen) return false;
    const esPdv = String(almacen.tipo_almacen?.nombre || almacen.tipoAlmacen?.nombre || '').trim().toUpperCase() === 'PDV'
        || String(almacen.codigo || '').trim().toUpperCase() === 'PDV';
    return !esPdv || (almacen.sucursal_id != null && Number(almacen.sucursal_id) !== Number(pedido.sucursal_destino_id));
}

export function pedidoEsTrasladoInternoPdv(pedido) {
    return pedido?.es_traslado_interno_pdv ?? esTrasladoInternoPdv(pedido);
}

/**
 * Envío nacional desde CEDIS: la paquetería aparece con almacén de salida.
 * La sucursal destino no la condiciona. El traslado interno no usa paquetería.
 */
export function paqueteriaEnvioVisible({
    requiereLogistica = false,
    envioComercialSinModalidad = false,
    tieneAlmacenSalida = false,
} = {}) {
    if (!requiereLogistica) return false;
    if (!envioComercialSinModalidad) return true;
    return Boolean(tieneAlmacenSalida);
}
