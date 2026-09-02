import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { paramsLimpios } from './resguardosUtils';

export default function useListadoResguardos({
    listadoRoute,
    indexRoute,
    resguardos: resguardosProp,
    metricas: metricasProp,
    bandeja: bandejaProp,
}) {
    const [resguardos, setResguardos] = useState(resguardosProp);
    const [metricas, setMetricas] = useState(metricasProp ?? {});
    const [bandeja, setBandeja] = useState(bandejaProp);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const abortRef = useRef(null);

    useEffect(() => {
        if (resguardosProp !== undefined) setResguardos(resguardosProp);
    }, [resguardosProp]);

    useEffect(() => {
        if (metricasProp !== undefined) setMetricas(metricasProp);
    }, [metricasProp]);

    useEffect(() => {
        if (bandejaProp !== undefined) setBandeja(bandejaProp);
    }, [bandejaProp]);

    const sincronizarUrl = useCallback((params) => {
        const qs = new URLSearchParams(paramsLimpios(params)).toString();
        const base = route(indexRoute);
        window.history.replaceState(window.history.state, '', `${base}${qs ? `?${qs}` : ''}`);
    }, [indexRoute]);

    const cargar = useCallback(async (params, { silencioso = false } = {}) => {
        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;
        if (!silencioso) setCargando(true);
        setError(null);
        try {
            const { data } = await axios.get(route(listadoRoute), {
                params: paramsLimpios(params),
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            if (data.resguardos !== undefined) setResguardos(data.resguardos);
            if (data.metricas !== undefined) setMetricas(data.metricas);
            if (data.bandeja !== undefined) setBandeja(data.bandeja);
            sincronizarUrl(params);
            return data;
        } catch (err) {
            if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return null;
            const status = err?.response?.status;
            if (status === 403) {
                setError('No tienes permiso para consultar estos registros.');
            } else {
                setError('No se pudo cargar el listado. Intenta de nuevo.');
            }
            return null;
        } finally {
            if (abortRef.current === controller) setCargando(false);
        }
    }, [listadoRoute, sincronizarUrl]);

    return { resguardos, metricas, bandeja, cargando, error, cargar };
}
