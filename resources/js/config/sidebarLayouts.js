/** Catálogo compartido de estilos de sidebar (preferencias + temas admin). */
export const SIDEBAR_LAYOUT_OPTIONS = [
    {
        id: 'professional_left',
        label: 'Profesional',
        description: 'Barra fija a la izquierda con navegación empresarial expandible.',
        preview: 'pro',
    },
    {
        id: 'fixed',
        label: 'Fijo',
        description: 'Barra fija con rail compacto y panel de accesos (estilo actual).',
        preview: 'fixed',
    },
    {
        id: 'floating_left',
        label: 'Flotante izquierda',
        description: 'Widget flotante en la esquina inferior izquierda.',
        preview: 'float-left',
    },
    {
        id: 'floating_right',
        label: 'Flotante derecha',
        description: 'Widget flotante en la esquina inferior derecha.',
        preview: 'float-right',
    },
];

export const SIDEBAR_LAYOUT_IDS = SIDEBAR_LAYOUT_OPTIONS.map((o) => o.id);

/** Default de sistema: sidebar profesional (migración 2026_08_25). */
export const DEFAULT_SIDEBAR_LAYOUT = 'professional_left';
export const DEFAULT_SIDEBAR_MOBILE_LAYOUT = 'mobile_topbar';

/** One-shot: alinea localStorage al default pro sin pisar elecciones posteriores. */
export const SIDEBAR_PRO_DEFAULT_MIGRATION_KEY = 'gelia:sidebar_pro_default_v1';

export function isProfessionalSidebarLayout(layout) {
    return layout === 'professional_left';
}

export function isKnownSidebarLayout(layout) {
    return SIDEBAR_LAYOUT_IDS.includes(layout);
}

/**
 * Primera visita post-deploy: fuerza theme_layout / mobile al default pro.
 * Si el flag ya existe, no toca preferencias (el usuario puede volver a flotante/fijo).
 */
export function ensureProfessionalSidebarDefaultOnce(storage = typeof window !== 'undefined' ? window.localStorage : null) {
    if (!storage) return false;
    try {
        if (storage.getItem(SIDEBAR_PRO_DEFAULT_MIGRATION_KEY) === '1') return false;
        storage.setItem('theme_layout', DEFAULT_SIDEBAR_LAYOUT);
        storage.setItem('theme_layout_mobile', DEFAULT_SIDEBAR_MOBILE_LAYOUT);
        storage.setItem(SIDEBAR_PRO_DEFAULT_MIGRATION_KEY, '1');
        return true;
    } catch {
        return false;
    }
}

/** local → auth → default; invalida ids desconocidos. */
export function resolveSidebarLayout(localValue, authValue) {
    const candidate = localValue || authValue || DEFAULT_SIDEBAR_LAYOUT;
    return isKnownSidebarLayout(candidate) ? candidate : DEFAULT_SIDEBAR_LAYOUT;
}
