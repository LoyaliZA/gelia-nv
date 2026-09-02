export const TIPO_CORRECCION_SNAPSHOT = 'snapshot_resguardo';
export const TIPO_CORRECCION_ANOTACION = 'anotacion_evento';

const STORAGE_DEVOLUCION = 'pdv:devolucion';
const STORAGE_CORRECCION = 'pdv:correccion';

export function bultosEnCustodia(resguardo) {
    return (resguardo?.bultos || []).filter((bulto) => bulto.estado === 'recibido');
}

export function admiteDevolucionResguardo(resguardo) {
    return resguardo?.estado === 'en_custodia' && bultosEnCustodia(resguardo).length > 0;
}

export function puedeConfirmarDevolucion(permisos, resguardo) {
    return Boolean(permisos?.confirmar_devolucion) && admiteDevolucionResguardo(resguardo);
}

export function puedeCorregirResguardo(permisos) {
    return Boolean(permisos?.corregir);
}

export function puedeAlgunaExcepcion(permisos, resguardo) {
    return puedeConfirmarDevolucion(permisos, resguardo) || puedeCorregirResguardo(permisos);
}

export function resumenImpactoDevolucion(resguardo) {
    const bultos = bultosEnCustodia(resguardo);

    return {
        cantidadBultos: bultos.length,
        folios: bultos.map((bulto) => bulto.folio || `#${bulto.id}`),
        estadoActual: resguardo?.estado,
        estadoNuevo: 'devuelto',
    };
}

export function resumenImpactoCorreccion({
    tipoCorreccion,
    resguardo,
    snapshotFolio,
    snapshotClienteNombre,
    eventoReferencia,
}) {
    if (tipoCorreccion === TIPO_CORRECCION_SNAPSHOT) {
        const cambios = [];

        const folioNuevo = String(snapshotFolio || '').trim();
        const folioActual = String(resguardo?.snapshot_folio || '').trim();
        if (folioNuevo && folioNuevo !== folioActual) {
            cambios.push({ campo: 'Folio', anterior: folioActual || '—', nuevo: folioNuevo });
        }

        const nombreNuevo = String(snapshotClienteNombre || '').trim();
        const nombreActual = String(resguardo?.snapshot_cliente_nombre || resguardo?.referencia_cliente || '').trim();
        if (nombreNuevo && nombreNuevo !== nombreActual) {
            cambios.push({ campo: 'Cliente', anterior: nombreActual || '—', nuevo: nombreNuevo });
        }

        return {
            tipo: 'snapshot',
            descripcion: 'Se registrará una corrección administrativa sobre el snapshot. Los hechos históricos no se alteran.',
            cambios,
        };
    }

    if (tipoCorreccion === TIPO_CORRECCION_ANOTACION) {
        return {
            tipo: 'anotacion',
            descripcion: 'Se agregará una anotación compensatoria vinculada al evento seleccionado. El evento original permanece intacto.',
            evento: eventoReferencia,
        };
    }

    return { tipo: null, descripcion: '' };
}

export function validarFormularioDevolucion({ motivo }) {
    const errores = {};
    const texto = String(motivo || '').trim();

    if (!texto) {
        errores.motivo = 'Indica el motivo de la devolución.';
    }

    return errores;
}

export function validarFormularioCorreccion({
    tipoCorreccion,
    motivo,
    snapshotFolio,
    snapshotClienteNombre,
    eventoReferenciaId,
    resguardo,
}) {
    const errores = {};
    const texto = String(motivo || '').trim();

    if (!tipoCorreccion) {
        errores.tipo_correccion = 'Selecciona el tipo de corrección.';
    }

    if (!texto) {
        errores.motivo = 'Indica el motivo de la corrección.';
    }

    if (tipoCorreccion === TIPO_CORRECCION_SNAPSHOT) {
        const folio = String(snapshotFolio || '').trim();
        const nombre = String(snapshotClienteNombre || '').trim();
        const folioActual = String(resguardo?.snapshot_folio || '').trim();
        const nombreActual = String(resguardo?.snapshot_cliente_nombre || resguardo?.referencia_cliente || '').trim();

        const folioCambia = folio !== '' && folio !== folioActual;
        const nombreCambia = nombre !== '' && nombre !== nombreActual;

        if (!folioCambia && !nombreCambia) {
            errores.correccion = 'Indica al menos un valor corregido distinto al actual.';
        }
    }

    if (tipoCorreccion === TIPO_CORRECCION_ANOTACION && !eventoReferenciaId) {
        errores.evento_referencia_id = 'Selecciona el evento de referencia.';
    }

    return errores;
}

function claveIdempotencia(storageKey, resguardoId, operacion) {
    const almacenada = sessionStorage.getItem(`${storageKey}:${resguardoId}`);
    if (almacenada) return almacenada;

    const terminal = typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const clave = `pdv:${operacion}:${resguardoId}:${terminal}`;
    sessionStorage.setItem(`${storageKey}:${resguardoId}`, clave);
    return clave;
}

