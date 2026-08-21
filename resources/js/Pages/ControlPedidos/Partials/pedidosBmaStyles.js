import {
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
} from '../../../utils/geliaTheme';

export const LABEL_NOTA_COMPRA_PREGUNTA = '¿Deseas que la nota de compra vaya dentro de tu envío?';
export const LABEL_NOTA_COMPRA_CAMPO = 'Nota de compra en el envío';
export const LABEL_GUIA_EMPRESA = 'Guía generada por la empresa';
export const LABEL_GUIA_CLIENTE = 'Guía proporcionada por el cliente';

export const etiquetaOrigenGuia = (pedido) => (
    Boolean(pedido?.cliente_proporciona_guia) ? LABEL_GUIA_CLIENTE : LABEL_GUIA_EMPRESA
);

export const etiquetaEnvio = (idx, caja) => {
    const n = Number(idx) + 1;
    const tipo = caja?.tipo_caja?.nombre || caja?.tipoCaja?.nombre;
    return tipo ? `Envío ${n}: ${tipo}` : `Envío ${n}`;
};

/**
 * Diferir cierre/acción de modal apilado para que el mismo clic no atraviese
 * al overlay padre (p. ej. borrador de pedido) tras desmontar el portal.
 */
export const deferModalAction = (fn, ms = 50) => {
    if (typeof fn !== 'function') return;
    window.setTimeout(fn, ms);
};

export const badgeClaseEstatusPedido = (estatus) => {
    const hex = estatus?.color_hex || '#94A3B8';
    return {
        style: {
            backgroundColor: `color-mix(in srgb, ${hex} 18%, transparent)`,
            color: hex,
            borderColor: `color-mix(in srgb, ${hex} 35%, transparent)`,
        },
        className: 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border',
    };
};

/** Etiquetas de negocio (no nombres de color como AMARILLO/AZUL). */
export const LABELS_ESTATUS_POR_FASE = {
    BORRADOR: 'Borrador',
    PESAJE_PENDIENTE: 'Pesaje pendiente',
    PESAJE_RESPONDIDO: 'Pesaje respondido',
    PENDIENTE_AUXILIAR: 'Pendiente de auditoría',
    EN_CEDIS: 'Pendiente de empaque',
    RECHAZADO_VENDEDORA: 'Rechazado o devuelto para corrección',
    INCIDENCIA_CEDIS: 'Error CEDIS',
    EN_RUTA: 'En ruta',
    PENDIENTE_DE_GUIA: 'Pendiente de guía',
    PENDIENTE_GUIA_CLIENTE: 'Pendiente de guía del cliente',
    PENDIENTE_DE_ENVIO: 'Pendiente de recolección o envío',
    ENTREGADO: 'Entregado',
    ENVIADO: 'Enviado',
    CANCELADO: 'Cancelado',
};

export const LABELS_HITO_AUDITORIA = {
    pago_en_revision: 'Pago en revisión',
    pendiente_remision: 'Pendiente de remisión',
    pago_validado: 'Pago validado',
};

export const badgeHitoAuditoria = (hito) => {
    if (!hito) return null;
    const map = {
        pago_en_revision: { hex: '#EAB308', label: LABELS_HITO_AUDITORIA.pago_en_revision },
        pendiente_remision: { hex: '#F97316', label: LABELS_HITO_AUDITORIA.pendiente_remision },
        pago_validado: { hex: '#22C55E', label: LABELS_HITO_AUDITORIA.pago_validado },
    };
    const item = map[hito] || { hex: '#94A3B8', label: LABELS_HITO_AUDITORIA[hito] || hito };
    return {
        label: item.label,
        ...badgeClaseEstatusPedido({ color_hex: item.hex }),
    };
};

/** Resguardo solo etiqueta el estado cuando el pedido ya está en flujo (no pre-venta/rechazado). */
export const etiquetaResguardoVisible = (estatus, esResguardo = false) => {
    if (!esResguardo) return false;
    const fase = estatus?.fase_ciclo;
    return Boolean(fase) && !['BORRADOR', 'PESAJE_PENDIENTE', 'PESAJE_RESPONDIDO', 'RECHAZADO_VENDEDORA'].includes(fase);
};

export const etiquetaEstatusPedido = (estatus, { esResguardo = false } = {}) => {
    if (etiquetaResguardoVisible(estatus, esResguardo)) {
        return 'Resguardo';
    }
    const fase = estatus?.fase_ciclo;
    if (fase && LABELS_ESTATUS_POR_FASE[fase]) {
        return LABELS_ESTATUS_POR_FASE[fase];
    }
    return estatus?.nombre_visual || fase || '—';
};

export const TABS_PEDIDOS_PRINCIPALES = [
    { id: 'TODAS', label: 'Todas' },
    { id: 'BORRADORES', label: 'Borradores' },
    { id: 'PESAJE_PENDIENTE', label: 'Pesaje pendiente' },
    { id: 'PESAJE_RESPONDIDO', label: 'Pesaje respondido' },
    { id: 'PENDIENTE_AUXILIAR', label: 'Pendiente de auditoría' },
    { id: 'RECHAZADAS', label: 'Rechazadas' },
];

export const TABS_PEDIDOS_SUBFILTROS = [
    { id: 'OBS_CEDIS', label: 'Obs. CEDIS' },
    { id: 'SIN_EXISTENCIA', label: 'Sin existencias' },
    { id: 'EN_CEDIS', label: 'Pendiente de empaque' },
    { id: 'PENDIENTE_GUIA_CLIENTE', label: 'Guía del cliente' },
    { id: 'ENVIADOS', label: 'Enviados' },
];

