// Triángulos del isotipo de GELIA (viewBox 100 × 100). Los comparten el logo SVG y el campo 3D de la actualización.
export const PIEZAS_LOGO = [
    ['30,10 70,10 30,30', 1], ['10,30 30,30 10,70', 1], ['30,90 30,70 70,90', 1],
    ['90,70 70,70 90,50', 1], ['70,50 70,70 50,50', 1], ['70,10 70,30 30,30', 0.6],
    ['30,30 30,70 10,70', 0.6], ['30,70 70,70 70,90', 0.6], ['70,70 70,50 90,50', 0.6],
    ['70,10 90,30 70,30', 0.3], ['30,10 30,30 10,30', 0.3], ['10,70 30,70 30,90', 0.3],
    ['70,90 70,70 90,70', 0.3],
].map(([puntos, opacidad]) => {
    const v = puntos.split(' ').map((par) => par.split(',').map(Number));
    const centro = [v.reduce((a, p) => a + p[0], 0) / 3, v.reduce((a, p) => a + p[1], 0) / 3];

    return { puntos, opacidad, vertices: v, centro };
});
