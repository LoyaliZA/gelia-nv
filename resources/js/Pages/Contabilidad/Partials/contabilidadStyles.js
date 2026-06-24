import { geliaCardClass } from '../../../utils/geliaTheme';

const BADGE_BASE = 'inline-flex items-center border';

/** Tarjeta / panel con tema GELIA (delega a geliaCardClass) */
export function contabilidadCard(extra = '') {
    return geliaCardClass(extra);
}

/** @deprecated Usar contabilidadCard() — alias para compatibilidad */
export const CONTABILIDAD_PANEL = contabilidadCard();

export const CONTABILIDAD_PANEL_HEADER = 'theme-surface theme-element border-b theme-border';
export const CONTABILIDAD_INNER = 'theme-element border theme-border rounded-2xl';
export const CONTABILIDAD_TABLE_HEAD = 'theme-element sticky top-0 z-10';

export const HERO_EYEBROW = 'text-[10px] font-black uppercase tracking-[0.3em]';
export const HERO_TITLE = 'text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0';
export const HERO_SUBTITLE = 'text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-2';
export const SECTION_TITLE = 'text-sm font-black uppercase tracking-widest theme-text-main';
export const TABLE_TH = 'p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted';
export const TABLE_TD = 'p-6 align-top';

export const BTN_PRIMARY =
    'inline-flex items-center justify-center gap-2 px-8 py-4 rounded-2xl font-black uppercase tracking-widest text-[11px] text-white shadow-xl hover:scale-105 transition-all outline-none disabled:opacity-50 disabled:hover:scale-100';

export const BTN_PRIMARY_STYLE = { backgroundColor: 'var(--color-primario)' };

export const BTN_SECONDARY =
    'inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all';

export const BTN_SEARCH =
    'shrink-0 px-6 py-4 rounded-xl font-black uppercase text-[10px] tracking-widest text-white hover:scale-105 transition-all shadow-md flex items-center justify-center gap-2 outline-none';

export const BTN_ACCION = {
    analisis:
        'inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all',
    excel: 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30 hover:bg-emerald-500 hover:text-white',
    retiros:
        'inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border border-amber-200 bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30 hover:bg-amber-500 hover:text-white text-[10px] font-black uppercase tracking-widest transition-all',
    upload:
        'inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all cursor-pointer',
};

export const BADGE_ESTATUS = {
    pendiente: `${BADGE_BASE} bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30`,
    transferido: `${BADGE_BASE} bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30`,
};

export const BADGE_TIPO = {
    venta: `${BADGE_BASE} bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30`,
    reembolso: `${BADGE_BASE} bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30`,
    contracargo: `${BADGE_BASE} bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/30`,
};

export const BADGE_SUCCESS = `${BADGE_BASE} bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30 text-[9px] font-black uppercase px-2 py-1 rounded-md`;

export const KPI_BORDES = {
    pedidos: 'border-l-4 border-l-sky-500',
    ventas: 'border-l-4 border-l-emerald-500',
    utilidad: 'border-l-4 border-l-violet-500',
    pendientes: 'border-l-4 border-l-amber-500',
    notas: 'border-l-4 border-l-indigo-500',
    ganancia: 'border-l-4 border-l-emerald-500',
    margen: 'border-l-4 border-l-teal-500',
    perdidas: 'border-l-4 border-l-red-500',
    comisiones: 'border-l-4 border-l-orange-500',
    envios: 'border-l-4 border-l-violet-500',
    enviosCliente: 'border-l-4 border-l-slate-400',
};

export const STORAGE_LISTA_PRECIOS = 'contabilidad_lista_precios_v1';
export const STORAGE_LISTA_NOMBRE = 'contabilidad_lista_nombre_v1';
