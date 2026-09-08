import { useEffect, useRef, useState } from 'react';
import { mapearEstadoConexionPdv, normalizarEnvelopePdv } from '../utils/pdvAlertQueueUtils';

const canalesActivos = new Map();

function nombreCanalSucursal(sucursalId) {
    return `pdv.sucursal.${sucursalId}`;
}

function nombreCanalUsuario(userId) {
    return `pdv.usuario.${userId}`;
}

function escucharTodosEventos(channel, onEvent) {
    const subscription = channel?.subscription;
    if (!subscription?.bind_global) {
        return () => {};
    }

    const handler = (eventName, data) => {
        const nombre = String(eventName || '');
        if (!nombre || nombre.startsWith('pusher:') || nombre.startsWith('client-')) {
            return;
        }
        const envelope = normalizarEnvelopePdv(data);
        if (envelope) onEvent(envelope);
    };

    subscription.bind_global(handler);
    return () => subscription.unbind_global(handler);
}

function suscribirCanal(echo, nombreCanal, onEvent) {
    const existente = canalesActivos.get(nombreCanal);
    if (existente) {
        existente.refCount += 1;
        existente.listeners.add(onEvent);
        return () => liberarCanal(nombreCanal, onEvent);
    }

    const channel = echo.private(nombreCanal);
    const listeners = new Set([onEvent]);
    const despachar = (envelope) => {
        listeners.forEach((listener) => listener(envelope));
    };
    const dejarEscuchar = escucharTodosEventos(channel, despachar);

    canalesActivos.set(nombreCanal, {
        channel,
        refCount: 1,
        listeners,
        dejarEscuchar,
    });

    return () => liberarCanal(nombreCanal, onEvent);
}

function liberarCanal(nombreCanal, onEvent) {
    const registro = canalesActivos.get(nombreCanal);
    if (!registro) return;

    registro.listeners.delete(onEvent);
    registro.refCount -= 1;

    if (registro.refCount > 0 && registro.listeners.size > 0) {
        return;
    }

    registro.dejarEscuchar?.();
    window.Echo?.leave(nombreCanal);
    canalesActivos.delete(nombreCanal);
}

/**
 * Suscripción realtime PDV centralizada (un canal compartido por nombre).
 */
export default function usePdvRealtime({
    sucursalId = null,
    userId = null,
    habilitado = true,
    onEvent,
}) {
    const onEventRef = useRef(onEvent);
    const [estadoConexion, setEstadoConexion] = useState('desconectado');

    useEffect(() => {
        onEventRef.current = onEvent;
    });

    useEffect(() => {
        if (!habilitado || typeof window === 'undefined' || !window.Echo) {
            setEstadoConexion('desconectado');
            return undefined;
        }

        const pusher = window.Echo.connector?.pusher;
        if (!pusher?.connection) {
            setEstadoConexion('desconectado');
            return undefined;
        }

        const onStateChange = (states) => {
            setEstadoConexion(mapearEstadoConexionPdv(states?.current));
        };

        pusher.connection.bind('state_change', onStateChange);
        setEstadoConexion(mapearEstadoConexionPdv(pusher.connection.state));

        return () => {
            pusher.connection.unbind('state_change', onStateChange);
        };
    }, [habilitado]);

    useEffect(() => {
        if (!habilitado || typeof window === 'undefined' || !window.Echo) {
            return undefined;
        }

        const despachar = (envelope) => {
            onEventRef.current?.(envelope);
        };

        const liberadores = [];

        if (sucursalId) {
            liberadores.push(suscribirCanal(window.Echo, nombreCanalSucursal(sucursalId), despachar));
        }
        if (userId) {
            liberadores.push(suscribirCanal(window.Echo, nombreCanalUsuario(userId), despachar));
        }

        return () => {
            liberadores.forEach((liberar) => liberar());
        };
    }, [habilitado, sucursalId, userId]);

    return { estadoConexion };
}

export { canalesActivos as __canalesActivosPdvParaPruebas };
