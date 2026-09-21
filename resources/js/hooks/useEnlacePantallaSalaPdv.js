import { useCallback, useState } from 'react';
import axios from 'axios';

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

    const obtenerEnlace = useCallback(async (sucursalId) => {
        if (!sucursalId) return null;

        setCargando(true);
        setError(null);

        try {
            const { data } = await axios.post(route('punto_venta.pantalla_sala.enlace.obtener'), {
                sucursal_id: sucursalId,
            });
            setEstadoEnlace(data);
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

    const activarEnlace = useCallback(async (sucursalId) => {
        if (!sucursalId) return null;

        setCargando(true);
        setError(null);

        try {
            const { data } = await axios.put(route('punto_venta.pantalla_sala.enlace.activar'), {
                sucursal_id: sucursalId,
            });
            setEstadoEnlace(data);
            return data;
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo activar la pantalla.');
            return null;
        } finally {
            setCargando(false);
        }
    }, []);

    const desactivarEnlace = useCallback(async (sucursalId) => {
        if (!sucursalId) return null;

        setCargando(true);
        setError(null);

        try {
            const { data } = await axios.put(route('punto_venta.pantalla_sala.enlace.desactivar'), {
                sucursal_id: sucursalId,
            });
            setEstadoEnlace(data);
            return data;
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo desactivar la pantalla.');
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
        activarEnlace,
        desactivarEnlace,
        abrirEnNuevaPestana,
    };
}
