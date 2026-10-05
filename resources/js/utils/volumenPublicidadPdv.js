/** Volumen de sala por defecto (0–100). */
export const VOLUMEN_PUBLICIDAD_SALA_DEFECTO = 30;

/** Curva perceptual: más diferencia audible en la mitad baja del control. */
export const VOLUMEN_PUBLICIDAD_CURVA = 1.75;

export function normalizarVolumenPublicidadPct(valor, defecto = VOLUMEN_PUBLICIDAD_SALA_DEFECTO) {
    const n = Number(valor);
    if (!Number.isFinite(n)) {
        return defecto;
    }
    if (n > 0 && n <= 1) {
        return Math.min(100, Math.max(0, Math.round(n * 100)));
    }

    return Math.min(100, Math.max(0, Math.round(n)));
}

/**
 * Convierte porcentajes de sala y pieza al volumen HTMLMediaElement (0–1).
 * @param {number|null|undefined} salaPct
 * @param {number|null|undefined} piezaPct null/undefined = 100 % relativo a sala
 */
export function volumenAudioVideoPublicidad(salaPct, piezaPct = null) {
    const sala = normalizarVolumenPublicidadPct(salaPct, VOLUMEN_PUBLICIDAD_SALA_DEFECTO) / 100;
    const pieza = piezaPct === null || piezaPct === undefined || piezaPct === ''
        ? 1
        : normalizarVolumenPublicidadPct(piezaPct, 100) / 100;
    const linear = sala * pieza;
    const curved = linear ** VOLUMEN_PUBLICIDAD_CURVA;

    return Math.min(1, Math.max(0, curved));
}
