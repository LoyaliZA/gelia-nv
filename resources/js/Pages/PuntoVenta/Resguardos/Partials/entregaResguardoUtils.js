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

export function validarFormularioEntrega(datos) {
    return {
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
    if (pasoId === 'receptor') {
        return Object.keys(validarPasoReceptor(datos)).length === 0;
    }
    if (pasoId === 'evidencia') {
        return Object.keys(validarPasoEvidencia(datos)).length === 0;
    }
    return true;
}
