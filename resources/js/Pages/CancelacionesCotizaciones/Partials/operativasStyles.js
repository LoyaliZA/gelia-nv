import {
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
    GELIA_SEGMENT_TABS_TRACK_COMPACT,
} from '../../../utils/geliaTheme';

/** Acento del módulo: hereda el tema global de AppLayout (--color-primario). */
export const ACCENT = 'var(--color-primario)';

export const ESTADO_BADGE = {
    1: 'bg-amber-500/15 text-amber-600 border-amber-500/30',
    2: 'bg-emerald-500/15 text-emerald-600 border-emerald-500/30',
    3: 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30',
    4: 'bg-red-500/15 text-red-600 border-red-500/30',
};

export const BTN_PRIMARY = `${THEME_BTN_PRIMARY} theme-btn-primary--compact`;
export const BTN_SECONDARY = `${THEME_BTN_SECONDARY} theme-btn-primary--compact`;

export {
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
    GELIA_SEGMENT_TABS_TRACK_COMPACT,
};

export const TIPOS_OPERATIVO = [
    { id: '', label: 'Todos' },
    { id: 'REMISION', label: 'Remisión' },
    { id: 'PEDIDO', label: 'Pedido' },
    { id: 'COTIZACION', label: 'Cotización' },
];

export const tipoOperativoDeProceso = (proceso) => {
    const nombre = proceso?.nombre?.toUpperCase() || '';
    if (nombre.includes('COTIZACIÓN') || nombre.includes('COTIZACION')) return 'cotizacion_pedido';
    if (nombre.includes('REMISIÓN') || nombre.includes('REMISION')) return 'remision';
    if (nombre.includes('PEDIDO') && nombre.includes('CANCEL')) return 'pedido';
    return 'generico';
};
