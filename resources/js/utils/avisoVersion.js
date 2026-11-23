export const FRASES_ACTUALIZACION = [
    'En un momento GELIA queda al día.',
    'Gracias por este instante. Ya casi está.',
    'Esta vista vuelve en cuanto termine.',
    'Lo que estabas haciendo sigue aquí.',
];

export const ESPERA_ACTUALIZACION_MS = 6000;
export const ROTACION_ACTUALIZACION_MS = 1700;
// El destello blanco ocupa el final de la espera; al terminar la pantalla ya es blanca y la recarga no se nota.
export const DESTELLO_ACTUALIZACION_MS = 700;
// Tiempo que el aviso permanece completo antes de plegarse a un costado.
export const ESPERA_AVISO_MS = 12000;
export const CLAVE_RETORNO_ACTUALIZACION = 'gelia-actualizado';

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
    try {
        window.sessionStorage.setItem(CLAVE_RETORNO_ACTUALIZACION, '1');
    } catch {
        // Sin almacenamiento, la vista vuelve sin fundido.
    }
}