export const TABS_PEDIDOS = [
    ...TABS_PEDIDOS_PRINCIPALES,
    ...TABS_PEDIDOS_SUBFILTROS,
];

/** Papelera admin: fila aparte de los filtros operativos. */
export const TABS_PEDIDOS_ADMIN = [
    { id: 'ELIMINADAS', label: 'Eliminados' },
];

/** Estado del ciclo: filtros principales de la bandeja auxiliar. */
export const TABS_AUDITORIA_PRINCIPALES = [
    { id: 'PENDIENTES', label: 'Pendientes' },
    { id: 'APROBADOS', label: 'Aprobados' },
    { id: 'RECHAZADOS', label: 'Rechazados' },
    { id: 'TODAS', label: 'Todas' },
];

/** Colas operativas / envío: subfiltros. */
export const TABS_AUDITORIA_SUBFILTROS = [
    { id: 'PAGO_EN_REVISION', label: 'Pago en revisión' },
    { id: 'PENDIENTE_REMISION', label: 'Pendiente remisión' },
    { id: 'PAGO_VALIDADO', label: 'Pago validado' },
    { id: 'ENVIO_PENDIENTE', label: 'Envío pendiente' },
    { id: 'PENDIENTE_LIBERACION', label: 'Pendiente liberación' },
    { id: 'ANEXO_POR_VERIFICAR', label: 'Anexo por verificar' },
    { id: 'ANEXO_RECHAZADO', label: 'Anexo rechazado' },
    { id: 'CONSOLIDADOS', label: 'Complementos' },
    { id: 'RESGUARDOS', label: 'Resguardo' },
];

export const TABS_AUDITORIA = [
    ...TABS_AUDITORIA_PRINCIPALES.slice(0, 3),
    ...TABS_AUDITORIA_SUBFILTROS,
    TABS_AUDITORIA_PRINCIPALES[3],
];

export const LABELS_ESTATUS_ENVIO = {
    completo: 'Envío completo',
    pendiente_regularizacion: 'Pendiente regularizar envío',
    pendiente_revision_anexo: 'Anexo por verificar',
    anexo_rechazado: 'Anexo rechazado',
    pendiente_liberacion: 'Pendiente de liberación',
    pendiente_consolidacion: 'Pendiente consolidación',
    consolidado: 'Consolidado',
    pendiente_pesaje: 'Pendiente de pesaje CEDIS',
    pesaje_listo: 'Pesaje listo — cotizar envío',
};

/** Fases pre-venta donde badges de pesaje / obs. CEDIS pueden mostrarse junto al ciclo. */
export const FASES_PRE_VENTA = ['BORRADOR', 'PESAJE_PENDIENTE', 'PESAJE_RESPONDIDO', 'RECHAZADO_VENDEDORA'];

export const esFasePreVenta = (faseCiclo) => !faseCiclo || FASES_PRE_VENTA.includes(faseCiclo);

/** Revisión de exhibición (pedido_bma_pagos.estado_revision). */
export const LABELS_ESTADO_REVISION_PAGO = {
    pendiente: 'Pendiente de revisión',
    en_revision: 'En revisión',
    verificado: 'Verificado',
    con_observaciones: 'Con observaciones',
    rechazado: 'Rechazado',
    // legacy (pre-migración)
    confirmado: 'Verificado',
    con_diferencia: 'Con observaciones',
};

/** Cobertura de pago del pedido (calculada; independiente de revisión). */
export const LABELS_COBERTURA_PAGO = {
    sin_pago: 'Sin pago',
    parcial: 'Parcial',
    cubierto: 'Cubierto',
    con_excedente: 'Con excedente',
};

/** @deprecated Prefer LABELS_COBERTURA_PAGO + LABELS_ESTADO_REVISION_PAGO */
export const LABELS_ESTADO_PAGO_PEDIDO = {
    sin_pago: 'Sin pago',
    parcialmente_pagado: 'Parcialmente pagado',
    sobrepagado: 'Sobrepagado',
    cubierto_pendiente_revision: 'Cubierto · pendiente de revisión',
    pagado_revisado: 'Pagado y revisado',
};

export const LABELS_FORMA_PAGO = {
    transferencia: 'Transferencia',
    deposito: 'Depósito',
    efectivo: 'Efectivo',
    tarjeta: 'Tarjeta',
    otro: 'Otro',
};

/** Aplicaciones SAF en pedido (saf_aplicaciones.estado). */
export const LABELS_ESTADO_SAF_APLICACION = {
    reservado: 'Reservado',
    aplicado: 'Aplicado',
    liberado: 'Liberado',
};

/** Etiqueta humana para códigos snake_case; fallback: espacios + capitalizar. */
export const etiquetaCodigo = (codigo, mapa = {}) => {
    if (codigo == null || codigo === '') return '—';
    const key = String(codigo);
    if (mapa[key]) return mapa[key];
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => (c.toUpperCase()));
};

export const badgeEstadoRevisionPago = (estado) => {
    const colores = {
        pendiente: '#EAB308',
        en_revision: '#3B82F6',
        verificado: '#22C55E',
        con_observaciones: '#F97316',
        rechazado: '#EF4444',
        confirmado: '#22C55E',
        con_diferencia: '#F97316',
    };
    const hex = colores[estado] || '#94A3B8';
    return {
        label: etiquetaCodigo(estado, LABELS_ESTADO_REVISION_PAGO),
        ...badgeClaseEstatusPedido({ color_hex: hex }),
    };
};

