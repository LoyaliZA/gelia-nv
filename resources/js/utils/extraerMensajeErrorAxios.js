/**
 * Extrae mensaje legible de errores Axios / Laravel validation.
 */
export function extraerMensajeErrorAxios(error, fallback = 'Ocurrió un error inesperado.') {
    const data = error?.response?.data;

    if (data?.errors && typeof data.errors === 'object') {
        return Object.values(data.errors).flat().join(' ');
    }

    if (typeof data?.message === 'string' && data.message.trim()) {
        return data.message;
    }

    if (error?.response?.status === 419) {
        return 'Sesión expirada. Recarga la página e intenta de nuevo.';
    }

    return fallback;
}
