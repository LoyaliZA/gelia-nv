import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { mensajeErrorBandejaRecepcion, REFRESCO_BANDEJA_MS } from './bandejaRecepcionUtils';

export default function useBandejaRecepcionTurno({
    bandeja: bandejaProp,
    datosRoute = 'punto_venta.turnos.recepcion.datos',
    refrescoMs = REFRESCO_BANDEJA_MS,
    habilitado = true,
}) {
    const [bandeja, setBandeja] = useState(bandejaProp ?? null);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const abortRef = useRef(null);

    useEffect(() => {
        if (bandejaProp !== undefined) {
            setBandeja(bandejaProp);
        }
    }, [bandejaProp]);

    const refrescar = useCallback(async ({ silencioso = false } = {}) => {
        if (!habilitado) return null;

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
            setBandeja(data);
            return data;
        } catch (err) {
            if (err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') return null;
            setError(mensajeErrorBandejaRecepcion(err));
            return null;
        } finally {
            if (abortRef.current === controller) setCargando(false);
        }
    }, [datosRoute, habilitado]);

    useEffect(() => {
        if (!habilitado || !refrescoMs || refrescoMs < 1000) return undefined;

        const intervalo = setInterval(() => {
            refrescar({ silencioso: true });
        }, refrescoMs);

        return () => clearInterval(intervalo);
    }, [habilitado, refrescoMs, refrescar]);

    return {
        bandeja,
        setBandeja,
        cargando,
        error,
        refrescar,
    };
}
