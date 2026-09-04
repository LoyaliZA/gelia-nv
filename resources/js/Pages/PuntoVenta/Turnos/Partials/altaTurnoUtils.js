const STORAGE_IDEMPOTENCY = 'pdv:turno:alta:idempotency';

export function claveIdempotenciaAltaTurno(sesionId = 'actual') {
    const almacenada = sessionStorage.getItem(`${STORAGE_IDEMPOTENCY}:${sesionId}`);
    if (almacenada) return almacenada;

    const terminal = typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const clave = `pdv:turno:alta:${sesionId}:${terminal}`;
    sessionStorage.setItem(`${STORAGE_IDEMPOTENCY}:${sesionId}`, clave);
    return clave;
}

export function renovarClaveIdempotenciaAltaTurno(sesionId = 'actual') {
    sessionStorage.removeItem(`${STORAGE_IDEMPOTENCY}:${sesionId}`);
    return claveIdempotenciaAltaTurno(sesionId);
}

export function armarPayloadAltaTurno({
    idempotencyKey,
    clienteId = null,
    nombreLlamado = null,
    prioridadAdultoMayor = false,
    prioridadDiscapacidad = false,
}) {
    const payload = {
        idempotency_key: idempotencyKey,
        prioridad_adulto_mayor: prioridadAdultoMayor,
        prioridad_discapacidad: prioridadDiscapacidad,
    };

    if (clienteId) {
        payload.cliente_id = clienteId;
    }

    if (nombreLlamado) {
        payload.nombre_llamado = nombreLlamado;
    }

    return payload;
}

export function validarFormularioAltaTurno({ modo, cliente, nombreLlamado }) {
    const errores = {};

    if (modo === 'cliente') {
        if (!cliente?.id) {
            errores.cliente = 'Selecciona un cliente registrado o usa la opción visitante.';
        }
    } else if (modo === 'visitante') {
        const nombre = String(nombreLlamado || '').trim();
        if (nombre.length < 2) {
            errores.nombre_llamado = 'Captura el nombre para llamado del visitante.';
        }
    } else {
        errores.modo = 'Selecciona cómo registrar el turno.';
    }

    return errores;
}

export function mensajeErrorAltaTurno(err) {
    const status = err?.response?.status;
    const data = err?.response?.data;

    if (status === 422) {
        const errores = data?.errors;
        if (errores && typeof errores === 'object') {
            const primero = Object.values(errores).flat()[0];
            if (primero) return primero;
        }
        return data?.message || 'Revisa los datos capturados.';
    }

    if (status === 403) {
        return 'No tienes permiso para dar de alta turnos.';
    }

    if (!err?.response) {
        return 'No se pudo conectar. Verifica la red e intenta de nuevo sin cerrar el formulario.';
    }

    return data?.message || 'Ocurrió un error al registrar el turno.';
}

export function etiquetaEstadoTurno(estado, catalogos = {}) {
    return catalogos?.estados?.[estado] || estado || '—';
}

export function etiquetasPrioridadTurno(turno) {
    const etiquetas = [];
    if (turno?.prioridad_diamante) etiquetas.push('Diamante');
    if (turno?.prioridad_vip) etiquetas.push('VIP');
    if (turno?.prioridad_adulto_mayor) etiquetas.push('Adulto mayor');
    if (turno?.prioridad_discapacidad) etiquetas.push('Discapacidad');
    return etiquetas;
}

export function esListaDiamanteCliente(cliente) {
    const nombre = String(cliente?.lista_actual || '').toUpperCase();
    return nombre.includes('DIAMANTE');
}
