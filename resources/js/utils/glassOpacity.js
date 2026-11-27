/** Opacidad del cristal de paneles (0–1). Mismo patrón que fontScale. */

export const GLASS_OPACITY_MIN = 0.5;
export const GLASS_OPACITY_MAX = 0.95;
export const GLASS_OPACITY_STEP = 0.05;
export const GLASS_OPACITY_DEFAULT = 0.75;
export const GLASS_OPACITY_STORAGE_KEY = 'theme_glass_opacity';

export function clampGlassOpacity(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return GLASS_OPACITY_DEFAULT;
    const stepped = Math.round(n / GLASS_OPACITY_STEP) * GLASS_OPACITY_STEP;
    return Math.min(GLASS_OPACITY_MAX, Math.max(GLASS_OPACITY_MIN, Number(stepped.toFixed(2))));
}

export function formatGlassOpacityLabel(opacity) {
    return `${Math.round(clampGlassOpacity(opacity) * 100)}%`;
}

/** Publica la opacidad en :root para color-mix del cristal. */
export function applyGlassOpacityToRoot(opacity, root = typeof document !== 'undefined' ? document.documentElement : null) {
    if (!root) return;
    root.style.setProperty('--theme-glass-opacity', String(clampGlassOpacity(opacity)));
}
