import React, { useCallback, useEffect, useRef, useState } from 'react';
import ConversacionLista from '@/Components/Mensajeria/ConversacionLista';
import ChatPanel from '@/Components/Mensajeria/ChatPanel';
import NuevaConversacionModal from '@/Components/Mensajeria/NuevaConversacionModal';
import CrearGrupoModal from '@/Components/Mensajeria/CrearGrupoModal';
import { useMensajeriaContext } from '@/Components/Mensajeria/MensajeriaContext';
import { animarEntrada } from '@/utils/animarMensajeria';

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

export default function MensajeriaShell({ variant = 'page', onCerrar }) {
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
        enviarSticker,
        crearConversacion,
        refrescarConversaciones,
        irAMensaje,
        setConversacionActiva,
    } = useMensajeriaContext();

    const chatPanelRef = useRef(null);
    const paneChatRef = useRef(null);
    const [modalNuevoChat, setModalNuevoChat] = useState(false);
    const [modalGrupo, setModalGrupo] = useState(false);
    const [vistaMobile, setVistaMobile] = useState('lista');
    const enDrawer = variant === 'drawer';

    const abrirConversacionPorId = useCallback(async (conversacionId, scrollToBottom = false) => {
        let conversacion = conversaciones.find((c) => c.id === conversacionId);

        if (!conversacion) {
            const lista = await refrescarConversaciones();
            conversacion = lista.find((c) => c.id === conversacionId);
        }

        if (!conversacion) return;

        if (conversacionActiva?.id !== conversacionId) {
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
        if (variant !== 'page') return undefined;
        const conversacionId = leerConversacionDesdeUrl();
        if (conversacionId) {
            abrirConversacionPorId(conversacionId, true);
        }
        return undefined;
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        if (variant !== 'page') return undefined;
        const handler = (event) => {
            const { conversacionId, scrollToBottom } = event.detail ?? {};
            if (conversacionId) {
                abrirConversacionPorId(conversacionId, scrollToBottom);
            }
        };
        window.addEventListener('mensajeria-abrir-conversacion', handler);
        return () => window.removeEventListener('mensajeria-abrir-conversacion', handler);
    }, [variant, abrirConversacionPorId]);

    useEffect(() => {
        if (vistaMobile !== 'chat') return undefined;
        const escritorio = window.matchMedia(enDrawer ? '(min-width: 768px)' : '(min-width: 640px)').matches;
        if (escritorio) return undefined;
        animarEntrada(paneChatRef.current, { eje: 'x', distancia: 28, duration: 340 });
        return undefined;
    }, [vistaMobile, enDrawer, conversacionActiva?.id]);

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
        } else {
            await abrirConversacionPorId(conversacionId, false);
        }
    }, [conversaciones, abrirConversacionPorId]); // eslint-disable-line react-hooks/exhaustive-deps

    const handleIrAMensajeEnChat = useCallback(async (conversacionId, mensajeId) => {
        const res = await irAMensaje(conversacionId, mensajeId);
        if (res) scrollAMensaje(mensajeId);
    }, [irAMensaje, scrollAMensaje]);

    return (
        <>
            <div className={`gelia-mensajeria-module w-full h-full min-h-0 ${enDrawer ? 'gelia-mensajeria-module--drawer' : ''}`}>
                <div className={`${vistaMobile === 'lista' ? 'flex' : 'hidden'} ${enDrawer ? 'md:flex w-full md:w-auto' : 'sm:flex w-full sm:w-auto'} h-full min-h-0 shrink-0`}>
                    <ConversacionLista
                        conversaciones={conversaciones}
                        conversacionActiva={conversacionActiva}
                        onSeleccionar={handleSeleccionar}
                        onNuevoChat={() => setModalNuevoChat(true)}
                        onNuevoGrupo={() => setModalGrupo(true)}
                        onSeleccionarConversacion={handleSeleccionarDesdeBusqueda}
                        onIrAMensaje={handleIrAMensajeGlobal}
                        onCerrar={enDrawer ? onCerrar : undefined}
                        compacta={enDrawer}
                    />
                </div>

                <div ref={paneChatRef} className={`gelia-mensajeria-chat-pane ${vistaMobile === 'chat' ? 'flex' : 'hidden'} ${enDrawer ? 'md:flex' : 'sm:flex'} flex-1 h-full min-h-0 min-w-0 w-full`}>
                    <ChatPanel
                        ref={chatPanelRef}
                        conversacion={conversacionActiva}
                        mensajes={mensajes}
                        cargandoMensajes={cargandoMensajes}
                        hasMore={hasMore}
                        enviando={enviando}
                        onEnviarTexto={enviarTexto}
                        onEnviarAdjunto={enviarAdjunto}
                        onEnviarSticker={enviarSticker}
                        onCargarMas={cargarMasMensajes}
                        onVolver={handleVolver}
                        mostrarVolver
                        volverBreakpoint={enDrawer ? 'md' : 'sm'}
                        onIrAMensaje={handleIrAMensajeEnChat}
                    />
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
        </>
    );
}
