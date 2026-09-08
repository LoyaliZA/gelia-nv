import { useCallback, useState } from 'react';
import axios from 'axios';

const CLAVE_URL_LOCAL = 'pdv_pantalla_sala_url';

function claveAlmacenamiento(sucursalId) {
    return `${CLAVE_URL_LOCAL}:${sucursalId}`;
}

export function guardarUrlPantallaSalaLocal(sucursalId, url) {
    if (!sucursalId || !url || typeof window === 'undefined') return;
    try {
        window.localStorage.setItem(claveAlmacenamiento(sucursalId), url);
    } catch {
        // ponytail: almacenamiento local opcional para reabrir sin regenerar
    }
}

export function leerUrlPantallaSalaLocal(sucursalId) {
    if (!sucursalId || typeof window === 'undefined') return null;
    try {
        return window.localStorage.getItem(claveAlmacenamiento(sucursalId));
    } catch {
        return null;
    }
}

export default function useEnlacePantallaSalaPdv() {
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [estadoEnlace, setEstadoEnlace] = useState(null);

    const consultarEstado = useCallback(async (sucursalId) => {
        if (!sucursalId) return null;

        setCargando(true);
        setError(null);

        try {
            const { data } = await axios.get(route('punto_venta.pantalla_sala.enlace.estado'), {
                params: { sucursal_id: sucursalId },
            });
            setEstadoEnlace(data);
            return data;
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo consultar el enlace de la pantalla.');
            return null;
        } finally {
            setCargando(false);
        }
    }, []);

    const obtenerEnlace = useCallback(async (sucursalId, { regenerar = false } = {}) => {
        if (!sucursalId) return null;

        setCargando(true);
        setError(null);

        try {
            const { data } = await axios.post(route('punto_venta.pantalla_sala.enlace.obtener'), {
                sucursal_id: sucursalId,
                regenerar,
            });
            if (data?.url) {
                guardarUrlPantallaSalaLocal(sucursalId, data.url);
            }
            setEstadoEnlace({ ...data, enlace_activo: true });
            return data;
        } catch (err) {
            const mensaje = err?.response?.data?.errors?.enlace?.[0]
                || err?.response?.data?.message
                || 'No se pudo obtener el enlace de la pantalla.';
            setError(mensaje);
            throw err;
        } finally {
            setCargando(false);
        }
    }, []);

    const revocarEnlace = useCallback(async (sucursalId) => {
        if (!sucursalId) return null;

        setCargando(true);
        setError(null);

        try {
            const { data } = await axios.delete(route('punto_venta.pantalla_sala.enlace.revocar'), {
                data: { sucursal_id: sucursalId },
            });
            setEstadoEnlace({ sucursal_id: sucursalId, enlace_activo: false });
            if (typeof window !== 'undefined') {
                window.localStorage.removeItem(claveAlmacenamiento(sucursalId));
            }
            return data;
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo revocar el enlace.');
            return null;
        } finally {
            setCargando(false);
        }
    }, []);

    const abrirEnNuevaPestana = useCallback((url) => {
        if (!url || typeof window === 'undefined') return;
        window.open(url, '_blank', 'noopener,noreferrer');
    }, []);

    return {
        cargando,
        error,
        estadoEnlace,
        consultarEstado,
        obtenerEnlace,
        revocarEnlace,
        abrirEnNuevaPestana,
        leerUrlLocal: leerUrlPantallaSalaLocal,
    };
}
