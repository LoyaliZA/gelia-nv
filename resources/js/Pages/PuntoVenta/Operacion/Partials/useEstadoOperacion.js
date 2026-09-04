import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { mensajeErrorOperacion, REFRESCO_OPERACION_MS } from './operacionUtils';

export default function useEstadoOperacion({
    estado: estadoProp,
    datosRoute = 'punto_venta.operacion.datos',
    refrescoMs = REFRESCO_OPERACION_MS,
}) {
    const [estado, setEstado] = useState(estadoProp ?? null);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [, setRelojTick] = useState(0);
    const abortRef = useRef(null);
    const desplazamientoServidorRef = useRef(0);

    useEffect(() => {
        const intervalo = setInterval(() => setRelojTick((valor) => valor + 1), 1000);
        return () => clearInterval(intervalo);
    }, []);

    useEffect(() => {
        if (estadoProp !== undefined) {
            setEstado(estadoProp);
            if (estadoProp?.servidor_at) {
                desplazamientoServidorRef.current = new Date(estadoProp.servidor_at).getTime() - Date.now();
            }
        }
    }, [estadoProp]);

    const ahoraServidor = useCallback(() => {
        return new Date(Date.now() + desplazamientoServidorRef.current).toISOString();
    }, []);

    const aplicarPayload = useCallback((data) => {
        setEstado(data);
        if (data?.servidor_at) {
            desplazamientoServidorRef.current = new Date(data.servidor_at).getTime() - Date.now();
        }
        return data;
    }, []);

    const refrescar = useCallback(async ({ silencioso = false } = {}) => {
        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;
        if (!silencioso) setCargando(true);
        setError(null);

        try {
            const { data } = await axios.get(route(datosRoute), {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            return aplicarPayload(data);
        } catch (err) {
            if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return null;
            const status = err?.response?.status;
            if (status === 403) {
                setError('No tienes permiso para consultar la operación.');
            } else {
                setError(mensajeErrorOperacion(err, 'consulta de operación'));
            }
            return null;
        } finally {
            if (abortRef.current === controller) setCargando(false);
        }
    }, [aplicarPayload, datosRoute]);

    useEffect(() => {
        if (!refrescoMs || refrescoMs < 1000) return undefined;

        const intervalo = setInterval(() => {
            refrescar({ silencioso: true });
        }, refrescoMs);

        return () => clearInterval(intervalo);
    }, [refrescoMs, refrescar]);

    return {
        estado,
        setEstado,
        cargando,
        error,
        refrescar,
        ahoraServidor,
        aplicarPayload,
    };
}
