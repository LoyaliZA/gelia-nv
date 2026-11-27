export const ROTACIONES_PUBLICIDAD = [0, 90, 180, 270];

export function normalizarRotacionPublicidad(valor) {
    const grados = Number(valor);

    return ROTACIONES_PUBLICIDAD.includes(grados) ? grados : 0;
}

export function girarRotacionPublicidad(actual, sentido) {
    const actualNorm = normalizarRotacionPublicidad(actual);
    const paso = sentido === 'izquierda' ? -90 : 90;

    return (actualNorm + paso + 360) % 360;
}

export function rotacionParaPantalla(item, superficie) {
    if (superficie === 'kiosko') {
        return normalizarRotacionPublicidad(item?.rotacion_kiosko ?? item?.rotacion);
    }

    return normalizarRotacionPublicidad(item?.rotacion);
}

export function estiloCapaRotada(rotacion, anchoPadre, altoPadre) {
    const grados = normalizarRotacionPublicidad(rotacion);
    const ancho = Number(anchoPadre) || 0;
    const alto = Number(altoPadre) || 0;

    if (grados === 0) {
        return {
            position: 'absolute',
            inset: 0,
            width: '100%',
            height: '100%',
        };
    }

    if (!ancho || !alto) {
        return {
            position: 'absolute',
            inset: 0,
            width: '100%',
            height: '100%',
            transform: `rotate(${grados}deg)`,
        };
    }

    const swap = grados === 90 || grados === 270;

    return {
        position: 'absolute',
        top: '50%',
        left: '50%',
        width: swap ? alto : ancho,
        height: swap ? ancho : alto,
        transform: `translate(-50%, -50%) rotate(${grados}deg)`,
    };
}

export function estiloEscenarioKiosko(disposicion, viewportAncho, viewportAlto) {
    const modo = disposicion === 'vertical' ? 'vertical' : 'auto';
    const ancho = Number(viewportAncho) || 0;
    const alto = Number(viewportAlto) || 0;

    if (modo !== 'vertical' || alto >= ancho) {
        return {
            position: 'relative',
            width: '100%',
            height: '100%',
        };
    }

    return {
        position: 'absolute',
        top: 0,
        left: '100%',
        width: '100dvh',
        height: '100dvw',
        transform: 'rotate(90deg)',
        transformOrigin: 'top left',
    };
}

export function urlKioskoConDisposicion(url, disposicion) {
    if (!url) return null;
    if (disposicion !== 'vertical') return url;

    try {
        const parsed = new URL(url);
        parsed.searchParams.set('disposicion', 'vertical');

        return parsed.toString();
    } catch {
        const union = url.includes('?') ? '&' : '?';

        return `${url}${union}disposicion=vertical`;
    }
}
