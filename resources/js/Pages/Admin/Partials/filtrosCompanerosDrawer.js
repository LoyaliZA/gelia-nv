function coincideId(filtro, ids) {
    if (!filtro) return true;
    return ids.some((id) => id != null && id !== '' && String(id) === String(filtro));
}

export function areaPrincipalDe(persona) {
    if (persona?.area?.nombre) return persona.area;
    const id = persona?.area_id;
    if (id == null || id === '') return null;
    return (persona.areas || []).find((area) => String(area.id) === String(id)) || null;
}

export function catalogoNombrado(personas, extraer) {
    const mapa = new Map();
    (personas || []).forEach((persona) => {
        extraer(persona).forEach((item) => {
            if (item?.id == null || item.id === '') return;
            const nombre = String(item.nombre || '').trim();
            if (!nombre) return;
            mapa.set(String(item.id), nombre);
        });
    });

    return [...mapa.entries()]
        .map(([value, label]) => ({ value, label }))
        .sort((a, b) => a.label.localeCompare(b.label, 'es'));
}

export function filtrarCompaneros(personas, { departamento = '', sucursal = '', area = '' } = {}) {
    return (personas || []).filter((persona) => (
        coincideId(departamento, [
            persona.departamento_id,
            persona.departamento?.id,
            ...(persona.departamentos || []).map((item) => item.id),
        ])
        && coincideId(sucursal, (persona.sucursales || []).map((item) => item.id))
        && coincideId(area, [persona.area_id, persona.area?.id, areaPrincipalDe(persona)?.id])
    ));
}