export const badgeCoberturaPago = (estado) => {
    const colores = {
        sin_pago: '#94A3B8',
        parcial: '#EAB308',
        cubierto: '#22C55E',
        con_excedente: '#3B82F6',
    };
    const hex = colores[estado] || '#94A3B8';
    return {
        label: etiquetaCodigo(estado, LABELS_COBERTURA_PAGO),
        ...badgeClaseEstatusPedido({ color_hex: hex }),
    };
};

export const badgeRevisionPagoPedido = (estado) => {
    if (!estado || estado === 'sin_pagos') return null;
    return badgeEstadoRevisionPago(estado);
};

/** @deprecated Prefer badgeCoberturaPago + badgeRevisionPagoPedido */
export const badgeEstadoPagoPedido = (estado) => {
    const colores = {
        sin_pago: '#94A3B8',
        parcialmente_pagado: '#F59E0B',
        sobrepagado: '#8B5CF6',
        cubierto_pendiente_revision: '#EAB308',
        pagado_revisado: '#22C55E',
    };
    const hex = colores[estado] || '#94A3B8';
    return {
        label: etiquetaCodigo(estado, LABELS_ESTADO_PAGO_PEDIDO),
        ...badgeClaseEstatusPedido({ color_hex: hex }),
    };
};

/** Resumen compacto de fuentes de pago (bancos/métodos). */
export const textoFuentesPagoCompacto = (fuentes = [], maxVisible = 2) => {
    const list = Array.isArray(fuentes) ? fuentes.filter(Boolean) : [];
    if (list.length === 0) return { texto: '—', completo: '', extras: 0 };
    if (list.length <= maxVisible) {
        return { texto: list.join(', '), completo: list.join(', '), extras: 0 };
    }
    const visibles = list.slice(0, maxVisible);
    const extras = list.length - maxVisible;
    return {
        texto: `${visibles.join(', ')} +${extras}`,
        completo: list.join(', '),
        extras,
    };
};

export const LABELS_MOTIVO_REPESAJE = {
    anexo_piezas: 'Cliente anexó piezas',
    quita_piezas: 'Cliente quitó piezas',
    cambio_surtido: 'Cliente cambió el surtido',
    otro: 'Otro cambio de pedido',
};

export const LABELS_ESTADO_FISICO = {
    bueno: 'Bueno',
    regular: 'Regular',
    malo: 'Malo',
    danado: 'Dañado',
    sin_existencia: 'Sin existencias',
};

export const badgeEstadoFisico = (estado) => {
    const colores = {
        bueno: '#22C55E',
        regular: '#EAB308',
        malo: '#F97316',
        danado: '#EF4444',
        sin_existencia: '#0EA5E9',
    };
    const hex = colores[estado] || '#94A3B8';
    return {
        label: LABELS_ESTADO_FISICO[estado] || estado || '—',
        ...badgeClaseEstatusPedido({ color_hex: hex }),
    };
};

export const badgeEstatusEnvio = (estatusEnvio, { faseCiclo = null, forzarPesaje = false } = {}) => {
    if (!estatusEnvio || estatusEnvio === 'completo') return null;
    // Pesaje no debe encimarse con fases posteriores (salvo vista CEDIS / forzarPesaje).
    if (['pendiente_pesaje', 'pesaje_listo'].includes(estatusEnvio)
        && !forzarPesaje
        && !esFasePreVenta(faseCiclo)) {
        return null;
    }
    // La fase de ciclo ya nombra el pesaje: no repetir Pendiente CEDIS / Pesaje listo.
    const duplicaFase = !forzarPesaje && (
        (estatusEnvio === 'pendiente_pesaje' && faseCiclo === 'PESAJE_PENDIENTE')
        || (estatusEnvio === 'pesaje_listo' && faseCiclo === 'PESAJE_RESPONDIDO')
    );
    if (duplicaFase) return null;
    const colores = {
        pendiente_regularizacion: '#F59E0B',
        pendiente_revision_anexo: '#8B5CF6',
        anexo_rechazado: '#EF4444',
        pendiente_liberacion: '#3B82F6',
        pendiente_consolidacion: '#0EA5E9',
        consolidado: '#14B8A6',
        pendiente_pesaje: '#F97316',
        pesaje_listo: '#10B981',
    };
    const hex = colores[estatusEnvio] || '#94A3B8';
    return {
        label: LABELS_ESTATUS_ENVIO[estatusEnvio] || estatusEnvio,
        ...badgeClaseEstatusPedido({ color_hex: hex }),
    };
};

export const operacionEmpaqueDe = (pedido) => (
    pedido?.miembro_operacion_empaque?.operacion
    || pedido?.miembroOperacionEmpaque?.operacion
    || null
);

export const complementosDe = (pedido) => pedido?.complementos || [];

export const badgeConComplementos = (pedido) => {
    const n = complementosDe(pedido).length;
    if (n < 1) return null;
    return {
        label: `+${n} complemento${n === 1 ? '' : 's'}`,
        ...badgeClaseEstatusPedido({ color_hex: '#14B8A6' }),
    };
};

export const badgeConsolidadoEmpaque = (operacion) => {
    if (!operacion) return null;
    return {
        label: `Consolidado · ${operacion.folio_operacion || ''}`.trim(),
        ...badgeClaseEstatusPedido({ color_hex: '#14B8A6' }),
    };
};

