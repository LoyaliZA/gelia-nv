export const TIPO_INCIDENCIA_FOLIO = 'folio_no_encontrado';
export const TIPO_INCIDENCIA_DANO = 'dano';
export const TIPO_INCIDENCIA_FALTANTE = 'faltante';

export const ESTADO_INCIDENCIA_ABIERTA = 'abierta';

const STORAGE_REGISTRO = 'pdv:incidencia-registro';
const STORAGE_RESOLUCION = 'pdv:incidencia-resolucion';

const MAPA_PERMISO_TIPO = {
    [TIPO_INCIDENCIA_FOLIO]: 'incidencia_folio',
    [TIPO_INCIDENCIA_DANO]: 'incidencia_dano',
    [TIPO_INCIDENCIA_FALTANTE]: 'incidencia_faltante',
};

export function exigeEvidenciaIncidencia(tipo) {
    return tipo === TIPO_INCIDENCIA_DANO || tipo === TIPO_INCIDENCIA_FALTANTE;
}

export function exigeBultoIncidencia(tipo) {
    return tipo === TIPO_INCIDENCIA_DANO;
}

export function admiteRegistroIncidencia(resguardo) {
    return ['pendiente_recepcion', 'en_custodia'].includes(resguardo?.estado);
}

export function admiteResolucionIncidencia(incidencia) {
    return incidencia?.estado === ESTADO_INCIDENCIA_ABIERTA;
}

export function puedeRegistrarTipo(permisos, tipo) {
    const clave = MAPA_PERMISO_TIPO[tipo];
    return Boolean(clave && permisos?.[clave]);
}

export function tiposIncidenciaDisponibles(permisos, catalogos = {}) {
    return Object.entries(catalogos.tipos_incidencia || {})
        .filter(([tipo]) => puedeRegistrarTipo(permisos, tipo))
        .map(([valor, etiqueta]) => ({ valor, etiqueta }));
}

export function puedeResolverIncidencia(permisos, incidencia) {
    if (!admiteResolucionIncidencia(incidencia)) {
        return false;
    }

    if (incidencia.tipo === TIPO_INCIDENCIA_DANO || incidencia.tipo === TIPO_INCIDENCIA_FALTANTE) {
        return Boolean(permisos?.autorizar_incidencia);
    }

    if (incidencia.tipo === TIPO_INCIDENCIA_FOLIO) {
        return Boolean(permisos?.incidencia_folio);
    }

    return false;
}

export function puedeRegistrarAlgunaIncidencia(permisos, resguardo) {
    if (!admiteRegistroIncidencia(resguardo)) {
        return false;
    }

    return Object.values(MAPA_PERMISO_TIPO).some((clave) => Boolean(permisos?.[clave]));
}

export function crearBultoIncidenciaVacio(prefijo = 'inc') {
    return {
        folio: '',
        tipo: 'caja',
        condicion: 'danado',
        piezas: 1,
    };
}

export function validarFormularioIncidencia({
    tipo,
    descripcion,
    evidencias = [],
    bulto,
    almacenId,
}) {
    const errores = {};

    if (!tipo) {
        errores.tipo = 'Selecciona el tipo de incidencia.';
    }

    const texto = String(descripcion || '').trim();
    if (!texto) {
        errores.descripcion = 'Describe la incidencia.';
    }

    if (exigeEvidenciaIncidencia(tipo) && evidencias.length === 0) {
        errores.evidencias = 'Adjunta al menos una fotografía.';
    }

    if (exigeBultoIncidencia(tipo)) {
        if (!almacenId) {
            errores.almacen_id = 'Selecciona el almacén de custodia.';
        }
        if (!String(bulto?.folio || '').trim()) {
            errores.bulto_folio = 'Indica el folio del bulto dañado.';
        }
        if (!bulto?.tipo) {
            errores.bulto_tipo = 'Selecciona el tipo de bulto.';
        }
        if (!bulto?.condicion) {
            errores.bulto_condicion = 'Selecciona la condición del bulto.';
        }
    }

    return errores;
}

export function validarFormularioResolucion({ motivoResolucion }) {
    const errores = {};
    const texto = String(motivoResolucion || '').trim();

    if (!texto) {
        errores.motivo_resolucion = 'Indica el motivo de la resolución.';
    }

    return errores;
}

export function claveIdempotenciaIncidencia(resguardoId) {
    const almacenada = sessionStorage.getItem(`${STORAGE_REGISTRO}:${resguardoId}`);
    if (almacenada) return almacenada;

    const terminal = typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const clave = `pdv:inc:${resguardoId}:${terminal}`;
    sessionStorage.setItem(`${STORAGE_REGISTRO}:${resguardoId}`, clave);
    return clave;
}

