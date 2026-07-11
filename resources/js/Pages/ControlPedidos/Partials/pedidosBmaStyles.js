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

export const TABS_PEDIDOS = [
    { id: 'TODAS', label: 'Todas' },
    { id: 'BORRADORES', label: 'Borradores' },
    { id: 'PENDIENTE_AUXILIAR', label: 'Pendiente Auxiliar' },
    { id: 'EN_CEDIS', label: 'En CEDIS' },
    { id: 'RECHAZADAS', label: 'Rechazadas' },
];

export const TABS_AUDITORIA = [
    { id: 'PENDIENTES', label: 'Pendientes' },
    { id: 'APROBADOS', label: 'Aprobados' },
    { id: 'RECHAZADOS', label: 'Rechazados' },
    { id: 'TODAS', label: 'Todas' },
];

export const TABS_CEDIS = [
    { id: 'PENDIENTES', label: 'Pendientes' },
    { id: 'EMPACADOS', label: 'Empacados' },
    { id: 'TODOS', label: 'Todos' },
];

export const badgeAuditoriaSemantico = (fase) => {
    const map = {
        PENDIENTE_AUXILIAR: { hex: '#EAB308', label: 'Pendiente' },
        EN_CEDIS: { hex: '#22C55E', label: 'Aprobado' },
        INCIDENCIA_CEDIS: { hex: '#22C55E', label: 'Aprobado' },
        EN_RUTA: { hex: '#22C55E', label: 'Aprobado' },
        RECHAZADO_VENDEDORA: { hex: '#EF4444', label: 'Rechazado' },
    };
    const item = map[fase] || { hex: '#94A3B8', label: fase || '—' };
    return {
        label: item.label,
        ...badgeClaseEstatusPedido({ color_hex: item.hex }),
    };
};

export const badgeEmpaqueSemantico = (fase) => {
    const map = {
        EN_CEDIS: { hex: '#EAB308', label: 'Pendiente de empaque' },
        INCIDENCIA_CEDIS: { hex: '#F97316', label: 'Con detalle' },
        EN_RUTA: { hex: '#22C55E', label: 'Empacado' },
    };
    const item = map[fase] || { hex: '#94A3B8', label: '—' };
    return {
        label: item.label,
        ...badgeClaseEstatusPedido({ color_hex: item.hex }),
    };
};

export const BTN_PRIMARY = `${THEME_BTN_PRIMARY} theme-btn-primary--compact`;
export const BTN_SECONDARY = `${THEME_BTN_SECONDARY} theme-btn-primary--compact`;

export { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_LABEL, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK };

export const formatearMoneda = (valor) => {
    const n = Number(valor) || 0;
    return n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
};

export const calcularTotalCobrar = (mercancia, envio, aplicaSeguro, costoSeguro, saldoFavor) => {
    const total = Number(mercancia || 0) + Number(envio || 0) + (aplicaSeguro ? Number(costoSeguro || 0) : 0) - Number(saldoFavor || 0);
    return Math.max(0, Math.round(total * 100) / 100);
};

export const textoWhatsAppPedido = (pedido) => {
    const lineas = [
        `Pedido: ${pedido.folio}`,
        `Cliente: ${pedido.cliente?.nombre || ''}`,
        `Total: ${formatearMoneda(pedido.total_a_cobrar)}`,
        `Estado: ${pedido.estatus?.nombre_visual || ''}`,
    ];
    return encodeURIComponent(lineas.join('\n'));
};

/** Espeja EnviarPedidoBmaService::validarCamposRequeridos para feedback inmediato en UI. */
export const validarCamposEnvioPedido = (data, { comprobantesExistentes = 0 } = {}) => {
    const faltantes = [];

    if (!data.cliente_id) faltantes.push('cliente');
    if (!data.catalogo_almacen_salida_id) faltantes.push('almacén de salida');
    if (!data.catalogo_banco_id) faltantes.push('banco');
    if (!data.catalogo_tipo_caja_id) faltantes.push('tipo de caja');
    if (!data.catalogo_paqueteria_id) faltantes.push('paquetería');
    if (!data.catalogo_tipo_guia_id) faltantes.push('tipo de guía');
    if (!data.catalogo_zona_id) faltantes.push('zona');
    if (!String(data.codigo_postal || '').trim()) faltantes.push('código postal');
    if (!String(data.domicilio_entrega || '').trim()) faltantes.push('domicilio de entrega');
    if (Number(data.total_mercancia || 0) <= 0) faltantes.push('total de mercancía');

    const comprobantesNuevos = (data.comprobantes || []).length;
    if (comprobantesExistentes + comprobantesNuevos === 0) {
        faltantes.push('comprobante de pago');
    }

    return {
        valido: faltantes.length === 0,
        faltantes,
        mensaje: faltantes.length
            ? `Complete los campos requeridos: ${faltantes.join(', ')}.`
            : null,
    };
};