export const puedeAnexarPagoEnvio = (pedido) => [
    'pendiente_regularizacion',
    'anexo_rechazado',
].includes(pedido?.estatus_envio);

/** Raíz en resguardo abierto pendiente de liberación (vendedora/auxiliar puede completar envío). */
export const puedeCompletarEnvioResguardo = (pedido) => {
    if (!pedido || pedido.pedido_principal_id) return false;
    if (!pedido.es_resguardo) return false;
    const fase = pedido.estatus?.fase_ciclo;
    if (!['PENDIENTE_AUXILIAR', 'EN_CEDIS'].includes(fase)) return false;
    return pedido.estatus_envio === 'pendiente_liberacion'
        || pedido.tipo_operacion_envio?.codigo === 'RESGUARDO_ABIERTO';
};

export const anexoEnvioPendienteDe = (pedido) => (
    (pedido?.anexos_envio || []).find((a) => a.estatus === 'pendiente') || null
);

export const TABS_CEDIS = [
    { id: 'TODOS', label: 'Todos' },
    { id: 'PENDIENTES_PESAJE', label: 'Pendientes de pesaje' },
    { id: 'EMPACADOS', label: 'Pendiente de empaque' },
    { id: 'PENDIENTES_ENVIO', label: 'Pendiente de recolección' },
    { id: 'PENDIENTES_GUIA', label: 'Pendientes de Guía' },
    { id: 'ENVIADOS', label: 'Enviados' },
    { id: 'INCORRECTAS', label: 'Errores CEDIS' },
];

export const TABS_DELEGADO = [
    { id: 'TODOS', label: 'Todos' },
    { id: 'PENDIENTES_GUIA', label: 'Pendientes de Guía' },
    { id: 'PENDIENTES_ENVIO', label: 'Pendiente de recolección' },
    { id: 'ENVIADOS', label: 'Enviados' },
];

export const badgeResguardoSemantico = () => ({
    label: 'Resguardo',
    ...badgeClaseEstatusPedido({ color_hex: '#3B82F6' }),
});

export const badgeResguardoApartado = () => ({
    label: 'Apartado',
    ...badgeClaseEstatusPedido({ color_hex: '#0EA5E9' }),
});

export const badgeRetrasoGuia = () => ({
    label: 'Retraso',
    ...badgeClaseEstatusPedido({ color_hex: '#F59E0B' }),
});

export const badgeRetrasoEmpaque = () => ({
    label: 'Retraso de empaque',
    ...badgeClaseEstatusPedido({ color_hex: '#EA580C' }),
});

export const badgeRetrasoRecoleccion = () => ({
    label: 'Retraso de recolección',
    ...badgeClaseEstatusPedido({ color_hex: '#C2410C' }),
});

/** Activo mientras no se empacó (independiente del badge de guía). */
export const tieneRetrasoEmpaqueActivo = (pedido) => (
    Boolean(pedido?.retraso_empaque_alertado_at) && !pedido?.empacado_at
);

/** Activo mientras no está enviado/cancelado. */
export const tieneRetrasoRecoleccionActivo = (pedido) => {
    if (!pedido?.retraso_recoleccion_alertado_at) return false;
    const fase = pedido?.estatus?.fase_ciclo;
    return !['ENVIADO', 'CANCELADO', 'ENTREGADO'].includes(fase);
};

export const badgesRetrasoSla = (pedido) => {
    const out = [];
    if (tieneRetrasoEmpaqueActivo(pedido)) out.push(badgeRetrasoEmpaque());
    if (tieneRetrasoRecoleccionActivo(pedido)) out.push(badgeRetrasoRecoleccion());
    return out;
};

export const badgeErrorDatos = () => ({
    label: 'Error datos',
    ...badgeClaseEstatusPedido({ color_hex: '#EF4444' }),
});

export const badgeCorregirRemision = () => ({
    label: 'Corregir remisión',
    ...badgeClaseEstatusPedido({ color_hex: '#F97316' }),
});

export const badgeCorregirGuia = () => ({
    label: 'Corregir guía',
    ...badgeClaseEstatusPedido({ color_hex: '#EF4444' }),
});

export const badgePendienteRevision = () => ({
    label: 'Revisión',
    ...badgeClaseEstatusPedido({ color_hex: '#EAB308' }),
});

export const badgePendienteReRevision = () => ({
    label: 'Nueva revisión',
    ...badgeClaseEstatusPedido({ color_hex: '#22C55E' }),
});

export const esPendienteReRevision = (pedido) => Boolean(pedido?.pendiente_re_revision);

export const badgeAuditoriaRevision = (pedido) => {
    if (pedido?.estatus?.fase_ciclo !== 'PENDIENTE_AUXILIAR') return null;
    const enCurso = Boolean(pedido?.en_revision_ahora);
    const reRev = esPendienteReRevision(pedido);
    if (enCurso) return badgePendienteRevision();
    if (reRev) return badgePendienteReRevision();
    return null;
};

export const camposIncorrectosDe = (pedido) => (
    Array.isArray(pedido?.campos_incorrectos) ? pedido.campos_incorrectos : []
);

export const tieneErrorRemision = (pedido) => {
    const c = camposIncorrectosDe(pedido);
    return c.includes('remision') || c.includes('folio_remision');
};

export const tieneErrorGuiaReportado = (pedido) => {
    const c = camposIncorrectosDe(pedido);
    return c.includes('numero_rastreo') || c.includes('guia_pdf');
};

