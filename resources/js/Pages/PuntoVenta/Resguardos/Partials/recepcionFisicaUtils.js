const STORAGE_IDEMPOTENCY = 'pdv:recepcion:idempotency';

export function extraerFolioEscaneado(codigo) {
    const valor = String(codigo || '').trim();
    if (!valor) return '';

    const desdeUrl = valor.match(/(?:folio|remision|resguardo)[=/:]([^/?&#\s]+)/i);
    if (desdeUrl?.[1]) return desdeUrl[1].trim();

    const desdeEtiqueta = valor.match(/(?:etiquetas\/resolver\/|codigo[=:])([A-Za-z0-9]+)/i);
    if (desdeEtiqueta?.[1]) return desdeEtiqueta[1].trim();

    return valor;
}

export function crearBultosVacios(cantidad, prefijo = 'bulto') {
    const total = Math.max(0, Number(cantidad) || 0);
    const base = Date.now();
    return Array.from({ length: total }, (_, indice) => ({
        folio: '',
        tipo: 'caja',
        condicion: 'bueno',
        piezas: 1,
        key: `${prefijo}-${base}-${indice}`,
    }));
}

export function foliosBultosRecibidos(resguardo) {
    if (Array.isArray(resguardo?.bultos_recibidos)) {
        return resguardo.bultos_recibidos
            .map((bulto) => String(bulto.folio || '').trim())
            .filter(Boolean);
    }

    if (Array.isArray(resguardo?.bultos)) {
        return resguardo.bultos
            .filter((bulto) => bulto.estado === 'recibido')
            .map((bulto) => String(bulto.folio || '').trim())
            .filter(Boolean);
    }

    return [];
}

export function cantidadBultosRecibida(resguardo) {
    if (typeof resguardo?.cantidad_bultos_recibida === 'number') {
        return resguardo.cantidad_bultos_recibida;
    }

    return foliosBultosRecibidos(resguardo).length;
}

export function cantidadBultosPendiente(resguardo) {
    if (typeof resguardo?.cantidad_bultos_pendiente === 'number') {
        return resguardo.cantidad_bultos_pendiente;
    }

    const esperada = Number(resguardo?.cantidad_bultos_esperada) || 0;

    return Math.max(0, esperada - cantidadBultosRecibida(resguardo));
}

export function esRecepcionComplementaria(resguardo) {
    return cantidadBultosRecibida(resguardo) > 0;
}

export function resguardoAdmiteRecepcion(resguardo, puedeRecibir = null) {
    if (typeof puedeRecibir === 'boolean') {
        return puedeRecibir;
    }

    if (typeof resguardo?.puede_recibir === 'boolean') {
        return resguardo.puede_recibir;
    }

    const estadoPermitido = ['pendiente_recepcion', 'en_custodia'].includes(resguardo?.estado);

    return estadoPermitido && cantidadBultosPendiente(resguardo) > 0;
}

export function resguardoAdmiteEntregaTotal(resguardo) {
    if (typeof resguardo?.recepcion_completa === 'boolean') {
        return resguardo.recepcion_completa;
    }

    const esperada = Number(resguardo?.cantidad_bultos_esperada) || 0;

    return esperada > 0 && cantidadBultosRecibida(resguardo) >= esperada;
}

export function validarFormularioRecepcion({
    almacenId,
    bultos,
    cantidadPendiente,
    foliosRecibidos = [],
}) {
    const errores = {};

    if (!almacenId) {
        errores.almacen_id = 'Selecciona la ubicación de custodia.';
    }

    const pendiente = Number(cantidadPendiente) || 0;
    if (pendiente < 1) {
        errores.bultos = 'Este resguardo ya no tiene bultos pendientes por recibir.';
        return errores;
    }

    if (!Array.isArray(bultos) || bultos.length < 1) {
        errores.bultos = 'Registra al menos un bulto de esta llegada.';
        return errores;
    }

    if (bultos.length > pendiente) {
        errores.bultos = `Solo faltan ${pendiente} bulto(s); no puedes registrar más en esta llegada.`;
        return errores;
    }

    const foliosPrevios = new Set(foliosRecibidos.map((folio) => String(folio).trim()).filter(Boolean));

    bultos.forEach((bulto, indice) => {
        const folio = String(bulto.folio || '').trim();

        if (!folio) {
            errores[`bultos.${indice}.folio`] = 'El folio del bulto es obligatorio.';
        } else if (foliosPrevios.has(folio)) {
            errores[`bultos.${indice}.folio`] = 'El folio ya fue recibido en una llegada anterior.';
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
        errores.bultos = 'Los folios de bulto deben ser únicos en esta llegada.';
    }

    return errores;
}

export function mensajeConfirmacionRecepcion({
    cantidadLlegada,
    cantidadPendiente,
    esComplementaria,
}) {
    const llegada = Number(cantidadLlegada) || 0;
    const pendiente = Number(cantidadPendiente) || 0;
    const restante = Math.max(0, pendiente - llegada);
    const completaResguardo = restante === 0;

    if (esComplementaria && !completaResguardo) {
        return {
            titulo: 'Confirmar llegada parcial',
            mensaje: `Se registrará una llegada complementaria de ${llegada} bulto(s). Quedarán ${restante} pendiente(s) por recibir.`,
            etiquetaConfirmar: 'Sí, registrar llegada parcial',
        };
    }

    if (esComplementaria && completaResguardo) {
        return {
            titulo: 'Confirmar llegada final',
            mensaje: `Se registrarán los últimos ${llegada} bulto(s) pendientes y se completará la recepción del resguardo.`,
            etiquetaConfirmar: 'Sí, completar recepción',
        };
    }

    if (!completaResguardo) {
        return {
            titulo: 'Confirmar llegada parcial',
            mensaje: `Se registrará una llegada parcial de ${llegada} bulto(s). Quedarán ${restante} pendiente(s) por recibir.`,
            etiquetaConfirmar: 'Sí, registrar llegada parcial',
        };
    }

    return {
        titulo: 'Confirmar recepción',
        mensaje: `Se registrará la recepción total de ${llegada} bulto(s) en custodia. Esta acción no se puede deshacer.`,
        etiquetaConfirmar: 'Sí, recibir resguardo',
    };
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
        return data?.message || 'Este resguardo ya recibió todos los bultos esperados.';
    }

    if (status === 422 && data?.errors) {
        const errores = data.errors;
        const prioridad = ['bultos', 'version', 'idempotency_key', 'estado', 'almacen_id'];
        for (const campo of prioridad) {
            const mensaje = errores[campo]?.[0];
            if (typeof mensaje === 'string') return mensaje;
        }

        const folioError = Object.entries(errores).find(([clave]) => clave.startsWith('bultos.'));
        if (folioError?.[1]?.[0]) {
            return folioError[1][0];
        }

        const primer = Object.values(errores).flat()[0];
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
