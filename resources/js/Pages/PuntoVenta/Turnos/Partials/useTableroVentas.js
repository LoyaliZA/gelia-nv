import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { mensajeErrorOperacionTurno, REFRESCO_TABLERO_MS } from './tableroVentasUtils';

export default function useTableroVentas({
    tablero: tableroProp,
    datosRoute = 'punto_venta.turnos.ventas.datos',
    refrescoMs = REFRESCO_TABLERO_MS,
}) {
    const [tablero, setTablero] = useState(tableroProp ?? null);
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
        if (tableroProp !== undefined) {
            setTablero(tableroProp);
            if (tableroProp?.servidor_at) {
                desplazamientoServidorRef.current = new Date(tableroProp.servidor_at).getTime() - Date.now();
            }
        }
    }, [tableroProp]);

    const ahoraServidor = useCallback(() => {
        return new Date(Date.now() + desplazamientoServidorRef.current).toISOString();
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
            setTablero(data);
            if (data?.servidor_at) {
                desplazamientoServidorRef.current = new Date(data.servidor_at).getTime() - Date.now();
            }
            return data;
        } catch (err) {
            if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return null;
            const status = err?.response?.status;
            if (status === 403) {
                setError('No tienes permiso para consultar el tablero de ventas.');
            } else {
                setError(mensajeErrorOperacionTurno(err, 'consulta del tablero'));
            }
            return null;
        } finally {
            if (abortRef.current === controller) setCargando(false);
        }
    }, [datosRoute]);

    useEffect(() => {
        if (!refrescoMs || refrescoMs < 1000) return undefined;

        const intervalo = setInterval(() => {
            refrescar({ silencioso: true });
        }, refrescoMs);

        return () => clearInterval(intervalo);
    }, [refrescoMs, refrescar]);

    return {
        tablero,
        setTablero,
        cargando,
        error,
        refrescar,
        ahoraServidor,
    };
}