export function limpiarClaveIdempotenciaIncidencia(resguardoId) {
    sessionStorage.removeItem(`${STORAGE_REGISTRO}:${resguardoId}`);
}

export function claveIdempotenciaResolucion(incidenciaId) {
    const almacenada = sessionStorage.getItem(`${STORAGE_RESOLUCION}:${incidenciaId}`);
    if (almacenada) return almacenada;

    const terminal = typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const clave = `pdv:inc-res:${incidenciaId}:${terminal}`;
    sessionStorage.setItem(`${STORAGE_RESOLUCION}:${incidenciaId}`, clave);
    return clave;
}

export function limpiarClaveIdempotenciaResolucion(incidenciaId) {
    sessionStorage.removeItem(`${STORAGE_RESOLUCION}:${incidenciaId}`);
}

export function armarFormDataIncidencia({
    version,
    idempotencyKey,
    tipo,
    descripcion,
    evidencias = [],
    bulto,
    almacenId,
}) {
    const form = new FormData();
    form.append('version', String(version));
    form.append('idempotency_key', idempotencyKey);
    form.append('tipo', tipo);
    form.append('descripcion', String(descripcion).trim());

    if (exigeBultoIncidencia(tipo) && bulto) {
        form.append('almacen_id', String(almacenId));
        form.append('bulto[folio]', String(bulto.folio).trim());
        form.append('bulto[tipo]', bulto.tipo);
        form.append('bulto[condicion]', bulto.condicion);
        form.append('bulto[piezas]', String(bulto.piezas || 1));
    }

    evidencias.forEach((archivo, indice) => {
        form.append(`evidencias[${indice}]`, archivo);
    });

    return form;
}

export function armarPayloadResolucion({
    version,
    incidenciaVersion,
    idempotencyKey,
    motivoResolucion,
}) {
    return {
        version,
        incidencia_version: incidenciaVersion,
        idempotency_key: idempotencyKey,
        motivo_resolucion: String(motivoResolucion).trim(),
    };
}

function extraerMensajeValidacion(data, prioridad = []) {
    const errores = data?.errors;
    if (!errores) return null;

    for (const campo of prioridad) {
        const mensaje = errores[campo]?.[0];
        if (typeof mensaje === 'string') return mensaje;
    }

    const primer = Object.values(errores).flat()[0];
    return typeof primer === 'string' ? primer : null;
}

export function mensajeErrorIncidencia(error) {
    const status = error?.response?.status;
    const data = error?.response?.data;

    if (status === 422) {
        return extraerMensajeValidacion(data, [
            'tipo', 'descripcion', 'evidencias', 'bulto', 'almacen_id', 'version', 'idempotency_key',
        ]) || 'Revisa los datos del reporte.';
    }

    if (status === 403) {
        return 'No tienes permiso para registrar esta incidencia.';
    }

    if (status === 409) {
        return data?.message || 'No se puede registrar la incidencia en el estado actual.';
    }

    return 'No se pudo registrar la incidencia. Verifica la conexión e intenta de nuevo.';
}

export function mensajeErrorResolucion(error) {
    const status = error?.response?.status;
    const data = error?.response?.data;

    if (status === 422) {
        return extraerMensajeValidacion(data, [
            'motivo_resolucion', 'incidencia_version', 'version', 'idempotency_key',
        ]) || 'Revisa los datos de la resolución.';
    }

    if (status === 403) {
        return 'No tienes permiso para resolver esta incidencia.';
    }

    if (status === 409) {
        return data?.message || 'La incidencia ya no admite resolución.';
    }

    return 'No se pudo resolver la incidencia. Verifica la conexión e intenta de nuevo.';
}

export function esConflictoVersionIncidencia(error) {
    const errores = error?.response?.data?.errors;
    return Boolean(errores?.version || errores?.incidencia_version);
}

export function incidenciasOrdenadasCronologicamente(incidencias = []) {
    return [...incidencias].sort((a, b) => {
        const fechaA = new Date(a.reportado_at || 0).getTime();
        const fechaB = new Date(b.reportado_at || 0).getTime();
        if (fechaA !== fechaB) return fechaB - fechaA;
        return (b.id || 0) - (a.id || 0);
    });
}

export function incidenciaEstaResuelta(incidencia) {
    return incidencia?.estado !== ESTADO_INCIDENCIA_ABIERTA;
}
