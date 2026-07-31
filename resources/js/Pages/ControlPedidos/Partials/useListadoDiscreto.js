import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

function paramsLimpios(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    );
}

/**
 * Listado JSON discreto para vistas de Gestión de pedidos (sin visita Inertia).
 */
export default function useListadoDiscreto({
    listadoRoute,
    indexRoute,
    pedidos: pedidosProp,
    metricas: metricasProp,
    clientes: clientesProp,
}) {
    const [pedidos, setPedidos] = useState(pedidosProp);
    const [metricas, setMetricas] = useState(metricasProp ?? {});
    const [clientes, setClientes] = useState(clientesProp);
    const [cargando, setCargando] = useState(false);
    const abortRef = useRef(null);

    useEffect(() => {
        if (pedidosProp !== undefined) setPedidos(pedidosProp);
    }, [pedidosProp]);

    useEffect(() => {
        if (metricasProp !== undefined) setMetricas(metricasProp);
    }, [metricasProp]);

    useEffect(() => {
        if (clientesProp !== undefined) setClientes(clientesProp);
    }, [clientesProp]);

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
        try {
            const { data } = await axios.get(route(listadoRoute), {
                params: paramsLimpios(params),
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            if (data.pedidos !== undefined) setPedidos(data.pedidos);
            if (data.metricas !== undefined) setMetricas(data.metricas);
            if (data.clientes !== undefined) setClientes(data.clientes);
            sincronizarUrl(params);
            return data;
        } catch (err) {
            if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return null;
            return null;
        } finally {
            if (abortRef.current === controller) setCargando(false);
        }
    }, [listadoRoute, sincronizarUrl]);

    return { pedidos, metricas, clientes, cargando, cargar };
}
