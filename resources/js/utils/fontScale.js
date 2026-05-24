/** Escala tipográfica global (base 16px × escala). Límites para no romper layouts. */
export const FONT_SCALE_MIN = 0.875;
export const FONT_SCALE_MAX = 1.5;
export const FONT_SCALE_STEP = 0.0625;
export const FONT_SCALE_DEFAULT = 1;
export const FONT_SCALE_STORAGE_KEY = 'theme_font_scale';

export function clampFontScale(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return FONT_SCALE_DEFAULT;
    const stepped = Math.round(n / FONT_SCALE_STEP) * FONT_SCALE_STEP;
    return Math.min(FONT_SCALE_MAX, Math.max(FONT_SCALE_MIN, Number(stepped.toFixed(4))));
}

export function formatFontScaleLabel(scale) {
    return `${Math.round(clampFontScale(scale) * 100)}%`;
}

/** Publica la escala en :root; el zoom se aplica en .gelia-ui-scale (no en html, para no romper el sidebar fixed). */
export function applyFontScaleToRoot(scale) {
    if (typeof document === 'undefined') return;
    const value = clampFontScale(scale);
    document.documentElement.style.setProperty('--font-scale', String(value));
    document.documentElement.style.zoom = '';
}
