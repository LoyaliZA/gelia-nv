import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';

export default function useDetalleResguardoModal(resguardoId) {
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [resguardo, setResguardo] = useState(null);
    const [timeline, setTimeline] = useState([]);
    const [catalogos, setCatalogos] = useState({});
    const [permisos, setPermisos] = useState({});
    const [almacenes, setAlmacenes] = useState([]);
    const abortRef = useRef(null);

    const reiniciar = useCallback(() => {
        abortRef.current?.abort();
        setCargando(false);
        setError(null);
        setResguardo(null);
        setTimeline([]);
        setCatalogos({});
        setPermisos({});
        setAlmacenes([]);
    }, []);

    const cargar = useCallback(async () => {
        if (!resguardoId) {
            return null;
        }

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setCargando(true);
        setError(null);

        try {
            const { data } = await axios.get(route('punto_venta.resguardos.show', resguardoId), {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            setResguardo(data?.resguardo ?? null);
            setTimeline(Array.isArray(data?.timeline) ? data.timeline : []);
            setCatalogos(data?.catalogos ?? {});
            setPermisos(data?.permisos ?? {});
            setAlmacenes(Array.isArray(data?.almacenes) ? data.almacenes : []);

            return data;
        } catch (err) {
            if (axios.isCancel(err) || err?.code === 'ERR_CANCELED') {
                return null;
            }
            const mensaje = err?.response?.data?.message
                || err?.response?.status === 404
                    ? 'No se encontró el resguardo en tu sucursal activa.'
                    : 'No se pudo cargar el detalle del resguardo.';
            setError(mensaje);
            return null;
        } finally {
            if (!controller.signal.aborted) {
                setCargando(false);
            }
        }
    }, [resguardoId]);

    useEffect(() => () => abortRef.current?.abort(), []);

    return {
        cargar,
        reiniciar,
        recargar: cargar,
        cargando,
        error,
        resguardo,
        timeline,
        catalogos,
        permisos,
        almacenes,
    };
}
