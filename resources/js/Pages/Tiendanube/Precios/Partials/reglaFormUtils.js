export const OPERACIONES_PORCENTUALES = ['aumentar_porcentaje', 'reducir_porcentaje', 'margen_objetivo'];

export const OPERACIONES_SIN_PARAMETRO = ['copiar', 'eliminar'];

export const OPERACIONES_SIN_BASE = ['fijar', 'eliminar'];

export function definicionVacia(metadatos) {
    const base = metadatos?.base_inicial || 'costo_remoto_actual';

    return {
        id: `temp-${Date.now()}`,
        condiciones: [],
        base,
        base_lista_id: null,
        operacion: 'aumentar_porcentaje',
        parametro: '',
        destino: 'normal',
        redondeo: 'dos_decimales_half_up',
        redondeo_direccion: 'arriba',
    };
}

export function requiereListaBase(base) {
    return base === 'lista_referencia';
}

export function requiereParametro(operacion) {
    return !OPERACIONES_SIN_PARAMETRO.includes(operacion);
}

export function esPorcentual(operacion) {
    return OPERACIONES_PORCENTUALES.includes(operacion);
}

export function limpiarParametroAlCambiarOperacion(operacionAnterior, operacionNueva, parametro) {
    if (operacionAnterior === operacionNueva) {
        return parametro;
    }
    if (!requiereParametro(operacionNueva)) {
        return '';
    }
    if (esPorcentual(operacionAnterior) !== esPorcentual(operacionNueva)) {
        return '';
    }

    return parametro;
}

export function serializarDefinicion(form) {
    const out = {
        id: form.id,
        condiciones: (form.condiciones || []).map((c) => ({
            campo: c.campo,
            operador: c.operador,
            valor: c.valor || null,
            valor_hasta: c.valor_hasta || null,
            inclusivo_desde: c.inclusivo_desde !== false,
            inclusivo_hasta: c.inclusivo_hasta !== false,
            lista_id: c.lista_id ? Number(c.lista_id) : null,
        })),
        base: form.base,
        operacion: form.operacion,
        destino: form.destino,
        redondeo: form.redondeo || 'dos_decimales_half_up',
        redondeo_direccion: form.redondeo_direccion || 'arriba',
    };

    if (requiereListaBase(form.base) && form.base_lista_id) {
        out.base_lista_id = Number(form.base_lista_id);
    }
    if (requiereParametro(form.operacion) && form.parametro !== '') {
        out.parametro = String(form.parametro);
    }

    return out;
}

export function etiquetaCampo(valor, metadatos) {
    const lista = [...(metadatos?.bases || []), ...(metadatos?.campos_condicion || [])];
    return lista.find((o) => o.value === valor)?.label || valor;
}
