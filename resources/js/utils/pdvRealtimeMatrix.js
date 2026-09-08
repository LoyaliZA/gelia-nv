/** Vistas del paquete Turnos/Operación con recarga autoritativa en tiempo real. */
export const PDV_VISTA_REALTIME = {
    gerencia: 'gerencia',
    vendedor: 'vendedor',
    recepcion: 'recepcion',
};

const TIPOS_TURNO_GERENCIA = new Set([
    'turno.alta',
    'turno.asignado',
    'turno.transferido',
    'turno.reatencion',
    'turno.ventana_reatencion_vencida',
    'atencion.cerrada',
    'atencion.prorroga',
]);

const TIPOS_TURNO_VENDEDOR_USUARIO = new Set([
    'turno.asignado',
    'turno.transferido',
    'turno.reatencion',
    'atencion.cerrada',
    'atencion.prorroga',
]);

const TIPOS_TURNO_RECEPCION = new Set([
    'turno.alta',
    'turno.asignado',
    'turno.transferido',
    'atencion.cerrada',
]);

const TIPOS_OPERACION_GERENCIA = new Set([
    'jornada.abierta',
    'jornada.cerrada',
    'jornada.cierre_manual',
    'jornada.cierre_horario',
    'jornada.ampliada',
    'pausa.iniciada',
    'pausa.finalizada',
    'equipo.asistencia',
]);

const TIPOS_OPERACION_VENDEDOR = new Set([
    'jornada.abierta',
    'jornada.cerrada',
    'pausa.iniciada',
    'pausa.finalizada',
    'equipo.asistencia',
]);

const TIPOS_OPERACION_RECEPCION = new Set([
    'jornada.abierta',
    'jornada.cerrada',
    'jornada.cierre_manual',
    'jornada.cierre_horario',
    'jornada.ampliada',
    'pausa.iniciada',
    'pausa.finalizada',
    'equipo.asistencia',
]);

function userIdEnvelope(envelope) {
    const datos = envelope?.datos ?? {};
    return Number(
        datos.user_id
        ?? datos.jornada?.user_id
        ?? datos.intervalo?.user_id
        ?? null,
    ) || null;
}

function afectaUsuario(envelope, userId) {
    if (!userId) return false;
    return userIdEnvelope(envelope) === Number(userId);
}

/**
 * Matriz mínima evento → vista (contrato fase 11).
 */
export function debeRefrescarVistaPdv(vista, envelope, { userId = null } = {}) {
    if (!envelope?.tipo) return false;

    const tipo = envelope.tipo;
    const dominio = envelope.dominio;

    if (vista === PDV_VISTA_REALTIME.gerencia) {
        if (dominio === 'turnos' && TIPOS_TURNO_GERENCIA.has(tipo)) return true;
        if (dominio === 'operacion' && TIPOS_OPERACION_GERENCIA.has(tipo)) return true;
        return false;
    }

    if (vista === PDV_VISTA_REALTIME.vendedor) {
        if (dominio === 'turnos') {
            if (!TIPOS_TURNO_VENDEDOR_USUARIO.has(tipo)) return false;
            if (envelope.audiencia === 'usuario') return true;
            return afectaUsuario(envelope, userId);
        }
        if (dominio === 'operacion' && TIPOS_OPERACION_VENDEDOR.has(tipo)) {
            return afectaUsuario(envelope, userId);
        }
        return false;
    }

    if (vista === PDV_VISTA_REALTIME.recepcion) {
        if (dominio === 'turnos' && TIPOS_TURNO_RECEPCION.has(tipo)) return true;
        if (dominio === 'operacion' && TIPOS_OPERACION_RECEPCION.has(tipo)) return true;
        return false;
    }

    return false;
}

/** Clave estable para agrupar recargas debounced de una misma vista. */
export function claveRecargaVistaPdv(vista) {
    return `pdv:recarga:${vista}`;
}
