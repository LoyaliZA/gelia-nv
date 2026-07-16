/**
 * Localiza match de reexpedición y calcula zona + costo sin doble suma.
 * @returns {{ matchKey: string|null, zonaId: string|number|'', costoEnvio: number, costoAplicado: number }}
 */
export function resolverReexpedicionForm({
    codigoPostal,
    paqueteriaId,
    reexpediciones = [],
    zonas = [],
    costoEnvioActual,
    costoAplicadoPrevio = 0,
}) {
    const cp = String(codigoPostal || '').trim();
    const paq = paqueteriaId == null || paqueteriaId === '' ? null : String(paqueteriaId);
    const match = cp && paq
        ? reexpediciones.find((r) => String(r.codigo_postal).trim() === cp && String(r.paqueteria_id) === paq)
        : null;

    const zonaCon = zonas.find((z) => /con reexpedici[oó]n/i.test(z.nombre || ''));
    const zonaSin = zonas.find((z) => /sin reexpedici[oó]n/i.test(z.nombre || ''));

    const envio = Number(costoEnvioActual || 0);
    const previo = Number(costoAplicadoPrevio || 0);
    const base = Math.max(0, envio - previo);
    const nuevoAdicional = match ? Math.max(0, Number(match.costo_adicional || 0)) : 0;

    return {
        matchKey: match ? `${match.codigo_postal}|${match.paqueteria_id}` : null,
        zonaId: match ? (zonaCon?.id ?? '') : (zonaSin?.id ?? ''),
        costoEnvio: Math.round((base + nuevoAdicional) * 100) / 100,
        costoAplicado: Math.round(nuevoAdicional * 100) / 100,
    };
}
