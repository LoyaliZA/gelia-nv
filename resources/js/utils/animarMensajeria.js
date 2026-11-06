import { animate } from 'animejs/animation';

export function movimientoReducido() {
    return typeof window !== 'undefined'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

export function animarEntrada(el, { eje = 'y', distancia = 16, duration = 320 } = {}) {
    if (!el || movimientoReducido()) return;
    const propiedad = eje === 'x' ? 'translateX' : 'translateY';
    animate(el, {
        opacity: [0, 1],
        [propiedad]: [distancia, 0],
        duration,
        ease: 'outExpo',
    });
}

export function animarPop(el) {
    if (!el || movimientoReducido()) return;
    animate(el, {
        opacity: [0, 1],
        scale: [0.86, 1],
        duration: 280,
        ease: 'outExpo',
    });
}
