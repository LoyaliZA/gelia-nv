import React, { useCallback, useEffect, useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import useMensajeria from '@/hooks/useMensajeria';
import useMensajeriaEcho from '@/hooks/useMensajeriaEcho';
import ConversacionLista from '@/Components/Mensajeria/ConversacionLista';
import ChatPanel from '@/Components/Mensajeria/ChatPanel';
import NuevaConversacionModal from '@/Components/Mensajeria/NuevaConversacionModal';
import CrearGrupoModal from '@/Components/Mensajeria/CrearGrupoModal';
import { setConversacionActivaMensajeria } from '@/utils/mensajeriaNotificaciones';
import usePresenciaHeartbeat from '@/hooks/usePresenciaHeartbeat';
import usePresenciaContactos from '@/hooks/usePresenciaContactos';

function leerConversacionDesdeUrl() {
    if (typeof window === 'undefined') return null;
    const params = new URLSearchParams(window.location.search);
    const id = params.get('conversacion');
    return id ? Number(id) : null;
}

function limpiarQueryConversacion() {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    if (!url.searchParams.has('conversacion')) return;
    url.searchParams.delete('conversacion');
    window.history.replaceState({}, '', url.pathname + url.search);
}

export default function Index({ auth, conversacionesIniciales = [] }) {
    const {
        conversaciones,
        conversacionActiva,
        mensajes,
        cargandoMensajes,
        enviando,
        hasMore,
        seleccionarConversacion,
        cargarMasMensajes,
        enviarTexto,
        enviarAdjunto,
        recibirMensaje,
        actualizarMensaje,
        actualizarAdjunto,
        crearConversacion,
        refrescarConversaciones,
        irAMensaje,
        setConversacionActiva,
        setConversaciones,
    } = useMensajeria(conversacionesIniciales, auth.user.id);

    usePresenciaHeartbeat(true);
    usePresenciaContactos(setConversaciones, setConversacionActiva);

    const chatPanelRef = useRef(null);
    const [modalNuevoChat, setModalNuevoChat] = useState(false);
    const [modalGrupo, setModalGrupo] = useState(false);
    const [vistaMobile, setVistaMobile] = useState('lista');

    useEffect(() => {
        setConversacionActivaMensajeria(conversacionActiva?.id ?? null);
        return () => setConversacionActivaMensajeria(null);
    }, [conversacionActiva?.id]);

    const abrirConversacionPorId = useCallback(async (conversacionId, scrollToBottom = false) => {
        let conversacion = conversaciones.find((c) => c.id === conversacionId);

        if (!conversacion) {
            const lista = await refrescarConversaciones();
            conversacion = lista.find((c) => c.id === conversacionId);
        }

        if (!conversacion) return;

        const yaActiva = conversacionActiva?.id === conversacionId;

        if (!yaActiva) {
            await seleccionarConversacion(conversacion);
            setVistaMobile('chat');
        }

        if (scrollToBottom) {
            requestAnimationFrame(() => {
                chatPanelRef.current?.forceScrollToBottom();
            });
        }

        limpiarQueryConversacion();
    }, [conversaciones, conversacionActiva?.id, seleccionarConversacion, refrescarConversaciones]);

    useEffect(() => {
        const conversacionId = leerConversacionDesdeUrl();
        if (conversacionId) {
            abrirConversacionPorId(conversacionId, true);
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        const handler = (event) => {
            const { conversacionId, scrollToBottom } = event.detail ?? {};
            if (conversacionId) {
                abrirConversacionPorId(conversacionId, scrollToBottom);
            }
        };
        window.addEventListener('mensajeria-abrir-conversacion', handler);
        return () => window.removeEventListener('mensajeria-abrir-conversacion', handler);
    }, [abrirConversacionPorId]);

    const handleMensajeEnviado = useCallback((mensaje) => {
        if (mensaje.user?.id !== auth.user.id) {
            recibirMensaje(mensaje);
        }
    }, [auth.user.id, recibirMensaje]);

    const handleMensajeLeido = useCallback((mensaje) => {
        actualizarMensaje(mensaje);
    }, [actualizarMensaje]);

    const handleAdjuntoProcesado = useCallback((mensajeId, adjunto) => {
        actualizarAdjunto(mensajeId, adjunto);
    }, [actualizarAdjunto]);

    useMensajeriaEcho(conversacionActiva?.id, {
        onMensajeEnviado: handleMensajeEnviado,
        onMensajeLeido: handleMensajeLeido,
        onAdjuntoProcesado: handleAdjuntoProcesado,
    });

    const handleSeleccionar = async (conversacion) => {
        await seleccionarConversacion(conversacion);
        setVistaMobile('chat');
    };

    const handleVolver = () => {
        setVistaMobile('lista');
        setConversacionActiva(null);
    };

    const scrollAMensaje = useCallback((mensajeId) => {
        requestAnimationFrame(() => {
            const el = document.getElementById(`mensaje-${mensajeId}`);
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el?.classList.add('gelia-mensaje--destacado');
            setTimeout(() => el?.classList.remove('gelia-mensaje--destacado'), 2400);
        });
    }, []);

    const handleIrAMensajeGlobal = useCallback(async (conversacionId, mensajeId) => {
        const res = await irAMensaje(conversacionId, mensajeId);
        if (!res) return;
        setVistaMobile('chat');
        scrollAMensaje(mensajeId);
    }, [irAMensaje, scrollAMensaje]);

    const handleSeleccionarDesdeBusqueda = useCallback(async (conversacionId) => {
        const conversacion = conversaciones.find((c) => c.id === conversacionId);
        if (conversacion) {
            await handleSeleccionar(conversacion);
        }
    }, [conversaciones, handleSeleccionar]);

    const handleIrAMensajeEnChat = useCallback(async (_conversacionId, mensajeId) => {
        const res = await irAMensaje(_conversacionId, mensajeId);
        if (res) scrollAMensaje(mensajeId);
    }, [irAMensaje, scrollAMensaje]);

    return (
        <AppLayout fullScreen>
            <div className="gelia-mensajeria-page">
                <div className="gelia-mensajeria-module w-full">
                <div className={`${vistaMobile === 'lista' ? 'flex' : 'hidden'} sm:flex h-full min-h-0 w-full sm:w-auto shrink-0`}>
                    <ConversacionLista
                        conversaciones={conversaciones}
                        conversacionActiva={conversacionActiva}
                        onSeleccionar={handleSeleccionar}
                        onNuevoChat={() => setModalNuevoChat(true)}
                        onNuevoGrupo={() => setModalGrupo(true)}
                        onSeleccionarConversacion={handleSeleccionarDesdeBusqueda}
                        onIrAMensaje={handleIrAMensajeGlobal}
                    />
                </div>

                <div className={`gelia-mensajeria-chat-pane ${vistaMobile === 'chat' ? 'flex' : 'hidden'} sm:flex flex-1 h-full min-h-0 min-w-0 w-full`}>
                    <ChatPanel
                        ref={chatPanelRef}
                        conversacion={conversacionActiva}
                        mensajes={mensajes}
                        cargandoMensajes={cargandoMensajes}
                        hasMore={hasMore}
                        enviando={enviando}
                        onEnviarTexto={enviarTexto}
                        onEnviarAdjunto={enviarAdjunto}
                        onCargarMas={cargarMasMensajes}
                        onVolver={handleVolver}
                        mostrarVolver
                        onIrAMensaje={handleIrAMensajeEnChat}
                    />
                </div>
                </div>
            </div>

            <NuevaConversacionModal
                isOpen={modalNuevoChat}
                onClose={() => setModalNuevoChat(false)}
                onCrear={crearConversacion}
            />

            <CrearGrupoModal
                isOpen={modalGrupo}
                onClose={() => setModalGrupo(false)}
                onCrear={crearConversacion}
            />
        </AppLayout>
    );
}
