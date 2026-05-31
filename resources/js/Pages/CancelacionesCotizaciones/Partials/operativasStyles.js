/** Acento del módulo: hereda el tema global de AppLayout (--color-primario). */
export const ACCENT = 'var(--color-primario)';

export const ESTADO_BADGE = {
    1: 'bg-amber-500/15 text-amber-600 border-amber-500/30',
    2: 'bg-emerald-500/15 text-emerald-600 border-emerald-500/30',
    3: 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30',
    4: 'bg-red-500/15 text-red-600 border-red-500/30',
};

import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';

export const BTN_PRIMARY = `${THEME_BTN_PRIMARY} theme-btn-primary--compact`;
export const BTN_SECONDARY = `${THEME_BTN_SECONDARY} theme-btn-primary--compact`;

/** Tabs de estado y tipo: scroll horizontal en móvil */
export const ESTILOS_OPERATIVAS_TABS = `
    .operativas-tabs-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        scrollbar-width: thin;
    }

    .operativas-tabs-track {
        display: inline-flex;
        flex-wrap: nowrap;
        align-items: stretch;
        width: max-content;
        min-width: 100%;
        gap: 0.125rem;
    }

    .operativas-tabs-track .gelia-segment-btn {
        flex: 0 0 auto;
        min-width: max-content;
        padding: 0.625rem 0.875rem;
        font-size: 0.625rem;
        letter-spacing: 0.08em;
    }

    .operativas-tabs-track--tipo .gelia-segment-btn {
        font-size: 0.5625rem;
        padding: 0.5rem 0.75rem;
    }

    @media (min-width: 640px) {
        .operativas-tabs-track {
            display: flex;
            width: 100%;
        }

        .operativas-tabs-track .gelia-segment-btn {
            flex: 1 1 0;
            min-width: 4.25rem;
        }

        .operativas-tabs-track--tipo .gelia-segment-btn {
            flex: 1 1 0;
            min-width: 3.5rem;
        }
    }
`;

export const TIPOS_OPERATIVO = [
    { id: '', label: 'Todos' },
    { id: 'REMISION', label: 'Remisión' },
    { id: 'PEDIDO', label: 'Pedido' },
    { id: 'COTIZACION', label: 'Cotización' },
];

export const tipoOperativoDeProceso = (proceso) => {
    const nombre = proceso?.nombre?.toUpperCase() || '';
    if (nombre.includes('REMISIÓN') || nombre.includes('REMISION')) return 'remision';
    if (nombre.includes('PEDIDO') && nombre.includes('CANCEL')) return 'pedido';
    if (nombre.includes('COTIZACIÓN') || nombre.includes('COTIZACION')) return 'cotizacion_pedido';
    return 'generico';
};
