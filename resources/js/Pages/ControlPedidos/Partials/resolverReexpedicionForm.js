/**
 * Reexpedición: el cargo lo define la zona seleccionada (costo_adicional configurable en Admin).
 * El catálogo CP+paquetería solo sugiere la zona Con/Sin.
 *
 * @returns {{ matchKey: string|null, zonaIdSugerida: string|number|'', costoAplicado: number }}
 */
export function resolverReexpedicionForm({
    codigoPostal,
    paqueteriaId,
    reexpediciones = [],
    zonas = [],
    zonaIdSeleccionada = '',
}) {
    const cp = String(codigoPostal || '').trim();
    const paq = paqueteriaId == null || paqueteriaId === '' ? null : String(paqueteriaId);
    const match = cp && paq
        ? reexpediciones.find((r) => String(r.codigo_postal).trim() === cp && String(r.paqueteria_id) === paq)
        : null;

    const zonaCon = zonas.find((z) => /con reexpedici[oó]n/i.test(z.nombre || ''));
    const zonaSin = zonas.find((z) => /sin reexpedici[oó]n/i.test(z.nombre || ''));
    const zonaIdSugerida = match ? (zonaCon?.id ?? '') : (zonaSin?.id ?? '');

    const zonaId = zonaIdSeleccionada !== '' && zonaIdSeleccionada != null
        ? zonaIdSeleccionada
        : zonaIdSugerida;
    const zona = zonas.find((z) => String(z.id) === String(zonaId));
    const costoAplicado = Math.max(0, Math.round(Number(zona?.costo_adicional || 0) * 100) / 100);

    return {
        matchKey: match ? `${match.codigo_postal}|${match.paqueteria_id}` : null,
        zonaIdSugerida,
        /** @deprecated usar zonaIdSugerida; se mantiene por compat de lecturas previas */
        zonaId: zonaIdSugerida,
        costoAplicado,
    };
}

/** Quita el adicional de un costo_envio histórico que lo traía mezclado. */
export function separarCostoEnvioDeReexpedicion(costoEnvioAlmacenado, costoAplicado) {
    const full = Number(costoEnvioAlmacenado);
    const rex = Math.max(0, Number(costoAplicado || 0));
    if (!Number.isFinite(full) || costoEnvioAlmacenado === '' || costoEnvioAlmacenado == null) {
        return { base: costoEnvioAlmacenado ?? '', reexpedicion: rex };
    }
    if (rex <= 0) {
        return { base: Math.round(full * 100) / 100, reexpedicion: 0 };
    }
    const base = Math.max(0, Math.round((full - rex) * 100) / 100);
    return { base, reexpedicion: rex };
}

/** Persiste flete + reexpedición en el único campo costo_envio del pedido. */
export function costoEnvioParaPersistir(base, reexpedicion) {
    return Math.round((Number(base || 0) + Number(reexpedicion || 0)) * 100) / 100;
}

/**
 * Flete que entra al seguro: total de compra + guía.
 * Si el formulario ya separó la reexpedición (envioEsBase), costoEnvio es la guía.
 * Restarla otra vez baja el seguro: en DHL, 2% de $150 = $3.
 */
export function fleteBaseParaSeguro(costoEnvio, reexpedicion, envioEsBase) {
    const envio = Number(costoEnvio || 0);
    if (!Number.isFinite(envio)) return 0;
    if (envioEsBase) return Math.max(0, Math.round(envio * 100) / 100);
    const rex = Math.max(0, Number(reexpedicion || 0));
    return Math.max(0, Math.round((envio - rex) * 100) / 100);
}

/** Cargo según zona (Admin → Zonas Pedido → costo_adicional). */
export function costoReexpedicionDeZona(zonas, zonaId) {
    if (zonaId === '' || zonaId == null) return 0;
    const zona = (zonas || []).find((z) => String(z.id) === String(zonaId));
    return Math.max(0, Math.round(Number(zona?.costo_adicional || 0) * 100) / 100);
}
