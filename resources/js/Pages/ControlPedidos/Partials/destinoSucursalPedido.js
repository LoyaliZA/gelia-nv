/**
 * Visibilidad y payload de sucursal destino — consume flags/catálogos del backend (1D).
 */

export function modalidadesRequierenDestino(catalogos = {}) {
    return catalogos?.destino_sucursal_config?.modalidades_requieren_destino || [];
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
