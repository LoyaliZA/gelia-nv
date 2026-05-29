/**
 * Utilidades de tema GELIA — consumen variables y clases de resources/css/gelia-theme.css
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
