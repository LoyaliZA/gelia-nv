export const FRASES_ACTUALIZACION = [
    'En un momento GELIA queda al día.',
    'Gracias por este instante. Ya casi está.',
    'Esta vista vuelve en cuanto termine.',
    'Lo que estabas haciendo sigue aquí.',
];

export const ESPERA_ACTUALIZACION_MS = 4800;
export const ROTACION_ACTUALIZACION_MS = 1600;

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
