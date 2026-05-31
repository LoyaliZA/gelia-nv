const ETIQUETAS_TIPO = {
    imagen: '📷 Imagen',
    video: '🎬 Video',
    audio: '🎤 Audio',
    archivo: '📎 Archivo',
    texto: '',
};

export function fragmentoRespuesta(mensaje) {
    if (!mensaje) return '';

    if (mensaje.preview) return mensaje.preview;

    const tipo = mensaje.tipo || 'texto';
    if (tipo === 'texto' && mensaje.contenido) {
        const t = mensaje.contenido.trim();
        return t.length > 120 ? `${t.slice(0, 120)}…` : t;
    }

    const nombreAdjunto = mensaje.nombre_adjunto || mensaje.adjuntos?.[0]?.nombre_original;
    if (nombreAdjunto) {
        return mensaje.contenido
            ? `${nombreAdjunto} — ${mensaje.contenido.trim().slice(0, 80)}`
            : nombreAdjunto;
    }

    if (mensaje.contenido) {
        const t = mensaje.contenido.trim();
        return t.length > 100 ? `${t.slice(0, 100)}…` : t;
    }

    return ETIQUETAS_TIPO[tipo] || `[${tipo}]`;
}

export function nombreRemitenteRespuesta(mensaje) {
    return mensaje?.user?.name || mensaje?.user_name || 'Usuario';
}
