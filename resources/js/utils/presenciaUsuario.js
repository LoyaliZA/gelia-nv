const ZONA = 'America/Mexico_City';

function mismoDia(fecha, ahora) {
    const formato = new Intl.DateTimeFormat('en-CA', {
        timeZone: ZONA,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
    return formato.format(fecha) === formato.format(ahora);
}

export function textoVistoPorUltimaVez(iso, ahora = new Date()) {
    if (!iso) return 'Visto por última vez';
    const fecha = new Date(iso);
    if (Number.isNaN(fecha.getTime())) return 'Visto por última vez';

    const diffMin = Math.round((ahora.getTime() - fecha.getTime()) / 60000);
    if (diffMin < 1) return 'Visto por última vez hace un momento';
    if (diffMin < 60) return `Visto por última vez hace ${diffMin} min`;

    const hora = new Intl.DateTimeFormat('es-MX', {
        timeZone: ZONA,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(fecha);

    if (mismoDia(fecha, ahora)) {
        return `Visto por última vez a las ${hora}`;
    }

    const dia = new Intl.DateTimeFormat('es-MX', {
        timeZone: ZONA,
        day: 'numeric',
        month: 'short',
    }).format(fecha);

    return `Visto por última vez el ${dia} a las ${hora}`;
}

export function textoPresencia(presencia, ahora = new Date()) {
    if (!presencia) return null;
    if (presencia.en_linea === false) {
        return textoVistoPorUltimaVez(presencia.ultima_actividad_at, ahora);
    }
    const base = presencia.etiqueta || presencia.estado || '';
    if (!base) return null;
    if (presencia.mensaje?.trim()) {
        return `${base} · ${presencia.mensaje.trim()}`;
    }
    return base;
}

export function clasePresencia(presencia) {
    if (presencia?.en_linea === false) return 'gelia-presencia--ausente';
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
