import { useEffect, useRef } from 'react';

export default function useMensajeriaEcho(conversacionId, { onMensajeEnviado, onMensajeLeido, onAdjuntoProcesado }) {
    const channelRef = useRef(null);

    useEffect(() => {
        if (!conversacionId || typeof window === 'undefined' || !window.Echo) return;

        const channelName = `conversacion.${conversacionId}`;
        const channel = window.Echo.private(channelName);

        channel.listen('.mensaje.enviado', (e) => {
            onMensajeEnviado?.(e.mensaje);
        });

        channel.listen('.mensaje.leido', (e) => {
            onMensajeLeido?.(e.mensaje);
        });

        channel.listen('.adjunto.procesado', (e) => {
            onAdjuntoProcesado?.(e.mensaje_id, e.adjunto);
        });

        channelRef.current = channel;

        return () => {
            window.Echo.leave(channelName);
            channelRef.current = null;
        };
    }, [conversacionId, onMensajeEnviado, onMensajeLeido, onAdjuntoProcesado]);
}

export function useMensajeriaEchoGlobal(onMensajeEnviado) {
    useEffect(() => {
        const handler = (e) => onMensajeEnviado?.(e.detail);
        window.addEventListener('mensajeria-mensaje-recibido', handler);
        return () => window.removeEventListener('mensajeria-mensaje-recibido', handler);
    }, [onMensajeEnviado]);
}
