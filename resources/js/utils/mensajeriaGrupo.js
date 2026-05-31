/** Colores distintivos para remitentes en chats grupales (estilo WhatsApp). */
const COLORES_REMITENTE = [
    '#e11d48',
    '#059669',
    '#d97706',
    '#7c3aed',
    '#0891b2',
    '#db2777',
    '#4f46e5',
    '#0d9488',
    '#ca8a04',
    '#9333ea',
];

export function colorRemitenteGrupo(userId) {
    const id = Number(userId);
    if (!id) return COLORES_REMITENTE[0];
    return COLORES_REMITENTE[Math.abs(id) % COLORES_REMITENTE.length];
}

export function nombreRemitenteGrupo(user) {
    if (!user) return 'Usuario';
    const nombre = user.name?.trim();
    if (nombre) return nombre;
    if (user.username) return user.username;
    return 'Usuario';
}

/** Nombres visibles repetidos entre participantes (p. ej. dos "Gerente"). */
export function nombresConDuplicado(participantes = []) {
    const conteo = new Map();
    for (const p of participantes) {
        const clave = nombreRemitenteGrupo(p).toLowerCase();
        conteo.set(clave, (conteo.get(clave) || 0) + 1);
    }
    return new Set(
        [...conteo.entries()].filter(([, n]) => n > 1).map(([nombre]) => nombre)
    );
}

/**
 * Si hay homónimos en el grupo, muestra @username para distinguir cuentas (sin IDs técnicos).
 */
export function subtituloRemitenteGrupo(user, participantes = []) {
    if (!participantes.length || !user?.username) return null;

    const duplicados = nombresConDuplicado(participantes);
    const nombreNormalizado = nombreRemitenteGrupo(user).toLowerCase();

    if (!duplicados.has(nombreNormalizado)) return null;

    return `@${user.username}`;
}

export function etiquetaRolParticipante(rol) {
    if (rol === 'admin') return 'Administrador';
    return 'Miembro';
}

/**
 * Agrupa mensajes consecutivos del mismo remitente (cabecera solo en el primero del bloque).
 */
export function prepararMensajesGrupo(mensajes, esGrupo) {
    if (!esGrupo) {
        return mensajes.map((mensaje) => ({ mensaje, mostrarRemitente: false }));
    }

    return mensajes.map((mensaje, index) => {
        if (mensaje.es_propio) {
            return { mensaje, mostrarRemitente: false };
        }

        const anterior = mensajes[index - 1];
        const mostrarRemitente = !anterior
            || anterior.es_propio
            || anterior.user?.id !== mensaje.user?.id;

        return { mensaje, mostrarRemitente };
    });
}

export function ordenarParticipantes(participantes = []) {
    return [...participantes].sort((a, b) => {
        if (a.rol === 'admin' && b.rol !== 'admin') return -1;
        if (b.rol === 'admin' && a.rol !== 'admin') return 1;
        return nombreRemitenteGrupo(a).localeCompare(nombreRemitenteGrupo(b), 'es');
    });
}
