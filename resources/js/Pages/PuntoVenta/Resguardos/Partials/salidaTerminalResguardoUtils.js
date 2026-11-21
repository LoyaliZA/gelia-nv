export const MAX_RECEPTOR_TERMINAL = 120;
export const MAX_MOTIVO_REGENERACION = 500;

/** Una clave por intento; el servidor la guarda con prefijo en 64 caracteres, por eso no pasa de 55. */
export function claveIdempotenciaSalidaTerminal(resguardoId) {
    const terminal = typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;

    return `pdv:st:${resguardoId}:${terminal}`.slice(0, 55);
}

export function mensajeErrorSalidaTerminal(error) {
    const status = error?.response?.status;
    const data = error?.response?.data;

    if (status === 409) {
        return data?.message || 'Este resguardo cambió desde otra terminal. Actualiza los datos para ver su estado.';
    }

    if (status === 422) {
        if (data?.errors) {
            const primer = Object.values(data.errors).flat()[0];
            if (typeof primer === 'string') return primer;
        }
        if (data?.message) return data.message;
    }

    if (status === 403) {
        return 'Tu usuario no puede registrar la salida de este resguardo. Pide apoyo a gerencia de la sucursal.';
    }

    return 'No pudimos registrar la salida. Revisa la conexión e inténtalo de nuevo; el registro no se duplicará.';
}
