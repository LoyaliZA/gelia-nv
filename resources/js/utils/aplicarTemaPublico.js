const ACCENT_COLORS = {
    rosa: '#ec4899',
    azul: '#3b82f6',
    verde: '#10b981',
    amarillo: '#f59e0b',
};

/**
 * Aplica tema claro/oscuro en páginas públicas (sin AppLayout).
 * Default oscuro si no hay preferencia guardada.
 */
export function aplicarTemaPublico(isDark) {
    const root = document.documentElement;
    root.classList.toggle('dark', isDark);
    localStorage.setItem('theme', isDark ? 'dark' : 'light');

    const savedColor = localStorage.getItem('theme_color') || 'rosa';
    root.style.setProperty('--color-primario', ACCENT_COLORS[savedColor] || ACCENT_COLORS.rosa);

    const glass = localStorage.getItem('theme_glass');
    if (glass === 'true') root.classList.add('glass-active');
    else root.classList.remove('glass-active');
}

/** true si dark; sin theme guardado → dark por defecto. */
export function leerTemaPublicoInicial() {
    const saved = localStorage.getItem('theme');
    if (saved === 'light') return false;
    return true;
}
