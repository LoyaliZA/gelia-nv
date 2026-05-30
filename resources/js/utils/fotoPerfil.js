/**
 * Normaliza rutas de foto de perfil almacenadas en BD (ej. perfiles/uuid.jpg)
 * a URL pública servida por Laravel (/storage/...).
 */
export function resolveFotoPerfilUrl(ruta) {
    if (!ruta || typeof ruta !== 'string') return null;

    const trimmed = ruta.trim();
    if (!trimmed) return null;

    if (
        trimmed.startsWith('http://')
        || trimmed.startsWith('https://')
        || trimmed.startsWith('/storage/')
        || trimmed.startsWith('data:')
        || trimmed.startsWith('blob:')
    ) {
        return trimmed;
    }

    if (trimmed.startsWith('/')) {
        return trimmed;
    }

    return `/storage/${trimmed.replace(/^\/+/, '')}`;
}
