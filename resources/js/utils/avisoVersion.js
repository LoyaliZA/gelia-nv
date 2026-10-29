export const FRASES_ACTUALIZACION = [
    'Tu captura sigue en esta pantalla hasta que confirmes.',
    'En un momento GELIA queda al día.',
    'Gracias por esperar este instante.',
    'La operación sigue al terminar.',
];

export const ESPERA_ACTUALIZACION_MS = 2400;
export const ROTACION_ACTUALIZACION_MS = 1200;

export function decidirAvisoVersion({ versionCargada, versionRemota }) {
    if (!versionCargada || !versionRemota || versionCargada === versionRemota) {
        return 'nada';
    }

    return 'avisar';
}

export function fraseActualizacion(indice) {
    const total = FRASES_ACTUALIZACION.length;
    const normalizado = ((indice % total) + total) % total;

    return FRASES_ACTUALIZACION[normalizado];
}

export function antesDeRecargar() {
    // Fase 2: guardar los borradores activos antes de location.reload().
}
