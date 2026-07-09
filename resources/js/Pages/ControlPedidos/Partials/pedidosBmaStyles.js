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
