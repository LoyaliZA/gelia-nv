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

export const GELIA_PAGE_SHELL =
    'w-full max-w-[1400px] mx-auto p-4 md:p-8 pb-[clamp(1.5rem,4vw,2.5rem)] min-w-0 box-border';

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
