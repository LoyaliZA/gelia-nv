export function paramsLimpios(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    );
}

export function paramsListadoResguardos({
    bandeja,
    paso,
    q,
    estado,
    antiguedad,
    page = 1,
}) {
    return paramsLimpios({
        bandeja,
        paso: bandeja === 'por_recibir' ? (paso || undefined) : undefined,
        q: q || undefined,
        estado: estado || undefined,
        antiguedad: antiguedad || undefined,
        page,
    });
}

/** Referencia de cliente sin nombre completo. */
export function referenciaCliente(resguardo) {
    const numero = resguardo?.cliente?.numero_cliente;
    if (numero !== null && numero !== undefined && numero !== '') {
        return `#${numero}`;
    }
    return resguardo?.snapshot_folio || 'Sin referencia';
}

export function etiquetasClasificacionActivas(resguardo, catalogoAntiguedades = {}) {
    const clasificaciones = resguardo?.clasificaciones || {};
    return Object.entries(clasificaciones)
        .filter(([, activa]) => activa)
        .map(([clave]) => catalogoAntiguedades[clave] || clave);
}

const ANTIGUEDAD_POR_BANDEJA = {
    por_recibir: ['rezagado'],
    en_custodia: ['proximo_a_vencer', 'vencido'],
};

export function antiguedadValidaEnBandeja(bandeja, antiguedad) {
    if (!antiguedad) return true;
    const claves = ANTIGUEDAD_POR_BANDEJA[bandeja] || [];
    return claves.includes(antiguedad);
}

export function antiguedadesVisiblesPorBandeja(
    bandeja,
    catalogoAntiguedades = {},
    puedeVerVencidos = false,
    puedeVerRezagados = false,
) {
    const claves = ANTIGUEDAD_POR_BANDEJA[bandeja] || [];
    return Object.entries(catalogoAntiguedades)
        .filter(([clave]) => claves.includes(clave))
        .filter(([clave]) => clave !== 'vencido' || puedeVerVencidos)
        .filter(([clave]) => clave !== 'rezagado' || puedeVerRezagados);
}

export function metricasAntiguedadClaves(bandeja, puedeVerVencidos = false, puedeVerRezagados = false) {
    const claves = ANTIGUEDAD_POR_BANDEJA[bandeja] || [];
    return claves
        .filter((clave) => clave !== 'vencido' || puedeVerVencidos)
        .filter((clave) => clave !== 'rezagado' || puedeVerRezagados);
}

/**
 * Plazos calculados por backend; la UI solo presenta fecha y categoría.
 * @returns {Array<{ id: string, etiqueta: string, fecha: string, clasificacion: string|null }>}
 */
export function plazosOperativosResguardo(resguardo) {
    const clasificaciones = resguardo?.clasificaciones || {};
    const items = [];

    if (resguardo?.fecha_limite_rezago && resguardo?.estado === 'pendiente_recepcion') {
        items.push({
            id: 'rezago',
            etiqueta: 'Límite recepción',
            fecha: resguardo.fecha_limite_rezago,
            clasificacion: clasificaciones.rezagado ? 'rezagado' : null,
        });
    }

    if (resguardo?.fecha_limite_custodia && resguardo?.estado === 'en_custodia') {
        const clasificacion = clasificaciones.vencido
            ? 'vencido'
            : (clasificaciones.proximo_a_vencer ? 'proximo_a_vencer' : null);
        items.push({
            id: 'custodia',
            etiqueta: 'Límite custodia',
            fecha: resguardo.fecha_limite_custodia,
            clasificacion,
        });
    }

    return items;
}

export const VISTA_RESGUARDOS_POR_RECIBIR = {
    CARD: 'card',
    LISTA: 'lista',
};

const STORAGE_VISTA = 'pdv_resguardos_vista';
const STORAGE_VISTA_POR_RECIBIR = 'pdv_resguardos_vista_por_recibir';

export function leerVistaPorRecibir() {
    try {
        const guardada = globalThis.localStorage?.getItem(STORAGE_VISTA)
            || globalThis.localStorage?.getItem(STORAGE_VISTA_POR_RECIBIR);
        if (guardada === VISTA_RESGUARDOS_POR_RECIBIR.LISTA) return VISTA_RESGUARDOS_POR_RECIBIR.LISTA;
    } catch {
        // ponytail: sin localStorage seguimos con tarjetas por defecto
    }
    return VISTA_RESGUARDOS_POR_RECIBIR.CARD;
}

export function guardarVistaPorRecibir(vista) {
    try {
        globalThis.localStorage?.setItem(STORAGE_VISTA, vista);
    } catch {
        // ponytail: preferencia no crítica
    }
}

