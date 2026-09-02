const STORAGE_IDEMPOTENCY = 'pdv:recepcion:idempotency';

export function extraerFolioEscaneado(codigo) {
    const valor = String(codigo || '').trim();
    if (!valor) return '';

    const desdeUrl = valor.match(/(?:folio|remision|resguardo)[=/:]([^/?&#\s]+)/i);
    if (desdeUrl?.[1]) return desdeUrl[1].trim();

    return valor;
}

export function crearBultosVacios(cantidad) {
    const total = Math.max(0, Number(cantidad) || 0);
    return Array.from({ length: total }, (_, indice) => ({
        folio: '',
        tipo: 'caja',
        condicion: 'bueno',
        piezas: 1,
        key: `bulto-${indice}`,
    }));
}

export function validarFormularioRecepcion({ almacenId, bultos, cantidadEsperada }) {
    const errores = {};

    if (!almacenId) {
        errores.almacen_id = 'Selecciona la ubicación de custodia.';
    }

    const esperada = Number(cantidadEsperada) || 0;
    if (!Array.isArray(bultos) || bultos.length !== esperada) {
        errores.bultos = `Debes registrar exactamente ${esperada} bulto(s).`;
        return errores;
    }

    bultos.forEach((bulto, indice) => {
        if (!String(bulto.folio || '').trim()) {
            errores[`bultos.${indice}.folio`] = 'El folio del bulto es obligatorio.';
        }
        if (!bulto.tipo) {
            errores[`bultos.${indice}.tipo`] = 'Selecciona el tipo de bulto.';
        }
        if (!String(bulto.condicion || '').trim()) {
            errores[`bultos.${indice}.condicion`] = 'Indica la condición del bulto.';
        }
        const piezas = Number(bulto.piezas);
        if (!Number.isFinite(piezas) || piezas < 1) {
            errores[`bultos.${indice}.piezas`] = 'Las piezas deben ser al menos 1.';
        }
    });

    const folios = bultos.map((b) => String(b.folio || '').trim()).filter(Boolean);
    if (folios.length !== new Set(folios).size) {
        errores.bultos = 'Los folios de bulto deben ser únicos.';
    }

    return errores;
}

export function claveIdempotenciaRecepcion(resguardoId) {
    const almacenada = sessionStorage.getItem(`${STORAGE_IDEMPOTENCY}:${resguardoId}`);
    if (almacenada) return almacenada;

    const terminal = typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    const clave = `pdv:rec:${resguardoId}:${terminal}`;
    sessionStorage.setItem(`${STORAGE_IDEMPOTENCY}:${resguardoId}`, clave);
    return clave;
}

export function limpiarClaveIdempotenciaRecepcion(resguardoId) {
    sessionStorage.removeItem(`${STORAGE_IDEMPOTENCY}:${resguardoId}`);
}

export function armarFormDataRecepcion({
    version,
    idempotencyKey,
    almacenId,
    bultos,
    evidencias = [],
}) {
    const form = new FormData();
    form.append('version', String(version));
    form.append('idempotency_key', idempotencyKey);
    form.append('almacen_id', String(almacenId));

    bultos.forEach((bulto, indice) => {
        form.append(`bultos[${indice}][folio]`, String(bulto.folio).trim());
        form.append(`bultos[${indice}][tipo]`, bulto.tipo);
        form.append(`bultos[${indice}][condicion]`, bulto.condicion);
        form.append(`bultos[${indice}][piezas]`, String(bulto.piezas));
    });

    evidencias.forEach((archivo, indice) => {
        form.append(`evidencias[${indice}]`, archivo);
    });

    return form;
}

export function mensajeErrorRecepcion(error) {
    const status = error?.response?.status;
    const data = error?.response?.data;

    if (status === 409) {
        return data?.message || 'Este resguardo ya fue recibido desde otra terminal.';
    }

    if (status === 422 && data?.errors) {
        const primer = Object.values(data.errors).flat()[0];
        if (typeof primer === 'string') return primer;
    }

    if (status === 403) {
        return 'No tienes permiso para registrar la recepción.';
    }

    return 'No se pudo completar la recepción. Verifica la conexión e intenta de nuevo.';
}

export function esConflictoVersion(error) {
    const errores = error?.response?.data?.errors;
    return Boolean(errores?.version);
}
