import React, { useCallback, useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import useMensajeria from '@/hooks/useMensajeria';
import useMensajeriaEcho from '@/hooks/useMensajeriaEcho';
import ConversacionLista from '@/Components/Mensajeria/ConversacionLista';
import ChatPanel from '@/Components/Mensajeria/ChatPanel';
import NuevaConversacionModal from '@/Components/Mensajeria/NuevaConversacionModal';
import CrearGrupoModal from '@/Components/Mensajeria/CrearGrupoModal';
import { setConversacionActivaMensajeria } from '@/utils/mensajeriaNotificaciones';

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
        setConversacionActiva,
    } = useMensajeria(conversacionesIniciales, auth.user.id);

    const [modalNuevoChat, setModalNuevoChat] = useState(false);
    const [modalGrupo, setModalGrupo] = useState(false);
    const [vistaMobile, setVistaMobile] = useState('lista');

    useEffect(() => {
        setConversacionActivaMensajeria(conversacionActiva?.id ?? null);
        return () => setConversacionActivaMensajeria(null);
    }, [conversacionActiva?.id]);

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

    return (
        <AppLayout fullScreen>
            <div className="gelia-mensajeria-module flex h-full min-h-0 w-full overflow-hidden theme-text-main">
                <div className={`${vistaMobile === 'lista' ? 'flex' : 'hidden'} sm:flex h-full min-h-0 w-full sm:w-auto shrink-0`}>
                    <ConversacionLista
                        conversaciones={conversaciones}
                        conversacionActiva={conversacionActiva}
                        onSeleccionar={handleSeleccionar}
                        onNuevoChat={() => setModalNuevoChat(true)}
                        onNuevoGrupo={() => setModalGrupo(true)}
                    />
                </div>

                <div className={`${vistaMobile === 'chat' ? 'flex' : 'hidden'} sm:flex flex-1 h-full min-h-0 min-w-0 w-full`}>
                    <ChatPanel
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
        </AppLayout>
    );
}