export function claseVistaTarjetas(_bandeja, vista = VISTA_RESGUARDOS_POR_RECIBIR.CARD) {
    return vista === VISTA_RESGUARDOS_POR_RECIBIR.CARD ? '' : 'hidden';
}

export function claseVistaTabla(_bandeja, vista = VISTA_RESGUARDOS_POR_RECIBIR.CARD) {
    return vista === VISTA_RESGUARDOS_POR_RECIBIR.LISTA ? '' : 'hidden';
}

export function claseGridTarjetasResguardo(_bandeja, vista = VISTA_RESGUARDOS_POR_RECIBIR.CARD) {
    return vista === VISTA_RESGUARDOS_POR_RECIBIR.CARD
        ? 'p-3 sm:p-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 md:gap-4'
        : 'p-4 space-y-3';
}

export function titularResguardo(resguardo) {
    const nombre = String(resguardo?.snapshot_cliente_nombre || '').trim();
    if (nombre) return nombre;
    return referenciaCliente(resguardo);
}

export function etiquetaRetiroResguardo(resguardo) {
    if (resguardo?.etiqueta_retiro) {
        return resguardo.etiqueta_retiro;
    }
    if (resguardo?.envia_a_otra_persona) {
        return `Recoge tercero autorizado: ${resguardo.envia_otra_persona}`;
    }
    return resguardo?.pedido_bma_id
        ? 'Retira el titular del pedido'
        : 'Retira la persona titular';
}

export function etiquetaEstadoRecepcion(resguardo, catalogos = {}, paso = 'gerente') {
    if (resguardo?.clasificaciones?.rezagado && paso === 'gerente') {
        return catalogos.antiguedades?.rezagado || 'Recepción rezagada';
    }

    const esperada = resguardo?.cantidad_bultos_esperada ?? 0;

    if (resguardo?.estado === 'recibido') {
        return catalogos.estados?.recibido || 'Recibido';
    }

    if (paso === 'recepcionista' || resguardo?.estado === 'en_recepcion') {
        const enCustodia = resguardo?.cantidad_bultos_en_custodia ?? 0;
        const pendienteCustodia = resguardo?.cantidad_bultos_pendiente_custodia ?? 0;
        if (pendienteCustodia > 0 && enCustodia > 0) {
            return `Custodia parcial (${enCustodia}/${esperada})`;
        }
        if (pendienteCustodia > 0) {
            return `En recepción (${enCustodia}/${esperada})`;
        }
        return catalogos.estados?.en_recepcion || 'En recepción';
    }

    const pendiente = resguardo?.cantidad_bultos_pendiente ?? 0;
    const recibida = resguardo?.cantidad_bultos_recibida ?? 0;
    if (pendiente > 0 && recibida > 0) {
        return `Recepción gerente (${recibida}/${esperada})`;
    }
    return catalogos.estados?.pendiente_recepcion || 'Pendiente de recepción gerente';
}

export function clasePieTarjetaRecepcion(resguardo) {
    if (resguardo?.clasificaciones?.vencido) {
        return 'border-[color-mix(in_srgb,var(--color-peligro)_50%,transparent)] text-[var(--color-peligro)] bg-[color-mix(in_srgb,var(--color-peligro)_10%,transparent)]';
    }
    if (resguardo?.clasificaciones?.rezagado) {
        return 'border-[color-mix(in_srgb,var(--color-aviso)_50%,transparent)] text-[var(--color-aviso)] bg-[color-mix(in_srgb,var(--color-aviso)_10%,transparent)]';
    }
    const pendiente = resguardo?.cantidad_bultos_pendiente ?? 0;
    const recibida = resguardo?.cantidad_bultos_recibida ?? 0;
    if (pendiente > 0 && recibida > 0) {
        return 'border-[color-mix(in_srgb,var(--color-aviso)_50%,transparent)] text-[var(--color-aviso)] bg-[color-mix(in_srgb,var(--color-aviso)_10%,transparent)]';
    }
    return 'border-[var(--color-primario)]/50 text-[var(--color-primario)] bg-[var(--color-primario)]/5';
}

const MENSAJES_VACIO_BANDEJA = {
    por_recibir: 'No hay resguardos pendientes de recepción en esta sucursal.',
    en_custodia: 'No hay resguardos en custodia en esta sucursal.',
    incidencias: 'No hay resguardos con incidencias abiertas en esta sucursal.',
};

export function mensajeVacioBandeja(bandeja, catalogoBandejas = {}, hayFiltrosActivos = false) {
    if (hayFiltrosActivos) {
        return 'No hay resguardos que coincidan con los filtros aplicados.';
    }
    return MENSAJES_VACIO_BANDEJA[bandeja]
        || `No hay resguardos en ${(catalogoBandejas[bandeja] || 'esta bandeja').toLowerCase()}`;
}