/** Badge con color del catálogo + etiqueta semántica del estado. */
export const badgeEstatusPedido = (estatus, { esResguardo = false } = {}) => {
    if (etiquetaResguardoVisible(estatus, esResguardo)) {
        return badgeResguardoSemantico();
    }
    return {
        label: etiquetaEstatusPedido(estatus),
        ...badgeClaseEstatusPedido(estatus),
    };
};

export const badgeAuditoriaSemantico = (fase, esResguardo = false) => {
    if (etiquetaResguardoVisible({ fase_ciclo: fase }, esResguardo)) {
        return badgeResguardoSemantico();
    }
    const map = {
        PENDIENTE_AUXILIAR: { hex: '#EAB308', label: 'Pendiente de auditoría' },
        EN_CEDIS: { hex: '#22C55E', label: 'Aprobado' },
        INCIDENCIA_CEDIS: { hex: '#22C55E', label: 'Aprobado' },
        PENDIENTE_DE_GUIA: { hex: '#22C55E', label: 'Aprobado' },
        PENDIENTE_GUIA_CLIENTE: { hex: '#22C55E', label: 'Aprobado' },
        PENDIENTE_DE_ENVIO: { hex: '#22C55E', label: 'Aprobado' },
        ENTREGADO: { hex: '#22C55E', label: 'Aprobado' },
        ENVIADO: { hex: '#22C55E', label: 'Aprobado' },
        EN_RUTA: { hex: '#22C55E', label: 'Aprobado' },
        RECHAZADO_VENDEDORA: { hex: '#EF4444', label: 'Rechazado' },
    };
    const item = map[fase] || { hex: '#94A3B8', label: fase || '—' };
    return {
        label: item.label,
        ...badgeClaseEstatusPedido({ color_hex: item.hex }),
    };
};

export const badgeEmpaqueSemantico = (fase, esResguardo = false, resguardoApartado = false) => {
    if (etiquetaResguardoVisible({ fase_ciclo: fase }, esResguardo)) {
        return resguardoApartado ? badgeResguardoApartado() : badgeResguardoSemantico();
    }
    const map = {
        EN_CEDIS: { hex: '#EAB308', label: 'Pendiente de Empaque' },
        INCIDENCIA_CEDIS: { hex: '#F97316', label: 'Error reportado' },
        PENDIENTE_DE_GUIA: { hex: '#A855F7', label: 'Esperando Guía' },
        PENDIENTE_GUIA_CLIENTE: { hex: '#C026D3', label: 'Guía del cliente' },
        PENDIENTE_DE_ENVIO: { hex: '#0EA5E9', label: 'Pendiente de recolección' },
        ENTREGADO: { hex: '#22C55E', label: 'Empacado' },
        ENVIADO: { hex: '#22C55E', label: 'Empacado' },
    };
    const item = map[fase] || { hex: '#94A3B8', label: '—' };
    return {
        label: item.label,
        ...badgeClaseEstatusPedido({ color_hex: item.hex }),
    };
};

export const esPedidoEmpacadoCedis = (fase) =>
    ['PENDIENTE_DE_GUIA', 'PENDIENTE_GUIA_CLIENTE', 'PENDIENTE_DE_ENVIO', 'ENTREGADO', 'ENVIADO'].includes(fase);

export const guiaPdfDe = (pedido) => {
    const doc = (pedido?.documentos || []).find((d) => d.tipo === 'guia');
    if (!doc) return null;

    const url = doc.url
        || (doc.id && pedido?.id
            ? route('control_pedidos.documentos.show', { pedidoBma: pedido.id, documento: doc.id })
            : null)
        || (doc.ruta_archivo ? `/storage/${doc.ruta_archivo}` : null);
    if (!url) return null;

    return { ...doc, url };
};

export const tieneGuiaPdfDisponible = (pedido) => Boolean(guiaPdfDe(pedido));

export const tieneGuiaLista = (pedido) =>
    ['PENDIENTE_DE_ENVIO', 'ENVIADO'].includes(pedido?.estatus?.fase_ciclo) && Boolean(pedido?.numero_rastreo);

export const puedeCargarGuiaCliente = (pedido) =>
    Boolean(pedido?.cliente_proporciona_guia)
    && !pedido?.numero_rastreo
    && pedido?.estatus?.fase_ciclo === 'PENDIENTE_GUIA_CLIENTE';

export const badgeGuiaLista = () => ({
    className: 'inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-emerald-500/40 bg-emerald-500/15 text-emerald-600',
    label: 'Guía Lista',
});

export const badgeObservacionesCedis = () => ({
    className: 'inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-widest bg-orange-500/15 text-orange-600',
    label: 'Observaciones CEDIS',
});

export const badgeSinExistencias = () => ({
    className: 'inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-widest bg-sky-500/15 text-sky-600',
    label: 'Sin existencias',
});

/** Pedido con al menos una revisión en sin_existencia. */
export const pedidoTieneSinExistencias = (pedido) => {
    if (pedido?.tiene_sin_existencia_abierta) return true;
    if (pedido?.estado_fisico_general === 'sin_existencia') return true;
    const revs = pedido?.revisiones_producto || pedido?.revisionesProducto || [];
    return revs.some((r) => r.estado_fisico === 'sin_existencia');
};

export const LABELS_RESOLUCION_SIN_EXISTENCIA = {
    contactar: 'Contactar cliente',
    esperar: 'Esperar producto',
    retirar: 'Retirar producto',
    sustituir: 'Sustituir producto',
    stock_ok: 'Ya hay existencias',
};

