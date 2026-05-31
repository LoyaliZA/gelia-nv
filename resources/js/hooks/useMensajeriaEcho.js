import { useEffect, useRef } from 'react';

export default function useMensajeriaEcho(conversacionId, { onMensajeEnviado, onMensajeLeido, onAdjuntoProcesado }) {
    const onMensajeEnviadoRef = useRef(onMensajeEnviado);
    const onMensajeLeidoRef = useRef(onMensajeLeido);
    const onAdjuntoProcesadoRef = useRef(onAdjuntoProcesado);

    useEffect(() => {
        onMensajeEnviadoRef.current = onMensajeEnviado;
        onMensajeLeidoRef.current = onMensajeLeido;
        onAdjuntoProcesadoRef.current = onAdjuntoProcesado;
    });

    useEffect(() => {
        if (!conversacionId || typeof window === 'undefined' || !window.Echo) return;

        const channelName = `conversacion.${conversacionId}`;
        const channel = window.Echo.private(channelName);

        channel.listen('.mensaje.enviado', (e) => {
            onMensajeEnviadoRef.current?.(e.mensaje);
        });

        channel.listen('.mensaje.leido', (e) => {
            onMensajeLeidoRef.current?.(e.mensaje);
        });

        channel.listen('.adjunto.procesado', (e) => {
            onAdjuntoProcesadoRef.current?.(e.mensaje_id, e.adjunto);
        });

        return () => {
            window.Echo.leave(channelName);
        };
    }, [conversacionId]);
}

export function useMensajeriaEchoGlobal(onMensajeEnviado) {
    const onMensajeEnviadoRef = useRef(onMensajeEnviado);

    useEffect(() => {
        onMensajeEnviadoRef.current = onMensajeEnviado;
    });

    useEffect(() => {
        const handler = (e) => onMensajeEnviadoRef.current?.(e.detail);
        window.addEventListener('mensajeria-mensaje-recibido', handler);
        return () => window.removeEventListener('mensajeria-mensaje-recibido', handler);
    }, []);
}