export function claveIdempotenciaDevolucion(resguardoId) {
    return claveIdempotencia(STORAGE_DEVOLUCION, resguardoId, 'dev');
}

export function limpiarClaveIdempotenciaDevolucion(resguardoId) {
    sessionStorage.removeItem(`${STORAGE_DEVOLUCION}:${resguardoId}`);
}

export function claveIdempotenciaCorreccion(resguardoId) {
    return claveIdempotencia(STORAGE_CORRECCION, resguardoId, 'corr');
}

export function limpiarClaveIdempotenciaCorreccion(resguardoId) {
    sessionStorage.removeItem(`${STORAGE_CORRECCION}:${resguardoId}`);
}

function adjuntarEvidencias(form, evidencias = []) {
    evidencias.forEach((archivo, indice) => {
        form.append(`evidencias[${indice}]`, archivo);
    });
}

export function armarFormDataDevolucion({
    version,
    idempotencyKey,
    motivo,
    evidencias = [],
}) {
    const form = new FormData();
    form.append('version', String(version));
    form.append('idempotency_key', idempotencyKey);
    form.append('motivo', String(motivo || '').trim());
    adjuntarEvidencias(form, evidencias);
    return form;
}

export function armarFormDataCorreccion({
    version,
    idempotencyKey,
    tipoCorreccion,
    motivo,
    snapshotFolio,
    snapshotClienteNombre,
    eventoReferenciaId,
    evidencias = [],
    resguardo,
}) {
    const form = new FormData();
    form.append('version', String(version));
    form.append('idempotency_key', idempotencyKey);
    form.append('tipo_correccion', tipoCorreccion);
    form.append('motivo', String(motivo || '').trim());

    if (tipoCorreccion === TIPO_CORRECCION_SNAPSHOT) {
        const folio = String(snapshotFolio || '').trim();
        const nombre = String(snapshotClienteNombre || '').trim();
        const folioActual = String(resguardo?.snapshot_folio || '').trim();
        const nombreActual = String(resguardo?.snapshot_cliente_nombre || resguardo?.referencia_cliente || '').trim();

        if (folio !== '' && folio !== folioActual) {
            form.append('snapshot_folio', folio);
        }
        if (nombre !== '' && nombre !== nombreActual) {
            form.append('snapshot_cliente_nombre', nombre);
        }
    }

    if (tipoCorreccion === TIPO_CORRECCION_ANOTACION && eventoReferenciaId) {
        form.append('evento_referencia_id', String(eventoReferenciaId));
    }

    adjuntarEvidencias(form, evidencias);
    return form;
}

function extraerMensajeValidacion(data, campos) {
    const errores = data?.errors;
    if (!errores) return null;

    for (const campo of campos) {
        const valor = errores[campo];
        if (Array.isArray(valor) && valor[0]) return valor[0];
        if (typeof valor === 'string') return valor;
    }

    const primer = Object.values(errores).flat()[0];
    return typeof primer === 'string' ? primer : null;
}

export function mensajeErrorDevolucion(error) {
    const status = error?.response?.status;
    const data = error?.response?.data;

    if (status === 422) {
        return extraerMensajeValidacion(data, [
            'motivo', 'evidencias', 'bultos', 'estado', 'version', 'idempotency_key',
        ]) || 'Revisa los datos de la devolución.';
    }

    if (status === 403) {
        return 'No tienes permiso para confirmar la devolución.';
    }

    if (status === 409) {
        return data?.message || 'Este resguardo ya no admite devolución.';
    }

    return 'No se pudo confirmar la devolución. Verifica la conexión e intenta de nuevo.';
}

export function mensajeErrorCorreccion(error) {
    const status = error?.response?.status;
    const data = error?.response?.data;

    if (status === 422) {
        return extraerMensajeValidacion(data, [
            'tipo_correccion', 'motivo', 'snapshot_folio', 'snapshot_cliente_nombre',
            'evento_referencia_id', 'correccion', 'evidencias', 'version', 'idempotency_key',
        ]) || 'Revisa los datos de la corrección.';
    }

    if (status === 403) {
        return 'No tienes permiso para aplicar correcciones administrativas.';
    }

    if (status === 409) {
        return data?.message || 'No se puede aplicar la corrección en el estado actual.';
    }

    return 'No se pudo aplicar la corrección. Verifica la conexión e intenta de nuevo.';
}

export function esConflictoVersionExcepcion(error) {
    const errores = error?.response?.data?.errors;
    return Boolean(errores?.version);
}

export function eventosReferenciaDisponibles(timeline = []) {
    return timeline.map((evento) => ({
        id: evento.id,
        etiqueta: evento.tipo_etiqueta || evento.tipo_evento,
        ocurridoAt: evento.ocurrido_at,
        actor: evento.actor_referencia,
    }));
}