export const revisionSinExistenciaAbierta = (r) => (
    r?.estado_fisico === 'sin_existencia'
    && !['retirar', 'sustituir', 'stock_ok'].includes(r?.resolucion)
);

export const pedidoTieneSinExistenciaAbierta = (pedido) => {
    if (pedido?.tiene_sin_existencia_abierta) return true;
    const revs = pedido?.revisiones_producto || pedido?.revisionesProducto || [];
    return revs.some(revisionSinExistenciaAbierta);
};

/** Badge operativo solo mientras Ventas aún puede actuar (misma ventana que obs. CEDIS). */
export const mostrarBadgeSinExistencias = (pedido) => (
    pedidoTieneSinExistencias(pedido) && esFasePreVenta(pedido?.estatus?.fase_ciclo)
);
/** Departamentos a mostrar: principal (como área principal); si no hay, los M2M asignados. */
export const nombresDepartamentosVendedor = (vendedor) => {
    if (!vendedor) return [];
    const principal = vendedor.departamento?.nombre;
    if (principal) return [principal];
    return (vendedor.departamentos || [])
        .map((d) => d?.nombre)
        .filter(Boolean);
};

/** @deprecated Prefer nombresDepartamentosVendedor; conserva 1 nombre para callers simples. */
export const nombreDepartamentoVendedor = (vendedor) => {
    const nombres = nombresDepartamentosVendedor(vendedor);
    return nombres[0] || null;
};

export const badgeDepartamentoVendedor = (nombre) => {
    if (!nombre) return null;
    return {
        className: 'inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-indigo-500/40 bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
        label: nombre,
    };
};

export const BTN_PRIMARY = `${THEME_BTN_PRIMARY} theme-btn-primary--compact`;
export const BTN_SECONDARY = `${THEME_BTN_SECONDARY} theme-btn-primary--compact`;

export { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_LABEL, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK };

export const formatearMoneda = (valor) => {
    const n = Number(valor);
    if (valor === '' || valor == null || Number.isNaN(n)) return '—';
    return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
};

const pad2 = (n) => String(n).padStart(2, '0');

