/** Catálogo local (respaldo si Ziggy/API no están disponibles). */
export const PRESENCIA_ESTADOS = [
    { slug: 'disponible', etiqueta: 'Disponible', emoji: '🟢', color: '#22c55e' },
    { slug: 'en_junta', etiqueta: 'En junta', emoji: '📅', color: '#8b5cf6' },
    { slug: 'comiendo', etiqueta: 'Comiendo', emoji: '🍽️', color: '#f59e0b' },
    { slug: 'en_ruta_venta', etiqueta: 'En ruta de venta', emoji: '🚗', color: '#3b82f6' },
    { slug: 'ocupado', etiqueta: 'Ocupado', emoji: '🔴', color: '#ef4444' },
    { slug: 'ausente', etiqueta: 'Ausente', emoji: '⏸️', color: '#94a3b8' },
];

function mapEntradasCatalogo(entries) {
    return entries.map(([slug, meta]) => ({
        slug,
        etiqueta: meta?.etiqueta ?? slug,
        emoji: meta?.emoji ?? '•',
        color: meta?.color,
    }));
}

/** Normaliza respuesta API, props Inertia u objeto PHP serializado. */
export function normalizarCatalogoPresencia(fuente) {
    if (!fuente) {
        return [...PRESENCIA_ESTADOS];
    }

    if (Array.isArray(fuente)) {
        const items = fuente
            .filter((item) => item && (item.slug || item.estado))
            .map((item) => ({
                slug: item.slug ?? item.estado,
                etiqueta: item.etiqueta ?? item.slug ?? item.estado,
                emoji: item.emoji ?? '•',
                color: item.color,
            }));
        return items.length > 0 ? items : [...PRESENCIA_ESTADOS];
    }

    if (typeof fuente === 'object') {
        const entries = Object.entries(fuente);
        if (entries.length === 0) {
            return [...PRESENCIA_ESTADOS];
        }
        return mapEntradasCatalogo(entries);
    }

    return [...PRESENCIA_ESTADOS];
}

export function catalogoDesdeAuth(auth) {
    return normalizarCatalogoPresencia(auth?.presencia_catalogo);
}

export function presenciaDesdeSlug(slug, mensaje = null) {
    const item = PRESENCIA_ESTADOS.find((e) => e.slug === slug);
    if (!item) {
        return { estado: slug, etiqueta: slug, emoji: '•', modo: 'manual' };
    }
    return {
        estado: slug,
        etiqueta: item.etiqueta,
        emoji: item.emoji,
        color: item.color,
        mensaje,
        modo: 'manual',
        automatizar: true,
    };
}
