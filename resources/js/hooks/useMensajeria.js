import { useCallback, useEffect, useRef, useState } from 'react';
import MensajeriaService from '@/Services/MensajeriaService';
import {
    normalizarMensajeParaViewer,
    aplicarActualizacionLectura,
    aplicarCursorLectura,
    conversacionConMensajeNuevo,
    fusionarMensajesHilo,
    esMismoUsuario,
} from '@/utils/mensajeriaMensaje';

export default function useMensajeria(conversacionesIniciales = [], viewerUserId = null) {
    const [conversaciones, setConversaciones] = useState(conversacionesIniciales);
    const [conversacionActiva, setConversacionActiva] = useState(null);
    const [mensajes, setMensajes] = useState([]);
    const [cargandoMensajes, setCargandoMensajes] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [cursor, setCursor] = useState(null);
    const [hasMore, setHasMore] = useState(false);
    const mensajesRef = useRef([]);
    const conversacionesRef = useRef(conversacionesIniciales);
    const conversacionActivaRef = useRef(null);
    const procesadosRef = useRef(new Set());

    useEffect(() => {
        mensajesRef.current = mensajes;
    }, [mensajes]);

    useEffect(() => {
        conversacionesRef.current = conversaciones;
    }, [conversaciones]);

    useEffect(() => {
        conversacionActivaRef.current = conversacionActiva;
    }, [conversacionActiva]);

    const recordarMensaje = useCallback((mensajeId) => {
        if (!mensajeId) return;
        procesadosRef.current.add(mensajeId);
        if (procesadosRef.current.size > 400) {
            const primero = procesadosRef.current.values().next().value;
            procesadosRef.current.delete(primero);
        }
    }, []);

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

    const fijarConversacionActiva = useCallback((valor) => {
        setConversacionActiva((prev) => {
            const siguiente = typeof valor === 'function' ? valor(prev) : valor;
            conversacionActivaRef.current = siguiente;
            return siguiente;
        });
    }, []);

    const seleccionarConversacion = useCallback(async (conversacion) => {
        fijarConversacionActiva(conversacion);
        setMensajes([]);
        setCursor(null);
        setHasMore(false);
        setCargandoMensajes(true);

        try {
            const data = await MensajeriaService.listarMensajes(conversacion.id);
            setMensajes(data.mensajes);
            setCursor(data.next_cursor);
            setHasMore(data.has_more);
            (data.mensajes || []).forEach((m) => recordarMensaje(m.id));
            await MensajeriaService.marcarLeida(conversacion.id);
            actualizarConversacionEnLista(conversacion.id, { unread_count: 0 });
        } finally {
            setCargandoMensajes(false);
        }
    }, [actualizarConversacionEnLista, fijarConversacionActiva, recordarMensaje]);

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

    const enviarTexto = useCallback(async (contenido, replyToId = null) => {
        if (!conversacionActiva || !contenido.trim()) return null;

        setEnviando(true);
        try {
            const mensaje = await MensajeriaService.enviarMensaje(
                conversacionActiva.id,
                contenido.trim(),
                replyToId
            );
            recordarMensaje(mensaje?.id);
            setMensajes((prev) => [...prev, mensaje]);
            actualizarConversacionEnLista(conversacionActiva.id, {
                ultimo_mensaje_preview: contenido.trim().slice(0, 200),
                ultimo_mensaje_at: mensaje.created_at,
            });
            return mensaje;
        } finally {
            setEnviando(false);
        }
    }, [conversacionActiva, actualizarConversacionEnLista, recordarMensaje]);

    const enviarAdjunto = useCallback(async (file, tipo, contenido = null, replyToId = null) => {
        if (!conversacionActiva) return null;

        setEnviando(true);
        try {
            const mensaje = await MensajeriaService.enviarAdjunto(
                conversacionActiva.id,
                file,
                tipo,
                contenido,
                replyToId
            );
            recordarMensaje(mensaje?.id);
            setMensajes((prev) => [...prev, mensaje]);
            actualizarConversacionEnLista(conversacionActiva.id, {
                ultimo_mensaje_preview: mensaje.contenido || `[${tipo}]`,
                ultimo_mensaje_at: mensaje.created_at,
            });
            return mensaje;
        } finally {
            setEnviando(false);
        }
    }, [conversacionActiva, actualizarConversacionEnLista, recordarMensaje]);

    const recibirMensaje = useCallback((mensaje) => {
        if (!mensaje?.id || procesadosRef.current.has(mensaje.id)) return;
        recordarMensaje(mensaje.id);

        const normalizado = normalizarMensajeParaViewer(mensaje, viewerUserId);
        const activaId = conversacionActivaRef.current?.id;
        const id = normalizado.conversacion_id;
        const chatAbierto = activaId === id;

        if (chatAbierto) {
            setMensajes((prev) => {
                if (prev.some((m) => m.id === normalizado.id)) return prev;
                return [...prev, normalizado];
            });
            MensajeriaService.marcarLeida(id);
        }

        const existe = conversacionesRef.current.some((c) => c.id === id);
        if (!existe) {
            MensajeriaService.listarConversaciones()
                .then((lista) => setConversaciones(lista))
                .catch(() => {});
            return;
        }

        setConversaciones((prev) => {
            const idx = prev.findIndex((c) => c.id === id);
            if (idx === -1) return prev;
            const actualizada = conversacionConMensajeNuevo(prev[idx], normalizado, chatAbierto);
            const resto = prev.filter((c) => c.id !== id);
            return [actualizada, ...resto].sort(
                (a, b) => new Date(b.ultimo_mensaje_at || 0) - new Date(a.ultimo_mensaje_at || 0)
            );
        });
    }, [recordarMensaje, viewerUserId]);

    useEffect(() => {
        const handler = (event) => {
            const mensaje = event.detail;
            if (mensaje) recibirMensaje(mensaje);
        };
        window.addEventListener('mensajeria-mensaje-recibido', handler);
        return () => window.removeEventListener('mensajeria-mensaje-recibido', handler);
    }, [recibirMensaje]);

    const actualizarMensaje = useCallback((mensajeActualizado) => {
        if (!mensajeActualizado || !esMismoUsuario(mensajeActualizado.user?.id, viewerUserId)) {
            return;
        }

        setMensajes((prev) =>
            prev.map((m) =>
                m.id === mensajeActualizado.id
                    ? aplicarActualizacionLectura(m, mensajeActualizado, viewerUserId)
                    : m
            )
        );
    }, [viewerUserId]);

    useEffect(() => {
        const handler = (event) => {
            const mensaje = event.detail;
            if (mensaje) actualizarMensaje(mensaje);
        };
        window.addEventListener('mensajeria-mensaje-leido', handler);
        return () => window.removeEventListener('mensajeria-mensaje-leido', handler);
    }, [actualizarMensaje]);

    const aplicarCursor = useCallback((detalle) => {
        const resultado = aplicarCursorLectura({
            conversaciones: conversacionesRef.current,
            mensajes: mensajesRef.current,
            conversacionActivaId: conversacionActivaRef.current?.id,
            detalle,
        });
        setConversaciones(resultado.conversaciones);
        setMensajes(resultado.mensajes);
    }, []);

    useEffect(() => {
        const handler = (event) => aplicarCursor(event.detail);
        window.addEventListener('mensajeria-conversacion-leida', handler);
        return () => window.removeEventListener('mensajeria-conversacion-leida', handler);
    }, [aplicarCursor]);

    const sincronizarHiloAbierto = useCallback(async () => {
        const id = conversacionActivaRef.current?.id;
        if (!id) return;

        try {
            const data = await MensajeriaService.listarMensajes(id);
            setMensajes((prev) => fusionarMensajesHilo(prev, data.mensajes));
            (data.mensajes || []).forEach((m) => recordarMensaje(m.id));
        } catch {
            // La red de seguridad no debe interrumpir el chat abierto.
        }
    }, [recordarMensaje]);

    const marcarConversacionVisibleLeida = useCallback(async () => {
        if (!conversacionActiva) return;
        await MensajeriaService.marcarLeida(conversacionActiva.id);
    }, [conversacionActiva]);

    useEffect(() => {
        if (!conversacionActiva) return undefined;

        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                marcarConversacionVisibleLeida();
            }
        };

        document.addEventListener('visibilitychange', onVisible);

        return () => document.removeEventListener('visibilitychange', onVisible);
    }, [conversacionActiva?.id, marcarConversacionVisibleLeida]);

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
        fijarConversacionActiva((prev) => {
            if (!prev) return prev;
            return lista.find((c) => c.id === prev.id) || prev;
        });
        return lista;
    }, [fijarConversacionActiva]);

    const irAMensaje = useCallback(async (conversacionId, mensajeId) => {
        let conversacion = conversaciones.find((c) => c.id === conversacionId);

        if (!conversacion) {
            const lista = await refrescarConversaciones();
            conversacion = lista.find((c) => c.id === conversacionId);
        }

        if (!conversacion) return null;

        const cambioConversacion = conversacionActiva?.id !== conversacionId;

        if (cambioConversacion) {
            fijarConversacionActiva(conversacion);
            setMensajes([]);
            setCursor(null);
            setHasMore(false);
            setCargandoMensajes(true);
        }

        const yaCargado = !cambioConversacion
            && mensajesRef.current.some((m) => m.id === mensajeId);

        try {
            if (!yaCargado) {
                const data = await MensajeriaService.cargarContextoMensaje(conversacionId, mensajeId);
                setMensajes(data.mensajes);
                setCursor(data.next_cursor);
                setHasMore(data.has_more);
            }

            await MensajeriaService.marcarLeida(conversacionId);
            actualizarConversacionEnLista(conversacionId, { unread_count: 0 });
        } finally {
            setCargandoMensajes(false);
        }

        return { conversacion, mensajeId };
    }, [conversaciones, conversacionActiva?.id, refrescarConversaciones, actualizarConversacionEnLista, fijarConversacionActiva]);

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
        sincronizarHiloAbierto,
        irAMensaje,
        setConversacionActiva: fijarConversacionActiva,
        setConversaciones,
    };
}