export const formatearFechaHoraAuditoria = (valor) => {
    if (!valor) return '—';
    const d = new Date(valor);
    if (Number.isNaN(d.getTime())) return '—';
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())} ${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`;
};

export const formatearFechaNegocio = (valor) => {
    if (!valor) return '—';
    const d = new Date(valor);
    if (Number.isNaN(d.getTime())) return '—';
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
};

export const etiquetaAlmacen = (almacen) => {
    if (!almacen) return '—';
    if (almacen.codigo) return `${almacen.codigo} - ${almacen.nombre}`;
    return almacen.nombre || '—';
};

/** Etiqueta 1/3… para varias revisiones del mismo SKU/producto (por orden). */
export const etiquetasInstanciaRevision = (revisiones) => {
    const grupos = {};
    (revisiones || []).forEach((r, i) => {
        const key = String(r.producto_id || r.sku || r.descripcion_producto || `i-${i}`);
        (grupos[key] ||= []).push(i);
    });
    const out = {};
    Object.values(grupos).forEach((idxs) => {
        if (idxs.length < 2) return;
        idxs.forEach((idx, n) => {
            out[idx] = `${n + 1}/${idxs.length}`;
        });
    });
    return out;
};

export const calcularTotalCobrar = (mercancia, envio, aplicaSeguro, costoSeguro, saldoFavor) => {
    const total = Number(mercancia || 0) + Number(envio || 0) + (aplicaSeguro ? Number(costoSeguro || 0) : 0) - Number(saldoFavor || 0);
    return Math.max(0, Math.round(total * 100) / 100);
};

const round2 = (n) => Math.round(Number(n || 0) * 100) / 100;

/** Espeja RegistrarPagoPedidoBmaService::calcularResumenCobertura. */
export const calcularResumenCoberturaPago = ({
    totalMercancia = 0,
    costoEnvio = 0,
    aplicaSeguro = false,
    costoSeguro = 0,
    saldoAFavorAplicado = 0,
    totalPagado = 0,
} = {}) => {
    const totalACubrir = round2(Number(totalMercancia || 0) + Number(costoEnvio || 0) + (aplicaSeguro ? Number(costoSeguro || 0) : 0));
    const saf = round2(Math.max(0, Number(saldoAFavorAplicado || 0)));
    const totalACobrar = Math.max(0, round2(totalACubrir - saf));
    const pagado = round2(Math.max(0, Number(totalPagado || 0)));
    const delta = round2(totalACobrar - pagado);
    const pendiente = Math.max(0, delta);
    const excedenteGenerado = Math.max(0, round2(-delta));
    let cobertura = 'sin_pago';
    if (pagado <= 0 && saf <= 0) cobertura = 'sin_pago';
    else if (pendiente > 0.01) cobertura = 'parcial';
    else if (excedenteGenerado > 0.01) cobertura = 'con_excedente';
    else cobertura = 'cubierto';
    return {
        total_a_cubrir: totalACubrir,
        saldo_a_favor_aplicado: saf,
        total_a_cobrar: totalACobrar,
        total_pagado: pagado,
        pendiente,
        excedente_generado: excedenteGenerado,
        cobertura,
        total_final: totalACubrir,
        saldos_aplicados: saf,
        total_recibido: pagado,
        excedente: excedenteGenerado,
        nuevo_saldo_sugerido: excedenteGenerado,
    };
};

export const mensajePagoFaltante = (pendiente) => (
    `El total a cubrir no está completo. Faltan $${Number(pendiente).toFixed(2)}. Registre exhibiciones hasta cubrir mercancía, envío y seguro (menos el saldo a favor aplicado).`
);

/** max(real, volumétrico) redondeado al kg entero siguiente (ceil). Sin decimales. */
export const calcularPesoCobradoGuia = (pesoReal, pesoVolumetrico) => {
    const real = pesoReal === '' || pesoReal == null ? null : Number(pesoReal);
    const vol = pesoVolumetrico === '' || pesoVolumetrico == null ? null : Number(pesoVolumetrico);
    if (real == null && vol == null) return '';
    const r = Number.isFinite(real) ? real : 0;
    const v = Number.isFinite(vol) ? vol : 0;
    return String(Math.ceil(Math.max(r, v) - 1e-9));
};

const COMERCIALES_CON_COBERTURA = ['FEDEX', 'ESTAFETA', 'DHL'];

export const paqueteriaTieneCobertura = (nombrePaqueteria) => {
    const nombre = String(nombrePaqueteria || '').trim().toUpperCase();
    return COMERCIALES_CON_COBERTURA.includes(nombre);
};

export const calcCostoSeguro = (nombrePaqueteria, envio, totalMercancia) => {
    const nombre = String(nombrePaqueteria || '').trim().toUpperCase();
    if (!paqueteriaTieneCobertura(nombre)) {
        return 0;
    }

    const base = Number(envio || 0) + Number(totalMercancia || 0);
    let costo = 0;

    if (nombre === 'DHL') {
        costo = (base * 0.02) + 51;
    } else {
        costo = base * 0.025;
    }

    return Math.round(costo * 100) / 100;
};

export const calcSeguroPedido = (nombrePaqueteria, envio, totalMercancia) => ({
    aplica_seguro: paqueteriaTieneCobertura(nombrePaqueteria),
    costo_seguro: calcCostoSeguro(nombrePaqueteria, envio, totalMercancia),
});

/** Etiqueta de costo según categoría de paquetería (comercial vs local/taxi). */
export const etiquetaCostoEnvio = (paqueteria) => {
    if (!paqueteria) return 'Costo de envío';
    if (paqueteria.modalidad_tarifa === 'fija') {
        return 'Costo de transporte (tarifa fija)';
    }
    if (paqueteria.modalidad_tarifa === 'por_peso') {
        return 'Costo de transporte (por peso)';
    }
    if (paqueteria.categoria === 'comercial' || paqueteriaTieneCobertura(paqueteria.nombre)) {
        return 'Costo de envío';
    }
    return 'Costo de transporte / taxi';
};

/** Espeja CatalogoPaqueteriaPedido::calcularCostoEnvio (solo locales). */
export const calcularCostoTarifaLocal = (paqueteria, pesoCobradoKg = null) => {
    if (!paqueteria || paqueteria.categoria === 'comercial' || !paqueteria.modalidad_tarifa) {
        return null;
    }
    const monto = paqueteria.tarifa_monto != null && paqueteria.tarifa_monto !== ''
        ? Number(paqueteria.tarifa_monto)
        : null;
    if (monto == null || Number.isNaN(monto)) return null;

    if (paqueteria.modalidad_tarifa === 'fija') {
        return Math.round(monto * 100) / 100;
    }
    if (paqueteria.modalidad_tarifa !== 'por_peso') return null;
    if (pesoCobradoKg == null || Number(pesoCobradoKg) <= 0) return null;

    const paso = Number(paqueteria.tarifa_paso_peso || 0);
    if (!(paso > 0)) return null;

    const peso = Number(pesoCobradoKg);
    const pesoEnUnidad = paqueteria.tarifa_unidad_peso === 'g' ? peso * 1000 : peso;
    let pasos = Math.ceil(pesoEnUnidad / paso);
    if (pasos < 1) pasos = 1;
    return Math.round(pasos * monto * 100) / 100;
};

/**
 * Cotización lista para pedir comprobante (Opción 1: sin paso de aceptación).
 * Sin logística: basta mercancía > 0. Con logística: pesaje/habilitación + campos de envío.
 */
export const esCotizacionLista = ({
    requiereLogistica = true,
    cotizacionHabilitada = false,
    guiaCliente = false,
    envioPorCobrar = false,
    esResguardoAbierto = false,
    esResguardoComplementario = false,
    omiteCosto = false,
    catalogo_paqueteria_id = '',
    catalogo_tipo_guia_id = '',
    catalogo_zona_id = '',
    costo_envio = '',
    total_mercancia = 0,
} = {}) => {
    if (!requiereLogistica) {
        return Number(total_mercancia || 0) > 0;
    }
    if (!cotizacionHabilitada) return false;
    // Resguardo: envío/dirección se capturan al Completar envío; basta mercancía/consulta.
    if (guiaCliente || envioPorCobrar || esResguardoAbierto || esResguardoComplementario) return true;
    if (!catalogo_paqueteria_id) return false;
    if (!omiteCosto && (costo_envio === '' || costo_envio == null)) return false;
    if (!catalogo_tipo_guia_id) return false;
    if (!catalogo_zona_id) return false;
    return true;
};

export const textoWhatsAppPedido = (pedido) => {
    const identificador = pedido.folio_remision || pedido.folio;
    const lineas = [
        `Pedido: ${identificador}`,
        pedido.folio_remision && pedido.folio ? `Folio interno: ${pedido.folio}` : null,
        `Cliente: ${pedido.cliente?.nombre || ''}`,
        `Total: ${formatearMoneda(pedido.total_a_cobrar)}`,
        `Estado: ${etiquetaEstatusPedido(pedido.estatus, { esResguardo: pedido.es_resguardo })}`,
    ].filter(Boolean);
    return encodeURIComponent(lineas.join('\n'));
};

/** Espeja EnviarPedidoBmaService::validarCamposRequeridos para feedback inmediato en UI. */
export const validarCamposEnvioPedido = (data, {
    requiereLogistica = true,
    direccionesNormalizadas = false,
    esMunicipioDiferido = false,
    esResguardoAbierto = false,
    esResguardoComplementario = false,
    tienePesajeRespondido = false,
    tienePdfPedido = false,
    pagoPendiente = null,
    paqueteria = null,
    consultaCerrada = false,
    requiereConsultaCerrada = false,
    manualDireccionCompleta = null,
} = {}) => {
    const faltantes = [];
    const omiteCosto = esMunicipioDiferido || esResguardoAbierto || esResguardoComplementario
        || Boolean(data.cliente_proporciona_guia)
        || Boolean(data.envio_por_cobrar)
        || (
            !tienePesajeRespondido
            && paqueteria
            && paqueteria.categoria !== 'comercial'
            && paqueteria.modalidad_tarifa === 'por_peso'
        );
    const guiaCliente = Boolean(data.cliente_proporciona_guia);

    const claves = [];
    const marcar = (clave, label) => {
        claves.push(clave);
        faltantes.push(label);
    };

    if (requiereLogistica && !esResguardoComplementario && !tienePesajeRespondido) {
        return {
            valido: false,
            faltantes: ['pesaje CEDIS'],
            claves: ['pesaje'],
            mensaje: 'Hay campos faltantes.',
        };
    }

    if (requiereConsultaCerrada && !consultaCerrada) {
        return {
            valido: false,
            faltantes: ['cierre de consulta CEDIS'],
            claves: ['consulta_cerrada'],
            mensaje: 'Cierre la consulta CEDIS (confirme mercancía con el cliente) antes de enviar.',
        };
    }

    if (!String(data.folio_remision || '').trim()) marcar('folio_remision', 'folio de pedido');
    if (!data.cliente_id) marcar('cliente', 'cliente');
    if (!data.origen_id) marcar('origen', 'tipo de pedido');
    if (!data.almacen_id) marcar('almacen', 'almacén de salida');
    // Monto solo obligatorio con consulta cerrada (pedido final post-pesaje/consulta).
    if (consultaCerrada || !requiereConsultaCerrada) {
        if (Number(data.total_mercancia || 0) <= 0) marcar('total_mercancia', 'total de mercancía');
    }
    if (!tienePdfPedido) marcar('pdf_pedido', 'PDF o foto del pedido');

    if (pagoPendiente == null) {
        marcar('pago', 'exhibiciones de pago (guarde el borrador y registre los abonos que cubran el total)');
    } else if (Number(pagoPendiente) > 0.01) {
        marcar('pago', mensajePagoFaltante(pagoPendiente));
    }

    if (requiereLogistica) {
        if (tienePesajeRespondido) {
            if (data.peso_real_kg === '' || data.peso_real_kg == null) marcar('peso_real', 'peso real (pesaje CEDIS)');
            if (!data.catalogo_tipo_caja_id) marcar('tipo_caja', 'tipo de caja (pesaje CEDIS)');
            if (data.numero_cajas === '' || data.numero_cajas == null) marcar('numero_envios', 'número de envíos (pesaje CEDIS)');
        } else if (!omiteCosto) {
            if (data.peso_real_kg === '' || data.peso_real_kg == null) marcar('peso_real', 'peso real');
            if (!data.catalogo_tipo_caja_id) marcar('tipo_caja', 'tipo de caja');
            if (data.numero_cajas === '' || data.numero_cajas == null) marcar('numero_envios', 'número de envíos');
        }

        if (!guiaCliente) {
            if (!data.catalogo_paqueteria_id) marcar('paqueteria', 'paquetería');
            if (!esResguardoAbierto) {
                if (!data.catalogo_tipo_guia_id) marcar('tipo_guia', 'tipo de guía');
                if (!data.catalogo_zona_id) marcar('reexpedicion', 'reexpedición');
                if (!String(data.codigo_postal || '').trim()) marcar('codigo_postal', 'código postal');
                if (direccionesNormalizadas) {
                    const tieneDir = String(data.cliente_direccion_id || '').trim();
                    const excepcionFlag = Boolean(data.direccion_manual_excepcion);
                    const domicilioTxt = String(data.domicilio_entrega || '').trim();
                    const camposOk = manualDireccionCompleta === true
                        || (manualDireccionCompleta == null && excepcionFlag && domicilioTxt);
                    if (!tieneDir && !(excepcionFlag && camposOk)) {
                        marcar(
                            'domicilio',
                            excepcionFlag
                                ? 'dirección manual completa (destinatario, calle, colonia, CP, municipio, estado)'
                                : 'dirección de envío verificada o excepción manual completa'
                        );
                    }
                } else if (!String(data.domicilio_entrega || '').trim()) {
                    marcar('domicilio', 'domicilio de entrega');
                }
            }
            if (!omiteCosto && (data.costo_envio === '' || data.costo_envio == null)) {
                marcar('costo_envio', 'costo de envío');
            }
        }
    }

    return {
        valido: faltantes.length === 0,
        faltantes,
        claves,
        mensaje: faltantes.length ? 'Hay campos faltantes.' : null,
    };
};
