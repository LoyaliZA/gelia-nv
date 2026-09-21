/**
 * Utilidades de tema GELIA — consumen variables y clases de resources/css/gelia/
 * (aplicadas globalmente vía AppLayout).
 */

export function isGlassEffectEnabled() {
    if (typeof window === 'undefined') return true;
    return localStorage.getItem('theme_glass') !== 'false';
}

/** Tarjeta / panel con tema global */
export function geliaCardClass(extra = '') {
    const solid = isGlassEffectEnabled() ? '' : ' theme-card--solid';
    return `animate-page-reveal theme-surface theme-card border theme-border${solid} ${extra}`.trim();
}

export const THEME_INPUT = 'theme-input theme-placeholder';
export const THEME_SELECT = 'theme-select theme-placeholder';
export const THEME_TEXTAREA = 'theme-textarea theme-placeholder';
export const THEME_LABEL = 'theme-label';
export const THEME_BTN_ICON = 'theme-btn-icon';
export const THEME_BTN_PRIMARY = 'theme-btn-primary';
export const THEME_BTN_SECONDARY = 'theme-btn-secondary';
export const THEME_MODAL_OVERLAY = 'gelia-modal-overlay animate-fade-in';
export const THEME_MODAL_SHELL = 'gelia-modal-shell';

/** Diferir cierre de modal para evitar que el clic atraviese al overlay padre. */
export const deferModalAction = (fn, ms = 50) => {
    if (typeof fn !== 'function') return;
    window.setTimeout(fn, ms);
};

export const GELIA_ESTADO_VIVO_TONO = {
    exito: 'gelia-estado-vivo--exito',
    aviso: 'gelia-estado-vivo--aviso',
    error: 'gelia-estado-vivo--error',
    neutro: 'gelia-estado-vivo--neutro',
};

/** Contenedor squircle para iconos decorativos (títulos, filas, modales). */
export const GELIA_ICON_BOX = 'gelia-icon-box theme-element border theme-border';

export const GELIA_PAGE_SHELL = 'gelia-page-shell min-w-0 box-border';

export const GELIA_PAGE_SECTION = 'w-full max-w-full min-w-0 box-border';

export const GELIA_PREVENT_OVERFLOW_X = 'overflow-x-hidden max-w-full';

export const GELIA_SEGMENT_TABS_SCROLL = 'gelia-segment-tabs-scroll';
export const GELIA_SEGMENT_TABS_TRACK = 'gelia-segment-tabs-track';
export const GELIA_SEGMENT_TABS_TRACK_COMPACT = 'gelia-segment-tabs-track gelia-segment-tabs-track--compact';

export const GELIA_LISTADO_GRID =
    'grid grid-cols-1 w-full min-w-0 items-stretch gap-5 lg:grid-cols-2 lg:gap-6 2xl:grid-cols-3';

export const GELIA_RESPONSIVE_GRID =
    'grid w-full max-w-full min-w-0 gap-[clamp(1rem,2.5vw,1.5rem)] grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';

export const GELIA_ADMIN_HUB_GRID =
    'grid w-full max-w-full min-w-0 items-stretch gap-[clamp(1rem,2.5vw,1.5rem)] grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';

/** Botón secundario con borde (acciones terciarias en módulos densos). */
export const GELIA_BTN_OUTLINE =
    'inline-flex items-center justify-center gap-2 rounded-xl border theme-border px-3 py-2 text-[10px] font-black uppercase tracking-widest theme-text-main theme-surface hover:bg-black/5 dark:hover:bg-white/5 transition-colors disabled:opacity-50 disabled:cursor-not-allowed';

/** Etiqueta compacta de estado. */
export const GELIA_BADGE =
    'inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest';

/** Chip de filtro activo (solo lectura). */
export const GELIA_CHIP =
    'inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold border theme-border theme-element theme-text-main';

/** Leyenda de fieldset en formularios modulares. */
export const GELIA_FIELDSET_LEGEND =
    'text-[10px] font-black uppercase tracking-widest theme-text-muted px-1';

/** Botón conmutador (presets / filtros exclusivos). */
export function geliaToggleBtnClass(active = false) {
    return [
        'rounded-lg border theme-border px-3 py-1.5 text-[10px] font-black uppercase tracking-widest transition-colors',
        active ? 'text-white border-transparent' : 'theme-text-main theme-surface hover:bg-black/5 dark:hover:bg-white/5',
    ].join(' ');
}
