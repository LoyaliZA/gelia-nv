import getStroke from 'perfect-freehand';

export const FIRMA_STROKE_COLOR = '#1e3a8a';

export const FIRMA_PEN_OPTIONS = {
    size: 8,
    thinning: 0.65,
    smoothing: 0.5,
    streamline: 0.45,
    simulatePressure: true,
    easing: (t) => t,
    start: { taper: 4, cap: true },
    end: { taper: 4, cap: true },
};

const promedio = (a, b) => (a + b) / 2;

export function pathSvgDesdeTrazo(puntos) {
    const len = puntos.length;
    if (len < 4) return '';

    let a = puntos[0];
    let b = puntos[1];
    const c = puntos[2];

    let result = `M${a[0].toFixed(2)},${a[1].toFixed(2)} Q${b[0].toFixed(2)},${b[1].toFixed(2)} ${promedio(b[0], c[0]).toFixed(2)},${promedio(b[1], c[1]).toFixed(2)} T`;

    for (let i = 2, max = len - 1; i < max; i++) {
        a = puntos[i];
        b = puntos[i + 1];
        result += `${promedio(a[0], b[0]).toFixed(2)},${promedio(a[1], b[1]).toFixed(2)} `;
    }

    result += 'Z';
    return result;
}

export function dibujarTrazoPluma(context, puntos, color = FIRMA_STROKE_COLOR) {
    if (!puntos || puntos.length < 2) return;

    const contorno = getStroke(puntos, FIRMA_PEN_OPTIONS);
    const pathData = pathSvgDesdeTrazo(contorno);
    if (!pathData) return;

    const path = new Path2D(pathData);
    context.fillStyle = color;
    context.fill(path);
}
