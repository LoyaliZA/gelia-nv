import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { usePage } from '@inertiajs/react';
import useMensajeria from '@/hooks/useMensajeria';
import useMensajeriaEcho from '@/hooks/useMensajeriaEcho';
import usePresenciaHeartbeat from '@/hooks/usePresenciaHeartbeat';
import usePresenciaContactos from '@/hooks/usePresenciaContactos';
import {
    setConversacionActivaMensajeria,
    setMensajeriaWidgetAbierto,
} from '@/utils/mensajeriaNotificaciones';
import { MensajeriaContext } from './MensajeriaContext';
import MensajeriaShell from './MensajeriaShell';

function semillaInicial(props) {
    if (Array.isArray(props?.conversacionesIniciales) && props.conversacionesIniciales.length > 0) {
        return props.conversacionesIniciales;
    }
    const resumen = props?.auth?.mensajeria_resumen?.conversaciones;
    return Array.isArray(resumen) ? resumen : [];
}

export default function MensajeriaProvider({ children }) {
    const page = usePage();
    const userId = page.props.auth?.user?.id ?? null;
    const ruta = (page.url || '').split('?')[0];
    const enPaginaMensajeria = ruta === '/mensajeria' || ruta.startsWith('/mensajeria/');
    const semilla = useRef(semillaInicial(page.props));

    const api = useMensajeria(semilla.current, userId);
    const {
        conversacionActiva,
        recibirMensaje,
        actualizarMensaje,
        actualizarAdjunto,
        refrescarConversaciones,
        sincronizarHiloAbierto,
        setConversaciones,
        setConversacionActiva,
    } = api;

    const [widgetAbierto, setWidgetAbierto] = useState(false);

    usePresenciaHeartbeat(Boolean(userId));
    usePresenciaContactos(setConversaciones, setConversacionActiva);

    useEffect(() => {
        if (!userId) return undefined;
        refrescarConversaciones().catch(() => {});
        return undefined;
    }, [userId, refrescarConversaciones]);

    useEffect(() => {
        if (enPaginaMensajeria) setWidgetAbierto(false);
    }, [enPaginaMensajeria]);

    useEffect(() => {
        const visible = enPaginaMensajeria || widgetAbierto;
        setConversacionActivaMensajeria(visible ? conversacionActiva?.id ?? null : null);
        setMensajeriaWidgetAbierto(widgetAbierto && !enPaginaMensajeria);
    }, [enPaginaMensajeria, widgetAbierto, conversacionActiva?.id]);

    useEffect(() => {
        if (!widgetAbierto) return undefined;
        const previo = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previo;
        };
    }, [widgetAbierto]);

    const handleMensajeEnviado = useCallback((mensaje) => {
        if (!mensaje || Number(mensaje.user?.id) === Number(userId)) return;
        recibirMensaje(mensaje);
    }, [recibirMensaje, userId]);

    useMensajeriaEcho(userId ? conversacionActiva?.id : null, {
        onMensajeEnviado: handleMensajeEnviado,
        onMensajeLeido: actualizarMensaje,
        onAdjuntoProcesado: actualizarAdjunto,
    });

    useEffect(() => {
        const conexion = window.Echo?.connector?.pusher?.connection;
        if (!conexion || !userId) return undefined;

        let ignorarInicial = conexion.state !== 'connected';
        const alConectar = () => {
            if (ignorarInicial) {
                ignorarInicial = false;
                return;
            }
            refrescarConversaciones().catch(() => {});
            sincronizarHiloAbierto();
        };

        conexion.bind('connected', alConectar);
        return () => conexion.unbind('connected', alConectar);
    }, [userId, refrescarConversaciones, sincronizarHiloAbierto]);

    useEffect(() => {
        if (!userId) return undefined;
        let timer;
        const onVisible = () => {
            if (document.visibilityState !== 'visible') return;
            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
                refrescarConversaciones().catch(() => {});
                sincronizarHiloAbierto();
            }, 400);
        };
        document.addEventListener('visibilitychange', onVisible);
        return () => {
            window.clearTimeout(timer);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [userId, refrescarConversaciones, sincronizarHiloAbierto]);

    const abrirWidget = useCallback(() => {
        if (!enPaginaMensajeria) setWidgetAbierto(true);
    }, [enPaginaMensajeria]);

    const cerrarWidget = useCallback(() => setWidgetAbierto(false), []);

    return (
        <MensajeriaContext.Provider value={{
            ...api,
            widgetAbierto,
            abrirWidget,
            cerrarWidget,
            enPaginaMensajeria,
        }}>
            {children}
            {widgetAbierto && !enPaginaMensajeria && typeof document !== 'undefined' && createPortal(
                <div className="gelia-mensajeria-portal fixed inset-0 z-[9999] flex justify-end">
                    <button
                        type="button"
                        className="absolute inset-0 bg-black/50 backdrop-blur-sm border-0 p-0 cursor-default"
                        aria-label="Cerrar mensajería"
                        onClick={cerrarWidget}
                    />
                    <div className="gelia-mensajeria-drawer-panel relative h-full theme-surface theme-text-main border-l theme-border shadow-2xl flex flex-col">
                        <MensajeriaShell variant="drawer" onCerrar={cerrarWidget} />
                    </div>
                </div>,
                document.body
            )}
        </MensajeriaContext.Provider>
    );
}
