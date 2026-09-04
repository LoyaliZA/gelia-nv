/** Contrato de aceptación móvil para recepción física PDV (REM-06). */
export const MOBILE_VIEWPORT_WIDTH = 375;
export const MOBILE_VIEWPORT_HEIGHT = 667;
export const MIN_TOUCH_TARGET_PX = 44;

const TOUCH_TARGET_CLASS_RE = /\bmin-h-\[(?:4[4-9]|[5-9]\d|\d{3,})px\]/;
const HORIZONTAL_OVERFLOW_CLASS_RE = /\b(?:overflow-x-(?:auto|scroll)|min-w-(?:\[(?!0\])[^\]]+\]|\[[1-9]\d*px\]|max|fit|min|full))/;

export function configurarViewportMovil() {
    globalThis.innerWidth = MOBILE_VIEWPORT_WIDTH;
    globalThis.innerHeight = MOBILE_VIEWPORT_HEIGHT;
}

export function elementoTieneTargetTactil(elemento) {
    if (!elemento) return false;
    const clases = String(elemento.className || '');
    if (TOUCH_TARGET_CLASS_RE.test(clases)) return true;

    const minHeight = Number.parseInt(globalThis.getComputedStyle?.(elemento)?.minHeight || '', 10);
    return Number.isFinite(minHeight) && minHeight >= MIN_TOUCH_TARGET_PX;
}

export function elementoEsOperable(elemento) {
    if (!elemento) return false;
    if (elemento.disabled) return false;
    if (elemento.getAttribute('aria-hidden') === 'true') return false;

    const estilo = globalThis.getComputedStyle?.(elemento);
    if (!estilo) return true;

    return estilo.display !== 'none'
        && estilo.visibility !== 'hidden'
        && Number.parseFloat(estilo.opacity || '1') > 0;
}

export function assertTargetTactil(elemento, descripcion) {
    if (!elemento || !elementoTieneTargetTactil(elemento)) {
        throw new Error(`Acción crítica sin target táctil ≥${MIN_TOUCH_TARGET_PX}px: ${descripcion}`);
    }
}

export function assertSinOverflowHorizontal(contenedor) {
    const raiz = contenedor?.closest?.('[data-recepcion-movil-root]') || contenedor;
    if (!raiz) return;

    const anchoContenido = Math.max(raiz.scrollWidth || 0, raiz.offsetWidth || 0);
    if (anchoContenido > MOBILE_VIEWPORT_WIDTH + 1) {
        throw new Error(
            `Scroll horizontal en viewport móvil: ${anchoContenido}px > ${MOBILE_VIEWPORT_WIDTH}px`,
        );
    }

    for (const elemento of raiz.querySelectorAll('*')) {
        const clases = String(elemento.className || '');
        if (HORIZONTAL_OVERFLOW_CLASS_RE.test(clases) && !/\boverflow-x-hidden\b/.test(clases)) {
            throw new Error(`Clase de overflow horizontal no permitida en recepción móvil: ${clases}`);
        }
    }
}

export function buscarControlPorTexto(contenedor, texto, selector = 'button, label, a, [role="button"]') {
    return [...contenedor.querySelectorAll(selector)].find((elemento) => (
        elemento.textContent?.replace(/\s+/g, ' ').trim().includes(texto)
    ));
}

export function buscarPorAriaLabel(contenedor, etiqueta) {
    return contenedor.querySelector(`[aria-label="${etiqueta}"]`);
}
