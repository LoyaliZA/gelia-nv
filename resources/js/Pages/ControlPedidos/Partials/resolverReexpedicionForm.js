/**
 * Localiza match de reexpedición (CP + paquetería) y sugiere zona.
 * El adicional NO se mezcla en costo_envio: va aparte en cobro/UI.
 *
 * @returns {{ matchKey: string|null, zonaId: string|number|'', costoAplicado: number }}
 */
export function resolverReexpedicionForm({
    codigoPostal,
    paqueteriaId,
    reexpediciones = [],
    zonas = [],
}) {
    const cp = String(codigoPostal || '').trim();
    const paq = paqueteriaId == null || paqueteriaId === '' ? null : String(paqueteriaId);
    const match = cp && paq
        ? reexpediciones.find((r) => String(r.codigo_postal).trim() === cp && String(r.paqueteria_id) === paq)
        : null;

    const zonaCon = zonas.find((z) => /con reexpedici[oó]n/i.test(z.nombre || ''));
    const zonaSin = zonas.find((z) => /sin reexpedici[oó]n/i.test(z.nombre || ''));
    const nuevoAdicional = match ? Math.max(0, Number(match.costo_adicional || 0)) : 0;

    return {
        matchKey: match ? `${match.codigo_postal}|${match.paqueteria_id}` : null,
        zonaId: match ? (zonaCon?.id ?? '') : (zonaSin?.id ?? ''),
        costoAplicado: Math.round(nuevoAdicional * 100) / 100,
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
