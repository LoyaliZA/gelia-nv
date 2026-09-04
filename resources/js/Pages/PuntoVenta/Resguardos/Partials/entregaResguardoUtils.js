const STORAGE_IDEMPOTENCY = 'pdv:entrega:idempotency';

export const PASOS_ENTREGA = [
    { id: 'localizar', etiqueta: 'Localizar' },
    { id: 'revisar', etiqueta: 'Revisar' },
    { id: 'receptor', etiqueta: 'Receptor' },
    { id: 'evidencia', etiqueta: 'Firma' },
    { id: 'confirmar', etiqueta: 'Confirmar' },
];

export function claveIdempotenciaEntrega(resguardoId) {
    const almacenada = sessionStorage.getItem(`${STORAGE_IDEMPOTENCY}:${resguardoId}`);
    if (almacenada) return almacenada;

    const terminal = typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const clave = `pdv:ent:${resguardoId}:${terminal}`;
    sessionStorage.setItem(`${STORAGE_IDEMPOTENCY}:${resguardoId}`, clave);
    return clave;
}

export function limpiarClaveIdempotenciaEntrega(resguardoId) {
    sessionStorage.removeItem(`${STORAGE_IDEMPOTENCY}:${resguardoId}`);
}

export function validarPasoReceptor({ relacion, nombreQuienRetira }) {
    const errores = {};

    if (!relacion) {
        errores.relacion = 'Selecciona si retira el titular o un tercero autorizado.';
    } else if (relacion !== 'titular' && relacion !== 'tercero') {
        errores.relacion = 'La relación del receptor no es válida.';
    }

    const nombre = String(nombreQuienRetira || '').trim();
    if (!nombre) {
        errores.nombre_quien_retira = 'Indica el nombre de quien retira.';
    } else if (nombre.length > 255) {
        errores.nombre_quien_retira = 'El nombre no puede exceder 255 caracteres.';
    }

    return errores;
}

export function validarPasoEvidencia({ tieneFirma }) {
    const errores = {};
    if (!tieneFirma) {
        errores.firma = 'La firma es obligatoria para confirmar la entrega.';
    }
    return errores;
}

export function validarPasoBultos({ bultoIds }) {
    const errores = {};
    if (!Array.isArray(bultoIds) || bultoIds.length === 0) {
        errores.bulto_ids = 'Selecciona al menos un bulto en custodia para entregar.';
    }
    return errores;
}

export function validarFormularioEntrega(datos) {
    return {
        ...validarPasoBultos(datos),
        ...validarPasoReceptor(datos),
        ...validarPasoEvidencia(datos),
    };
}

export function dataUrlAFichero(dataUrl, nombre = 'firma.png') {
    const [header, base64] = dataUrl.split(',');
    const mime = header.match(/:(.*?);/)?.[1] || 'image/png';
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }
    return new File([bytes], nombre, { type: mime });
}

export function armarFormDataEntrega({
    version,
    idempotencyKey,
    relacion,
    nombreQuienRetira,
    metodoValidacion,
    observaciones,
    firma,
    evidencias = [],
    bultoIds = [],
}) {
    const form = new FormData();
    form.append('version', String(version));
    form.append('idempotency_key', idempotencyKey);
    form.append('relacion', relacion);
    form.append('nombre_quien_retira', String(nombreQuienRetira).trim());
    form.append('metodo_validacion', metodoValidacion);
    if (observaciones?.trim()) {
        form.append('observaciones', observaciones.trim());
    }
    form.append('firma', firma);

    evidencias.forEach((archivo, indice) => {
        form.append(`evidencias[${indice}]`, archivo);
    });

    bultoIds.forEach((id, indice) => {
        form.append(`bulto_ids[${indice}]`, String(id));
    });

    return form;
}

export function armarFormDataEntregaMultiple(entregas) {
    const form = new FormData();

    entregas.forEach((entrega, indice) => {
        const prefijo = `entregas[${indice}]`;
        form.append(`${prefijo}[resguardo_id]`, String(entrega.resguardoId));
        form.append(`${prefijo}[version]`, String(entrega.version));
        form.append(`${prefijo}[idempotency_key]`, entrega.idempotencyKey);
        form.append(`${prefijo}[relacion]`, entrega.relacion);
        form.append(`${prefijo}[nombre_quien_retira]`, String(entrega.nombreQuienRetira).trim());
        form.append(`${prefijo}[metodo_validacion]`, entrega.metodoValidacion);
        if (entrega.observaciones?.trim()) {
            form.append(`${prefijo}[observaciones]`, entrega.observaciones.trim());
        }
        form.append(`${prefijo}[firma]`, entrega.firma);
        (entrega.bultoIds || []).forEach((id, bultoIndice) => {
            form.append(`${prefijo}[bulto_ids][${bultoIndice}]`, String(id));
        });
        (entrega.evidencias || []).forEach((archivo, evidenciaIndice) => {
            form.append(`${prefijo}[evidencias][${evidenciaIndice}]`, archivo);
        });
    });

    return form;
}

export function mensajeErrorEntrega(error) {
    const status = error?.response?.status;
    const data = error?.response?.data;

    if (status === 409) {
        return data?.message || 'Este resguardo ya fue entregado desde otra terminal.';
    }

    if (status === 422 && data?.errors) {
        const primer = Object.values(data.errors).flat()[0];
        if (typeof primer === 'string') return primer;
    }

    if (status === 403) {
        return 'No tienes permiso para registrar la entrega.';
    }

    return 'No se pudo completar la entrega. Verifica la conexión e intenta de nuevo.';
}

export function esConflictoVersion(error) {
    const errores = error?.response?.data?.errors;
    return Boolean(errores?.version);
}

export function indicePaso(pasoId) {
    return PASOS_ENTREGA.findIndex((paso) => paso.id === pasoId);
}

export function puedeAvanzarPaso(pasoId, datos) {
    if (pasoId === 'revisar') {
        return Object.keys(validarPasoBultos(datos)).length === 0;
    }
    if (pasoId === 'receptor') {
        return Object.keys(validarPasoReceptor(datos)).length === 0;
    }
    if (pasoId === 'evidencia') {
        return Object.keys(validarPasoEvidencia(datos)).length === 0;
    }
    return true;
}

export function claveIdempotenciaEntregaMultiple(resguardoIds) {
    const firma = [...resguardoIds].sort((a, b) => a - b).join('-');
    const claveAlmacen = `${STORAGE_IDEMPOTENCY}:multi:${firma}`;
    const almacenada = sessionStorage.getItem(claveAlmacen);
    if (almacenada) return almacenada;

    const terminal = typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const lote = `pdv:em:${terminal}`;
    sessionStorage.setItem(claveAlmacen, lote);
    return lote;
}

export function limpiarClaveIdempotenciaEntregaMultiple(resguardoIds) {
    const firma = [...resguardoIds].sort((a, b) => a - b).join('-');
    sessionStorage.removeItem(`${STORAGE_IDEMPOTENCY}:multi:${firma}`);
    resguardoIds.forEach((id) => limpiarClaveIdempotenciaEntrega(id));
}
