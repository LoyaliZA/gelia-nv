/** Claves efímeras de preview; no borrar preferencias persistentes al cerrar sesión. */
export function clearEphemeralThemeKeys() {
    const keys = [
        'gelia:theme-preview',
        'theme-layout-preview',
        'theme-mobile-layout-preview',
        'theme-sidebar-mode-preview',
        'theme-fixed-position-preview',
    ];
    keys.forEach((key) => {
        try {
            localStorage.removeItem(key);
        } catch {
            /* ignore */
        }
    });
    try {
        Object.keys(localStorage)
            .filter((k) => k.startsWith('gelia:user:') && k.endsWith(':theme-preview'))
            .forEach((k) => localStorage.removeItem(k));
    } catch {
        /* ignore */
    }
}
