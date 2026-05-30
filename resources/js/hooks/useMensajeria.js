import { useCallback, useEffect, useRef, useState } from 'react';
import MensajeriaService from '@/Services/MensajeriaService';
import { normalizarMensajeParaViewer } from '@/utils/mensajeriaMensaje';

export default function useMensajeria(conversacionesIniciales = [], viewerUserId = null) {
    const [conversaciones, setConversaciones] = useState(conversacionesIniciales);
    const [conversacionActiva, setConversacionActiva] = useState(null);
    const [mensajes, setMensajes] = useState([]);
    const [cargandoMensajes, setCargandoMensajes] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [cursor, setCursor] = useState(null);
    const [hasMore, setHasMore] = useState(false);
    const mensajesRef = useRef([]);

    useEffect(() => {
        mensajesRef.current = mensajes;
    }, [mensajes]);

    const actualizarConversacionEnLista = useCallback((conversacionId, cambios) => {
        setConversaciones((prev) => {
            const idx = prev.findIndex((c) => c.id === conversacionId);
            if (idx === -1) return prev;
            const actualizada = { ...prev[idx], ...cambios };
            const resto = prev.filter((c) => c.id !== conversacionId);
            return [actualizada, ...resto].sort(
                (a, b) => new Date(b.ultimo_mensaje_at || 0) - new Date(a.ultimo_mensaje_at || 0)
            );
        });
    }, []);

    const seleccionarConversacion = useCallback(async (conversacion) => {
        setConversacionActiva(conversacion);
        setMensajes([]);
        setCursor(null);
        setHasMore(false);
        setCargandoMensajes(true);

        try {
            const data = await MensajeriaService.listarMensajes(conversacion.id);
            setMensajes(data.mensajes);
            setCursor(data.next_cursor);
            setHasMore(data.has_more);
            await MensajeriaService.marcarLeida(conversacion.id);
            actualizarConversacionEnLista(conversacion.id, { unread_count: 0 });
        } finally {
            setCargandoMensajes(false);
        }
    }, [actualizarConversacionEnLista]);

    const cargarMasMensajes = useCallback(async () => {
        if (!conversacionActiva || !hasMore || !cursor || cargandoMensajes) return;

        setCargandoMensajes(true);
        try {
            const data = await MensajeriaService.listarMensajes(conversacionActiva.id, cursor);
            setMensajes((prev) => [...data.mensajes, ...prev]);
            setCursor(data.next_cursor);
            setHasMore(data.has_more);
        } finally {
            setCargandoMensajes(false);
        }
    }, [conversacionActiva, hasMore, cursor, cargandoMensajes]);

    const enviarTexto = useCallback(async (contenido) => {
        if (!conversacionActiva || !contenido.trim()) return null;

        setEnviando(true);
        try {
            const mensaje = await MensajeriaService.enviarMensaje(conversacionActiva.id, contenido.trim());
            setMensajes((prev) => [...prev, mensaje]);
            actualizarConversacionEnLista(conversacionActiva.id, {
                ultimo_mensaje_preview: contenido.trim().slice(0, 200),
                ultimo_mensaje_at: mensaje.created_at,
            });
            return mensaje;
        } finally {
            setEnviando(false);
        }
    }, [conversacionActiva, actualizarConversacionEnLista]);

    const enviarAdjunto = useCallback(async (file, tipo, contenido = null) => {
        if (!conversacionActiva) return null;

        setEnviando(true);
        try {
            const mensaje = await MensajeriaService.enviarAdjunto(
                conversacionActiva.id, file, tipo, contenido
            );
            setMensajes((prev) => [...prev, mensaje]);
            actualizarConversacionEnLista(conversacionActiva.id, {
                ultimo_mensaje_preview: mensaje.contenido || `[${tipo}]`,
                ultimo_mensaje_at: mensaje.created_at,
            });
            return mensaje;
        } finally {
            setEnviando(false);
        }
    }, [conversacionActiva, actualizarConversacionEnLista]);

    const recibirMensaje = useCallback((mensaje) => {
        if (!mensaje) return;

        const normalizado = normalizarMensajeParaViewer(mensaje, viewerUserId);
        const activaId = conversacionActiva?.id;

        if (activaId === normalizado.conversacion_id) {
            setMensajes((prev) => {
                if (prev.some((m) => m.id === normalizado.id)) return prev;
                return [...prev, normalizado];
            });
            MensajeriaService.marcarLeida(mensaje.conversacion_id);
        }

        actualizarConversacionEnLista(normalizado.conversacion_id, {
            ultimo_mensaje_preview: normalizado.contenido || `[${normalizado.tipo}]`,
            ultimo_mensaje_at: normalizado.created_at,
            unread_count: activaId === normalizado.conversacion_id
                ? 0
                : (conversaciones.find((c) => c.id === normalizado.conversacion_id)?.unread_count || 0) + 1,
        });
    }, [conversacionActiva, conversaciones, actualizarConversacionEnLista, viewerUserId]);

    useEffect(() => {
        const handler = (event) => {
            const mensaje = event.detail;
            if (mensaje) recibirMensaje(mensaje);
        };
        window.addEventListener('mensajeria-mensaje-recibido', handler);
        return () => window.removeEventListener('mensajeria-mensaje-recibido', handler);
    }, [recibirMensaje]);

    const actualizarMensaje = useCallback((mensajeActualizado) => {
        const normalizado = normalizarMensajeParaViewer(mensajeActualizado, viewerUserId);
        setMensajes((prev) =>
            prev.map((m) => (m.id === normalizado.id ? normalizado : m))
        );
    }, [viewerUserId]);

    const actualizarAdjunto = useCallback((mensajeId, adjunto) => {
        setMensajes((prev) =>
            prev.map((m) => {
                if (m.id !== mensajeId) return m;
                return {
                    ...m,
                    adjuntos: m.adjuntos.map((a) =>
                        a.id === adjunto.id ? { ...a, ...adjunto } : a
                    ),
                };
            })
        );
    }, []);

    const crearConversacion = useCallback(async (payload) => {
        const conversacion = await MensajeriaService.crearConversacion(payload);
        setConversaciones((prev) => {
            if (prev.some((c) => c.id === conversacion.id)) return prev;
            return [conversacion, ...prev];
        });
        await seleccionarConversacion(conversacion);
        return conversacion;
    }, [seleccionarConversacion]);

    const refrescarConversaciones = useCallback(async () => {
        const lista = await MensajeriaService.listarConversaciones();
        setConversaciones(lista);
    }, []);

    const totalUnread = conversaciones.reduce((sum, c) => sum + (c.unread_count || 0), 0);

    return {
        conversaciones,
        conversacionActiva,
        mensajes,
        cargandoMensajes,
        enviando,
        hasMore,
        totalUnread,
        seleccionarConversacion,
        cargarMasMensajes,
        enviarTexto,
        enviarAdjunto,
        recibirMensaje,
        actualizarMensaje,
        actualizarAdjunto,
        crearConversacion,
        refrescarConversaciones,
        setConversacionActiva,
    };
}
