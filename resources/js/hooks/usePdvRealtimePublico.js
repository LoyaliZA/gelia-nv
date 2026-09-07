import { useEffect, useRef, useState } from 'react';
import { mapearEstadoConexionPdv, normalizarEnvelopePdv } from '../utils/pdvAlertQueueUtils';

const canalesPublicosActivos = new Map();

function nombreCanalPublico(sucursalId) {
    return `pdv.turnos.publico.${sucursalId}`;
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

function suscribirCanalPublico(echo, nombreCanal, onEvent) {
    const existente = canalesPublicosActivos.get(nombreCanal);
    if (existente) {
        existente.refCount += 1;
        existente.listeners.add(onEvent);
        return () => liberarCanalPublico(nombreCanal, onEvent);
    }

    const channel = echo.channel(nombreCanal);
    const listeners = new Set([onEvent]);
    const despachar = (envelope) => {
        listeners.forEach((listener) => listener(envelope));
    };
    const dejarEscuchar = escucharTodosEventos(channel, despachar);

    canalesPublicosActivos.set(nombreCanal, {
        channel,
        refCount: 1,
        listeners,
        dejarEscuchar,
    });

    return () => liberarCanalPublico(nombreCanal, onEvent);
}

function liberarCanalPublico(nombreCanal, onEvent) {
    const registro = canalesPublicosActivos.get(nombreCanal);
    if (!registro) return;

    registro.listeners.delete(onEvent);
    registro.refCount -= 1;

    if (registro.refCount > 0 && registro.listeners.size > 0) {
        return;
    }

    registro.dejarEscuchar?.();
    window.Echo?.leave(nombreCanal);
    canalesPublicosActivos.delete(nombreCanal);
}

/**
 * Suscripción al canal público anonimizado de turnos (6A).
 */
export default function usePdvRealtimePublico({
    sucursalId = null,
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
        if (!habilitado || !sucursalId || typeof window === 'undefined' || !window.Echo) {
            return undefined;
        }

        const despachar = (envelope) => {
            onEventRef.current?.(envelope);
        };

        const liberar = suscribirCanalPublico(
            window.Echo,
            nombreCanalPublico(sucursalId),
            despachar,
        );

        return () => liberar();
    }, [habilitado, sucursalId]);

    return { estadoConexion };
}
