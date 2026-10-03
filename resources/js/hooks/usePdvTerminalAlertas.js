import { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { crearCoordinadorAudioTerminalPdv } from '@/utils/pdvTerminalAudioLeader';

const STORAGE_KEY = 'pdv_terminal_alertas_id';

const RUTAS_ALERTAS = {
    estado: '/punto-venta/terminal-alertas/estado',
    activar: '/punto-venta/terminal-alertas/activar',
    latido: '/punto-venta/terminal-alertas/latido',
    liberar: '/punto-venta/terminal-alertas/liberar',
};

function esUuidValido(valor) {
    return typeof valor === 'string'
        && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(valor);
}

function generarTerminalId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    // Fallback RFC4122 v4 (navegadores sin crypto.randomUUID).
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const n = c === 'x' ? r : ((r & 0x3) | 0x8);
        return n.toString(16);
    });
}

function leerTerminalId(storageKey) {
    if (typeof window === 'undefined') return null;
    try {
        const existente = window.localStorage.getItem(storageKey);
        if (existente && esUuidValido(existente)) {
            return existente;
        }
        const nuevo = generarTerminalId();
        window.localStorage.setItem(storageKey, nuevo);
        return nuevo;
    } catch {
        return generarTerminalId();
    }
}

function urlTerminal(prefijoRuta, rutasFallback, accion) {
    try {
        const nombre = `${prefijoRuta}.${accion}`;
        if (typeof route === 'function' && route().has?.(nombre)) {
            return route(nombre);
        }
    } catch {
        /* ziggy */
    }
    return rutasFallback[accion];
}

