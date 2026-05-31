export function textoPresencia(presencia) {
    if (!presencia) return null;
    const base = presencia.etiqueta || presencia.estado || '';
    if (!base) return null;
    if (presencia.mensaje?.trim()) {
        return `${base} · ${presencia.mensaje.trim()}`;
    }
    return base;
}

export function clasePresencia(presencia) {
    if (!presencia?.estado) return 'gelia-presencia--disponible';
    return `gelia-presencia--${presencia.estado}`;
}

export function actualizarPresenciaEnConversacion(conversacion, userId, presencia) {
    if (!conversacion || !userId || !presencia) return conversacion;

    const presenciaConId = { ...presencia, user_id: presencia.user_id ?? userId };
    const participantes = (conversacion.participantes || []).map((p) =>
        Number(p.id) === Number(userId) ? { ...p, presencia: presenciaConId } : p
    );

    const esDirecto = conversacion.tipo !== 'grupo';
    const otroEsContacto = participantes.some((p) => Number(p.id) === Number(userId));

    return {
        ...conversacion,
        participantes,
        presencia_otro: esDirecto && otroEsContacto ? presenciaConId : conversacion.presencia_otro,
    };
}
