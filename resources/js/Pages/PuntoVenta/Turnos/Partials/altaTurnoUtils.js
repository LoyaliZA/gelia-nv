import { debeRefrescarVistaPdv, PDV_VISTA_REALTIME } from '../../../../utils/pdvRealtimeMatrix';

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

export function turnosVisiblesBandeja(bandeja) {
    if (!bandeja) return [];

    return [
        ...(bandeja.en_cola ?? []),
        ...(bandeja.asignados ?? []),
    ];
}

export function buscarTurnoActivoClienteEnBandeja(bandeja, clienteId) {
    if (!clienteId) return null;

    const id = Number(clienteId);

    return turnosVisiblesBandeja(bandeja).find(
        (turno) => Number(turno.cliente_id) === id,
    ) ?? null;
}

export function mensajeClienteYaEnCola(turno) {
    const folio = turno?.folio ? ` (${turno.folio})` : '';
    const estado = turno?.estado === 'ASIGNADO' ? 'asignado' : 'en cola';

    return `Esta persona ya tiene un turno ${estado}${folio}. No es necesario registrarlo de nuevo.`;
}

export function validarFormularioAltaTurno({ modo, cliente, nombreLlamado, bandeja = null }) {
    const errores = {};

    if (modo === 'cliente') {
        if (!cliente?.id) {
            errores.cliente = 'Selecciona un cliente registrado o usa la opción visitante.';
        } else {
            const turnoExistente = buscarTurnoActivoClienteEnBandeja(bandeja, cliente.id);
            if (turnoExistente) {
                errores.cliente = mensajeClienteYaEnCola(turnoExistente);
            }
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

export function sucursalAceptaAltasTurno(sucursalDia) {
    return sucursalDia?.acepta_altas !== false;
}

export function mensajeSucursalSinAltas(sucursalDia) {
    if (sucursalAceptaAltasTurno(sucursalDia)) {
        return null;
    }

    if (sucursalDia?.cierre_manual_at) {
        return 'La sucursal ya no acepta altas nuevas (cierre manual de gerencia).';
    }

    return 'La sucursal ya no acepta altas nuevas.';
}

export function formularioListoParaEnviar({
    modo,
    cliente,
    nombreLlamado,
    bandeja = null,
    sucursalDia = null,
}) {
    if (!sucursalAceptaAltasTurno(sucursalDia)) {
        return false;
    }

    return Object.keys(validarFormularioAltaTurno({
        modo,
        cliente,
        nombreLlamado,
        bandeja,
    })).length === 0;
}

export function debeRefrescarBandejaRecepcionPorEvento(envelope) {
    return debeRefrescarVistaPdv(PDV_VISTA_REALTIME.recepcion, envelope);
}

export function mensajeErrorAltaTurno(err) {
    const status = err?.response?.status;
    const data = err?.response?.data;
    const mensajeServidor = typeof data?.message === 'string' ? data.message : null;

    if (status === 422) {
        const errores = data?.errors;
        if (errores && typeof errores === 'object') {
            const primero = Object.values(errores).flat()[0];
            if (primero) return primero;
        }
        return mensajeServidor && !esMensajeSql(mensajeServidor)
            ? mensajeServidor
            : 'Revisa los datos capturados.';
    }

    if (status === 403) {
        return 'No tienes permiso para dar de alta turnos.';
    }

    if (!err?.response) {
        return 'No se pudo conectar. Verifica la red e intenta de nuevo sin cerrar el formulario.';
    }

    if (mensajeServidor && !esMensajeSql(mensajeServidor)) {
        return mensajeServidor;
    }

    return 'Ocurrió un error al registrar el turno. Actualiza la bandeja e intenta de nuevo.';
}

function esMensajeSql(mensaje) {
    return /SQLSTATE|Integrity constraint violation|Duplicate entry/i.test(mensaje);
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
