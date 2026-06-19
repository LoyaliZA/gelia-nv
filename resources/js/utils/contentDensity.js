/** Densidad / abarcamiento del contenido principal (patrón similar a fontScale). */

export const CONTENT_DENSITY_STORAGE_KEY = 'theme_content_density';
export const CONTENT_MAX_REM_STORAGE_KEY = 'theme_content_max_rem';
export const CONTENT_PADDING_REM_STORAGE_KEY = 'theme_content_padding_rem';

export const CONTENT_DENSITY_MODES = ['compacto', 'completo', 'personalizado'];
export const CONTENT_DENSITY_DEFAULT = 'compacto';

export const CONTENT_MAX_REM_MIN = 60;
export const CONTENT_MAX_REM_MAX = 120;
export const CONTENT_MAX_REM_DEFAULT = 87.5;
export const CONTENT_MAX_REM_STEP = 2.5;

export const CONTENT_PADDING_REM_MIN = 0.5;
export const CONTENT_PADDING_REM_MAX = 2;
export const CONTENT_PADDING_REM_DEFAULT = 1.25;
export const CONTENT_PADDING_REM_STEP = 0.125;

const PRESETS = {
    compacto: {
        contentMaxRem: 87.5,
        paddingRem: 1.25,
        shellPaddingBlock: 'clamp(0.75rem, 2vw, 1.5rem)',
        shellPaddingInline: 'clamp(0.75rem, 2vw, 1.5rem)',
    },
    completo: {
        contentMaxRem: null,
        paddingRem: 0.625,
        shellPaddingBlock: 'clamp(0.5rem, 1.5vw, 1rem)',
        shellPaddingInline: 'clamp(0.375rem, 1vw, 0.75rem)',
    },
};

export function clampContentMaxRem(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return CONTENT_MAX_REM_DEFAULT;
    const stepped = Math.round(n / CONTENT_MAX_REM_STEP) * CONTENT_MAX_REM_STEP;
    return Math.min(CONTENT_MAX_REM_MAX, Math.max(CONTENT_MAX_REM_MIN, Number(stepped.toFixed(2))));
}

export function clampContentPaddingRem(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return CONTENT_PADDING_REM_DEFAULT;
    const stepped = Math.round(n / CONTENT_PADDING_REM_STEP) * CONTENT_PADDING_REM_STEP;
    return Math.min(CONTENT_PADDING_REM_MAX, Math.max(CONTENT_PADDING_REM_MIN, Number(stepped.toFixed(3))));
}

export function normalizeDensityMode(value) {
    return CONTENT_DENSITY_MODES.includes(value) ? value : CONTENT_DENSITY_DEFAULT;
}

export function resolveContentDensity(temaVisual = {}, storage = null) {
    const ls = storage ?? (typeof window !== 'undefined' ? localStorage : null);

    const modo = normalizeDensityMode(
        ls?.getItem(CONTENT_DENSITY_STORAGE_KEY)
        ?? temaVisual?.densidad_contenido
        ?? CONTENT_DENSITY_DEFAULT
    );

    const maxRemRaw = ls?.getItem(CONTENT_MAX_REM_STORAGE_KEY) ?? temaVisual?.contenido_max_rem ?? CONTENT_MAX_REM_DEFAULT;
    const paddingRemRaw = ls?.getItem(CONTENT_PADDING_REM_STORAGE_KEY) ?? temaVisual?.contenido_padding_rem ?? CONTENT_PADDING_REM_DEFAULT;

    const customMaxRem = clampContentMaxRem(maxRemRaw);
    const customPaddingRem = clampContentPaddingRem(paddingRemRaw);

    if (modo === 'completo') {
        const preset = PRESETS.completo;
        return {
            modo,
            contentMax: '100%',
            paddingRem: preset.paddingRem,
            shellPaddingBlock: preset.shellPaddingBlock,
            shellPaddingInline: preset.shellPaddingInline,
            contenidoMaxRem: null,
            contenidoPaddingRem: preset.paddingRem,
        };
    }

    if (modo === 'personalizado') {
        return {
            modo,
            contentMax: `${customMaxRem}rem`,
            paddingRem: customPaddingRem,
            shellPaddingBlock: `clamp(0.5rem, 2vw, ${Math.max(customPaddingRem, 0.75)}rem)`,
            shellPaddingInline: `clamp(0.375rem, 1.5vw, ${customPaddingRem}rem)`,
            contenidoMaxRem: customMaxRem,
            contenidoPaddingRem: customPaddingRem,
        };
    }

    const preset = PRESETS.compacto;
    return {
        modo: 'compacto',
        contentMax: `${preset.contentMaxRem}rem`,
        paddingRem: preset.paddingRem,
        shellPaddingBlock: preset.shellPaddingBlock,
        shellPaddingInline: preset.shellPaddingInline,
        contenidoMaxRem: preset.contentMaxRem,
        contenidoPaddingRem: preset.paddingRem,
    };
}

export function formatContentMaxLabel(rem) {
    if (rem == null) return '100%';
    return `${Math.round(clampContentMaxRem(rem))} rem`;
}

export function formatContentPaddingLabel(rem) {
    return `${clampContentPaddingRem(rem).toFixed(2).replace(/\.?0+$/, '')} rem`;
}

export function applyContentDensityToRoot(temaVisual = {}) {
    if (typeof document === 'undefined') return resolveContentDensity(temaVisual, null);

    const density = resolveContentDensity(temaVisual);
    const root = document.documentElement;

    root.style.setProperty('--gelia-content-max', density.contentMax);
    root.style.setProperty('--gelia-main-padding-x', `${density.paddingRem}rem`);
    root.style.setProperty('--gelia-page-shell-padding-block', density.shellPaddingBlock);
    root.style.setProperty('--gelia-page-shell-padding-inline', density.shellPaddingInline);

    const shell = document.querySelector('.gelia-app-shell');
    if (shell) {
        shell.dataset.contentDensity = density.modo;
    }

    return density;
}

export function persistContentDensityToStorage({ modo, contenidoMaxRem, contenidoPaddingRem }) {
    if (typeof window === 'undefined') return;

    localStorage.setItem(CONTENT_DENSITY_STORAGE_KEY, normalizeDensityMode(modo));
    if (contenidoMaxRem != null) {
        localStorage.setItem(CONTENT_MAX_REM_STORAGE_KEY, String(clampContentMaxRem(contenidoMaxRem)));
    }
    if (contenidoPaddingRem != null) {
        localStorage.setItem(CONTENT_PADDING_REM_STORAGE_KEY, String(clampContentPaddingRem(contenidoPaddingRem)));
    }
}
