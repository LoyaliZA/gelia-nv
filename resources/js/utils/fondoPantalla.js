/** Valor canónico de fondo que sigue --bg-app (claro/oscuro). */
export const FONDO_SISTEMA = 'none';

const HEX_FONDO_SISTEMA = new Set([
    '#fff',
    '#ffffff',
    '#000',
    '#000000',
    '#0a0a0a',
]);

/**
 * Normaliza fondo_base legado (hex blanco/negro fijo) al fondo del sistema.
 * @param {string|null|undefined} valor
 * @returns {string}
 */
export function normalizeFondoBase(valor) {
    if (!valor || valor === FONDO_SISTEMA) {
        return FONDO_SISTEMA;
    }
    if (typeof valor === 'string' && valor.startsWith('#')) {
        const compact = valor.trim().toLowerCase();
        if (HEX_FONDO_SISTEMA.has(compact)) {
            return FONDO_SISTEMA;
        }
    }
    return valor;
}

/**
 * Aplica fondo de pantalla en :root (--bg-image-pc / --bg-image-movil).
 * @param {HTMLElement} root
 * @param {string|null|undefined} valor
 */
export function applyFondoPantalla(root, valor) {
    const fondo = normalizeFondoBase(valor);
    root.style.removeProperty('--bg-image-pc');
    root.style.removeProperty('--bg-image-movil');

    if (!fondo || fondo === FONDO_SISTEMA) {
        root.style.setProperty('--bg-image-pc', 'none');
        root.style.setProperty('--bg-image-movil', 'none');
    } else if (fondo.startsWith('#')) {
        root.style.setProperty('--bg-image-pc', `linear-gradient(to right, ${fondo}, ${fondo})`);
        root.style.setProperty('--bg-image-movil', `linear-gradient(to right, ${fondo}, ${fondo})`);
    } else if (fondo.startsWith('data:image') || fondo.startsWith('/storage')) {
        root.style.setProperty('--bg-image-pc', `url(${fondo})`);
        root.style.setProperty('--bg-image-movil', `url(${fondo})`);
    } else {
        root.style.setProperty('--bg-image-pc', `url('/assets/backgrounds/${fondo}_pc.svg')`);
        root.style.setProperty('--bg-image-movil', `url('/assets/backgrounds/${fondo}_movil.svg')`);
    }
}

/**
 * Etiqueta legible del tipo de fondo (perfil / auditoría).
 * @param {string|null|undefined} bg
 * @returns {string}
 */
export function etiquetaTipoFondo(bg) {
    const normalizado = normalizeFondoBase(bg);
    if (!normalizado || normalizado === FONDO_SISTEMA) {
        return 'Fondo del sistema';
    }
    if (normalizado.startsWith('#')) {
        return 'Color sólido';
    }
    if (normalizado.startsWith('data:image') || normalizado.startsWith('/storage')) {
        return 'Imagen personalizada';
    }
    return 'Diseño vectorial';
}
