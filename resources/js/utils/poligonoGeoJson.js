/**
 * Convierte paths { lat, lng }[] al anillo GeoJSON [lng, lat][] (cerrado).
 */
export function rutasGoogleAAnilloGeoJson(puntos) {
    if (!Array.isArray(puntos) || puntos.length < 3) {
        return null;
    }

    const ring = puntos.map((p) => [Number(p.lng), Number(p.lat)]);

    const [primeroLng, primeroLat] = ring[0];
    const [ultimoLng, ultimoLat] = ring[ring.length - 1];

    if (primeroLng !== ultimoLng || primeroLat !== ultimoLat) {
        ring.push([primeroLng, primeroLat]);
    }

    return ring;
}

export function rutasGoogleAGeoJson(puntos) {
    const ring = rutasGoogleAAnilloGeoJson(puntos);
    if (!ring) return null;

    return {
        type: 'Polygon',
        coordinates: [ring],
    };
}

export function extraerPathsDesdeMvcArray(mvcPath) {
    const coords = [];
    for (let i = 0; i < mvcPath.getLength(); i++) {
        const ll = mvcPath.getAt(i);
        coords.push({ lat: ll.lat(), lng: ll.lng() });
    }
    return coords;
}

export function extraerPathsDesdePolygon(polygon) {
    return extraerPathsDesdeMvcArray(polygon.getPath());
}