export default function usePdvTerminalAlertas({
    sucursalId = null,
    autorizado = false,
    habilitado = true,
    storageKey = STORAGE_KEY,
    prefijoRuta = 'punto_venta.terminal_alertas',
    rutasFallback = RUTAS_ALERTAS,
    coordinarAudio = true,
} = {}) {
    const terminalIdRef = useRef(leerTerminalId(storageKey));
    const urlTerminalAlertas = useCallback(
        (accion) => urlTerminal(prefijoRuta, rutasFallback, accion),
        [prefijoRuta, rutasFallback],
    );
    const [estado, setEstado] = useState('no_autorizada');
    const [designacion, setDesignacion] = useState(null);
    const [config, setConfig] = useState(null);
    const [catalogo, setCatalogo] = useState(null);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const liderRef = useRef(null);

    const terminalActiva = estado === 'terminal_activa';

    const refrescar = useCallback(async () => {
        if (!habilitado || !sucursalId) return null;

        try {
            const { data } = await axios.get(urlTerminalAlertas('estado'), {
                params: {
                    sucursal_id: sucursalId,
                    terminal_id: terminalIdRef.current,
                },
            });
            setEstado(data?.estado || 'no_autorizada');
            setDesignacion(data?.designacion ?? null);
            setConfig(data?.config ?? null);
            setCatalogo(data?.catalogo ?? null);
            setError(null);
            return data;
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo consultar la terminal de alertas.');
            if (!navigator.onLine) {
                setEstado('conexion_perdida');
            }
            return null;
        }
    }, [habilitado, sucursalId, urlTerminalAlertas]);

    const activar = useCallback(async () => {
        if (!habilitado || !sucursalId || !autorizado) return null;
        setCargando(true);
        setError(null);
        try {
            const { data } = await axios.post(urlTerminalAlertas('activar'), {
                sucursal_id: sucursalId,
                terminal_id: terminalIdRef.current,
            });
            if (data?.terminal_id) {
                terminalIdRef.current = data.terminal_id;
                try {
                    window.localStorage.setItem(storageKey, data.terminal_id);
                } catch {
                    /* ponytail */
                }
            }
            setEstado(data?.estado || 'terminal_activa');
            setDesignacion(data?.designacion ?? data);
            setConfig(data?.config ?? null);
            return data;
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo activar la terminal.');
            await refrescar();
            throw err;
        } finally {
            setCargando(false);
        }
    }, [habilitado, sucursalId, autorizado, refrescar, storageKey, urlTerminalAlertas]);

    const liberar = useCallback(async (motivo = 'manual', sucursalIdObjetivo = null) => {
        const sucursalLiberar = sucursalIdObjetivo ?? sucursalId;
        if (!habilitado || !sucursalLiberar) return null;
        setCargando(true);
        setError(null);
        try {
            const { data } = await axios.delete(urlTerminalAlertas('liberar'), {
                data: {
                    sucursal_id: sucursalLiberar,
                    terminal_id: terminalIdRef.current,
                    motivo,
                },
            });
            if (sucursalLiberar === sucursalId) {
                setEstado(data?.estado || 'disponible');
                setDesignacion(data?.designacion ?? null);
            }
            return data;
        } catch (err) {
            if (sucursalLiberar === sucursalId) {
                setError(err?.response?.data?.message || 'No se pudo liberar la terminal.');
            }
            throw err;
        } finally {
            setCargando(false);
        }
    }, [habilitado, sucursalId, urlTerminalAlertas]);

    const enviarLatido = useCallback(async () => {
        if (!habilitado || !sucursalId || !terminalActiva) return null;
        try {
            const { data } = await axios.put(urlTerminalAlertas('latido'), {
                sucursal_id: sucursalId,
                terminal_id: terminalIdRef.current,
            });
            setEstado(data?.estado || 'terminal_activa');
            setDesignacion(data?.designacion ?? data);
            setError(null);
            return data;
        } catch (err) {
            setError(err?.response?.data?.message || 'Latido de terminal rechazado.');
            await refrescar();
            return null;
        }
    }, [habilitado, sucursalId, terminalActiva, refrescar, urlTerminalAlertas]);

    useEffect(() => {
        if (!habilitado) return undefined;
        refrescar();
        return undefined;
    }, [habilitado, sucursalId, autorizado, refrescar]);

    useEffect(() => {
        if (!habilitado || !terminalActiva || !coordinarAudio) {
            liderRef.current?.destruir();
            liderRef.current = null;
            return undefined;
        }

        liderRef.current?.destruir();
        liderRef.current = crearCoordinadorAudioTerminalPdv({
            terminalId: terminalIdRef.current,
            habilitado: true,
        });

        return () => {
            liderRef.current?.destruir();
            liderRef.current = null;
        };
    }, [habilitado, terminalActiva, coordinarAudio]);

    useEffect(() => {
        if (!habilitado || !terminalActiva) return undefined;

        const segundos = config?.latido_segundos ?? 30;
        const intervalo = window.setInterval(() => {
            enviarLatido();
        }, segundos * 1000);

        return () => window.clearInterval(intervalo);
    }, [habilitado, terminalActiva, config?.latido_segundos, enviarLatido]);

    const terminalActivaRef = useRef(terminalActiva);
    terminalActivaRef.current = terminalActiva;

    const sucursalIdAnteriorRef = useRef(sucursalId);
    const liberarRef = useRef(liberar);
    liberarRef.current = liberar;

    useEffect(() => {
        const sucursalAnterior = sucursalIdAnteriorRef.current;

        if (
            habilitado
            && sucursalAnterior
            && sucursalId
            && sucursalAnterior !== sucursalId
            && terminalActivaRef.current
        ) {
            liberarRef.current('cambio_sucursal', sucursalAnterior).catch(() => {});
        }

        sucursalIdAnteriorRef.current = sucursalId;
    }, [sucursalId, habilitado]);

    return {
        terminalId: terminalIdRef.current,
        estado,
        designacion,
        config,
        catalogo,
        terminalActiva,
        cargando,
        error,
        esLiderAudio: () => liderRef.current?.esLider?.() ?? true,
        activar,
        liberar,
        refrescar,
        enviarLatido,
    };
}
