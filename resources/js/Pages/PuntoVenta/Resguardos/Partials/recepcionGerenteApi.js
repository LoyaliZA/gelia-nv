import axios from 'axios';
import { claveIdempotenciaRecepcion, limpiarClaveIdempotenciaRecepcion, mensajeErrorRecepcion } from './recepcionFisicaUtils';

export async function confirmarRecepcionGerente(resguardo) {
    const resguardoId = resguardo?.id;
    const clave = claveIdempotenciaRecepcion(resguardoId);

    try {
        const { data } = await axios.put(
            route('punto_venta.resguardos.recepcion', resguardoId),
            {
                version: resguardo.version,
                idempotency_key: clave,
            },
            { headers: { Accept: 'application/json' } },
        );

        limpiarClaveIdempotenciaRecepcion(resguardoId);

        return { ok: true, resguardo: data?.resguardo };
    } catch (error) {
        return {
            ok: false,
            error: mensajeErrorRecepcion(error),
            status: error?.response?.status,
        };
    }
}

export async function pasarARecepcionGerente(resguardo) {
    const resguardoId = resguardo?.id;

    try {
        const { data } = await axios.put(
            route('punto_venta.resguardos.pasar_recepcion', resguardoId),
            {
                version: resguardo.version,
                idempotency_key: `pdv:pasar:${resguardoId}:${Date.now()}`,
            },
            { headers: { Accept: 'application/json' } },
        );

        return { ok: true, resguardo: data?.resguardo };
    } catch (error) {
        const mensaje = error?.response?.data?.message
            || Object.values(error?.response?.data?.errors || {})?.flat?.()?.[0]
            || 'No se pudo pasar el resguardo a recepción.';

        return { ok: false, error: mensaje, status: error?.response?.status };
    }
}

export function resguardoSeleccionableGerente(resguardo) {
    return resguardo?.estado === 'pendiente_recepcion' || resguardo?.estado === 'recibido';
}
