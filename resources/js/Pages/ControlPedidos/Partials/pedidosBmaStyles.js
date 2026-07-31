import {
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
} from '../../../utils/geliaTheme';

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
    PENDIENTE_AUXILIAR: 'Pendiente Auxiliar',
    EN_CEDIS: 'En CEDIS',
    RECHAZADO_VENDEDORA: 'Rechazado',
    INCIDENCIA_CEDIS: 'Incidencia CEDIS',
    EN_RUTA: 'En ruta',
    PENDIENTE_DE_GUIA: 'Pendiente de guía',
    PENDIENTE_DE_ENVIO: 'Pendiente de envío',
    ENTREGADO: 'Entregado',
    ENVIADO: 'Enviado',
};

/** Resguardo solo etiqueta el estado cuando el pedido ya está en flujo (no borrador/rechazado). */
export const etiquetaResguardoVisible = (estatus, esResguardo = false) => {
    if (!esResguardo) return false;
    const fase = estatus?.fase_ciclo;
    return Boolean(fase) && fase !== 'BORRADOR' && fase !== 'RECHAZADO_VENDEDORA';
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

export const TABS_PEDIDOS = [
    { id: 'TODAS', label: 'Todas' },
    { id: 'BORRADORES', label: 'Borradores' },
    { id: 'PENDIENTE_AUXILIAR', label: 'Pendiente Auxiliar' },
    { id: 'EN_CEDIS', label: 'En CEDIS' },
    { id: 'ENVIADOS', label: 'Enviados' },
    { id: 'RECHAZADAS', label: 'Rechazadas' },
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

export const LABELS_MOTIVO_REPESAJE = {
    anexo_piezas: 'Cliente anexó piezas',
    quita_piezas: 'Cliente quitó piezas',
    cambio_surtido: 'Cliente cambió el surtido',
    otro: 'Otro cambio de pedido',
};

export const badgeEstatusEnvio = (estatusEnvio) => {
    if (!estatusEnvio || estatusEnvio === 'completo') return null;
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
    { id: 'EMPACADOS', label: 'Empacados' },
    { id: 'PENDIENTES_ENVIO', label: 'Pendientes de Enviar' },
    { id: 'PENDIENTES_GUIA', label: 'Pendientes de Guía' },
    { id: 'ENVIADOS', label: 'Enviados' },
    { id: 'INCORRECTAS', label: 'Incorrectas' },
];

export const TABS_DELEGADO = [
    { id: 'TODOS', label: 'Todos' },
    { id: 'PENDIENTES_GUIA', label: 'Pendientes de Guía' },
    { id: 'PENDIENTES_ENVIO', label: 'Pendientes de Envío' },
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

export const badgePendienteReRevision = () => ({
    label: 'Re-revisar',
    ...badgeClaseEstatusPedido({ color_hex: '#22C55E' }),
});

export const esPendienteReRevision = (pedido) => Boolean(pedido?.pendiente_re_revision);

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
        PENDIENTE_AUXILIAR: { hex: '#EAB308', label: 'Pendiente' },
        EN_CEDIS: { hex: '#22C55E', label: 'Aprobado' },
        INCIDENCIA_CEDIS: { hex: '#22C55E', label: 'Aprobado' },
        PENDIENTE_DE_GUIA: { hex: '#22C55E', label: 'Aprobado' },
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
        PENDIENTE_DE_ENVIO: { hex: '#0EA5E9', label: 'Pendiente de envío' },
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
    ['PENDIENTE_DE_GUIA', 'PENDIENTE_DE_ENVIO', 'ENTREGADO', 'ENVIADO'].includes(fase);

export const guiaPdfDe = (pedido) => {
    const doc = (pedido?.documentos || []).find((d) => d.tipo === 'guia');
    if (!doc) return null;

    const url = doc.url || (doc.ruta_archivo ? `/storage/${doc.ruta_archivo}` : null);
    if (!url) return null;

    return { ...doc, url };
};

export const tieneGuiaPdfDisponible = (pedido) => Boolean(guiaPdfDe(pedido));

export const tieneGuiaLista = (pedido) =>
    ['PENDIENTE_DE_ENVIO', 'ENVIADO'].includes(pedido?.estatus?.fase_ciclo) && Boolean(pedido?.numero_rastreo);

export const badgeGuiaLista = () => ({
    className: 'inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-emerald-500/40 bg-emerald-500/15 text-emerald-600',
    label: 'Guía Lista',
});

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

export const calcularTotalCobrar = (mercancia, envio, aplicaSeguro, costoSeguro, saldoFavor) => {
    const total = Number(mercancia || 0) + Number(envio || 0) + (aplicaSeguro ? Number(costoSeguro || 0) : 0) - Number(saldoFavor || 0);
    return Math.max(0, Math.round(total * 100) / 100);
};

/** Fórmula Drive: se cobra el mayor entre peso real y peso volumétrico. */
export const calcularPesoCobradoGuia = (pesoReal, pesoVolumetrico) => {
    const real = pesoReal === '' || pesoReal == null ? null : Number(pesoReal);
    const vol = pesoVolumetrico === '' || pesoVolumetrico == null ? null : Number(pesoVolumetrico);
    if (real == null && vol == null) return '';
    const r = Number.isFinite(real) ? real : 0;
    const v = Number.isFinite(vol) ? vol : 0;
    return String(Math.round(Math.max(r, v) * 10000) / 10000);
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
    comprobantesExistentes = 0,
    requiereLogistica = true,
    direccionesNormalizadas = false,
    esMunicipioDiferido = false,
    esResguardoAbierto = false,
    esResguardoComplementario = false,
    tienePesajeRespondido = false,
} = {}) => {
    const faltantes = [];
    const omiteCosto = esMunicipioDiferido || esResguardoAbierto || esResguardoComplementario;

    if (requiereLogistica && !esResguardoComplementario && !tienePesajeRespondido) {
        return {
            valido: false,
            faltantes: ['pesaje CEDIS'],
            mensaje: 'Debe solicitar y recibir el pesaje de CEDIS antes de enviar el pedido al auxiliar.',
        };
    }

    if (!String(data.folio_remision || '').trim()) faltantes.push('folio de pedido');
    if (!data.cliente_id) faltantes.push('cliente');
    if (!data.origen_id) faltantes.push('origen del pedido');
    if (!data.catalogo_banco_id) faltantes.push('banco');
    if (!data.almacen_id) faltantes.push('almacén de salida');
    if (Number(data.total_mercancia || 0) <= 0) faltantes.push('total de mercancía');

    const comprobantesNuevos = (data.comprobantes || []).length;
    if (comprobantesExistentes + comprobantesNuevos === 0) {
        faltantes.push('comprobante de pago');
    }

    if (requiereLogistica) {
        if (tienePesajeRespondido) {
            if (data.peso_real_kg === '' || data.peso_real_kg == null) faltantes.push('peso real (pesaje CEDIS)');
            if (!data.catalogo_tipo_caja_id) faltantes.push('tipo de caja (pesaje CEDIS)');
            if (data.numero_cajas === '' || data.numero_cajas == null) faltantes.push('número de cajas (pesaje CEDIS)');
        } else if (!omiteCosto) {
            if (data.peso_real_kg === '' || data.peso_real_kg == null) faltantes.push('peso real');
            if (!data.catalogo_tipo_caja_id) faltantes.push('tipo de caja');
            if (data.numero_cajas === '' || data.numero_cajas == null) faltantes.push('número de cajas');
        }

        if (!data.catalogo_tipo_guia_id) faltantes.push('tipo de guía');
        if (!data.catalogo_paqueteria_id) faltantes.push('paquetería');
        if (!data.catalogo_zona_id) faltantes.push('reexpedición');
        if (!omiteCosto && (data.costo_envio === '' || data.costo_envio == null)) {
            faltantes.push('costo de envío');
        }
        if (!String(data.codigo_postal || '').trim()) faltantes.push('código postal');
        if (direccionesNormalizadas) {
            const tieneDir = String(data.cliente_direccion_id || '').trim();
            const excepcion = Boolean(data.direccion_manual_excepcion) && String(data.domicilio_entrega || '').trim();
            if (!tieneDir && !excepcion) {
                faltantes.push('dirección de envío verificada o excepción manual');
            }
        } else if (!String(data.domicilio_entrega || '').trim()) {
            faltantes.push('domicilio de entrega');
        }
    }

    return {
        valido: faltantes.length === 0,
        faltantes,
        mensaje: faltantes.length
            ? `Complete los campos requeridos: ${faltantes.join(', ')}.`
            : null,
    };
};
