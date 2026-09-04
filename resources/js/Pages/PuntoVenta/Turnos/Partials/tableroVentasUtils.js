const STORAGE_IDEMPOTENCY = 'pdv:turno:ventas:idempotency';

export const REFRESCO_TABLERO_MS = 30000;

export function claveIdempotenciaOperacionTurno(accion, turnoId) {
    const almacenada = sessionStorage.getItem(`${STORAGE_IDEMPOTENCY}:${accion}:${turnoId}`);
    if (almacenada) return almacenada;

    const terminal = typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const clave = `pdv:turno:${accion}:${turnoId}:${terminal}`;
    sessionStorage.setItem(`${STORAGE_IDEMPOTENCY}:${accion}:${turnoId}`, clave);
    return clave;
}

export function renovarClaveIdempotenciaOperacionTurno(accion, turnoId) {
    sessionStorage.removeItem(`${STORAGE_IDEMPOTENCY}:${accion}:${turnoId}`);
    return claveIdempotenciaOperacionTurno(accion, turnoId);
}

export function etiquetasPrioridadDesdeTurno(turno) {
    const etiquetas = [];
    if (turno?.prioridad_diamante) etiquetas.push('Diamante');
    if (turno?.prioridad_vip) etiquetas.push('VIP');
    if (turno?.prioridad_adulto_mayor) etiquetas.push('Adulto mayor');
    if (turno?.prioridad_discapacidad) etiquetas.push('Discapacidad');
    return etiquetas;
}

export function estadoUiTurnoAsignado(turno) {
    const atencion = turno?.atencion;
    if (!atencion) return 'asignado';
    if (atencion.atencion_en_curso) return 'atencion';
    if (atencion.espera_inicial_vencida) return 'espera_vencida';
    return 'espera';
}

export function debeMostrarModalEspera(turno) {
    return estadoUiTurnoAsignado(turno) === 'espera_vencida';
}

export function milisegundosRestantes(expiraAt, servidorAt) {
    if (!expiraAt || !servidorAt) return null;
    const fin = new Date(expiraAt).getTime();
    const ahora = new Date(servidorAt).getTime();
    if (!Number.isFinite(fin) || !Number.isFinite(ahora)) return null;
    return Math.max(0, fin - ahora);
}

export function formatearCronometro(milisegundos) {
    if (milisegundos === null || milisegundos === undefined) return '—';
    const totalSegundos = Math.max(0, Math.floor(milisegundos / 1000));
    const minutos = Math.floor(totalSegundos / 60);
    const segundos = totalSegundos % 60;
    return `${String(minutos).padStart(2, '0')}:${String(segundos).padStart(2, '0')}`;
}

export function mensajeErrorOperacionTurno(err, accion = 'operación') {
    const status = err?.response?.status;
    const data = err?.response?.data;

    if (status === 422) {
        const errores = data?.errors;
        if (errores?.version) {
            return errores.version[0] || 'Otro terminal modificó este turno. Actualiza e intenta de nuevo.';
        }
        if (errores && typeof errores === 'object') {
            const primero = Object.values(errores).flat()[0];
            if (primero) return primero;
        }
        return data?.message || `Revisa los datos de la ${accion}.`;
    }

    if (status === 403) {
        return 'No tienes permiso para esta acción.';
    }

    if (status === 409) {
        return data?.message || 'El turno cambió de estado. Actualiza el tablero.';
    }

    if (!err?.response) {
        return 'No se pudo conectar. Verifica la red e intenta de nuevo.';
    }

    return data?.message || `No se pudo completar la ${accion}.`;
}

export function esConflictoVersionTurno(err) {
    return Boolean(err?.response?.data?.errors?.version);
}

export function puedeIniciarAtencion(turno, permisos) {
    if (!permisos?.cerrar_atencion || !turno?.atencion) return false;
    return !turno.atencion.atencion_en_curso;
}

export function puedeCerrarAtencion(turno, permisos) {
    if (!permisos?.cerrar_atencion || !turno?.atencion) return false;
    return turno.atencion.atencion_en_curso || turno.atencion.espera_inicial_vencida;
}

export function puedeTransferir(turno, permisos) {
    return Boolean(permisos?.transferir && turno?.atencion && !turno.atencion.fin_at);
}
