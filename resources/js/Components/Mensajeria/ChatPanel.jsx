import React, { forwardRef, useCallback, useEffect, useImperativeHandle, useMemo, useRef, useState } from 'react';
import { ArrowLeft, FolderOpen, Loader2, Users } from 'lucide-react';
import BuscadorInteligente from './BuscadorInteligente';
import { usePage } from '@inertiajs/react';
import MensajeBurbuja from './MensajeBurbuja';
import MensajeInput from './MensajeInput';
import AvatarUsuario from './AvatarUsuario';
import ParticipantesGrupoPanel from './ParticipantesGrupoPanel';
import ChatDrivePanel from './ChatDrivePanel';
import MensajeriaNavMenuButton from './MensajeriaNavMenuButton';
import SelectorEstadoPresencia from './SelectorEstadoPresencia';
import EstadoPresenciaTexto from './EstadoPresenciaTexto';
import ZonaDropAdjuntoChat from './ZonaDropAdjuntoChat';
import useChatScroll from '@/hooks/useChatScroll';
import { prepararMensajesGrupo } from '@/utils/mensajeriaGrupo';

const ChatPanel = forwardRef(function ChatPanel({
    conversacion,
    mensajes,
    cargandoMensajes,
    hasMore,
    enviando,
    onEnviarTexto,
    onEnviarAdjunto,
    onCargarMas,
    onVolver,
    mostrarVolver = false,
    onIrAMensaje,
}, ref) {
    const { auth } = usePage().props;
    const topSentinelRef = useRef(null);
    const prevScrollHeight = useRef(0);
    const [participantesAbierto, setParticipantesAbierto] = useState(false);
    const [driveAbierto, setDriveAbierto] = useState(false);
    const [respondiendoA, setRespondiendoA] = useState(null);

    const { scrollRef, forceScrollToBottom } = useChatScroll(mensajes, conversacion?.id);

    useImperativeHandle(ref, () => ({
        forceScrollToBottom,
    }), [forceScrollToBottom]);

    useEffect(() => {
        const sentinel = topSentinelRef.current;
        if (!sentinel) return;

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMore && !cargandoMensajes) {
                    prevScrollHeight.current = scrollRef.current?.scrollHeight || 0;
                    onCargarMas?.();
                }
            },
            { root: scrollRef.current, threshold: 0.1 }
        );

        observer.observe(sentinel);
        return () => observer.disconnect();
    }, [hasMore, cargandoMensajes, onCargarMas, scrollRef]);

    useEffect(() => {
        if (prevScrollHeight.current && scrollRef.current) {
            const diff = scrollRef.current.scrollHeight - prevScrollHeight.current;
            scrollRef.current.scrollTop = diff;
            prevScrollHeight.current = 0;
        }
    }, [mensajes.length, scrollRef]);

    useEffect(() => {
        setDriveAbierto(false);
        setParticipantesAbierto(false);
        setRespondiendoA(null);
    }, [conversacion?.id]);

    const scrollAMensaje = useCallback((mensajeId) => {
        requestAnimationFrame(() => {
            const el = document.getElementById(`mensaje-${mensajeId}`);
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el?.classList.add('gelia-mensaje--destacado');
            setTimeout(() => el?.classList.remove('gelia-mensaje--destacado'), 2400);
        });
    }, []);

    const handleIrACitacion = useCallback(async (mensajeId) => {
        if (document.getElementById(`mensaje-${mensajeId}`)) {
            scrollAMensaje(mensajeId);
            return;
        }
        if (onIrAMensaje && conversacion?.id) {
            await onIrAMensaje(conversacion.id, mensajeId);
            scrollAMensaje(mensajeId);
        }
    }, [conversacion?.id, onIrAMensaje, scrollAMensaje]);

    const handleResponder = useCallback((mensaje) => {
        setRespondiendoA(mensaje);
    }, []);

    const handleEnviarTextoConRespuesta = useCallback(async (texto) => {
        await onEnviarTexto?.(texto, respondiendoA?.id ?? null);
        setRespondiendoA(null);
    }, [onEnviarTexto, respondiendoA?.id]);

    const handleEnviarAdjuntoConRespuesta = useCallback(async (file, tipo, contenido) => {
        await onEnviarAdjunto?.(file, tipo, contenido, respondiendoA?.id ?? null);
        setRespondiendoA(null);
    }, [onEnviarAdjunto, respondiendoA?.id]);

    const esGrupo = conversacion?.tipo === 'grupo';
    const mensajesRender = useMemo(
        () => prepararMensajesGrupo(mensajes, esGrupo),
        [mensajes, esGrupo]
    );

    if (!conversacion) {
        return (
            <div className="gelia-mensajeria-chat-pane flex-1 flex flex-col items-center justify-center theme-text-main px-6 text-center">
                <div className="p-5 rounded-[2rem] theme-element border theme-border">
                    <AvatarUsuario className="w-16 h-16" iconClassName="w-8 h-8" />
                </div>
                <p className="text-sm font-black uppercase italic theme-text-main mt-5 m-0">Selecciona un chat_</p>
                <p className="text-[11px] font-bold mt-2 m-0 theme-text-muted uppercase tracking-widest">O inicia una nueva conversación</p>
            </div>
        );
    }

    return (
        <div className="gelia-mensajeria-chat-pane flex-1 flex flex-col min-w-0 h-full theme-text-main">
            <div className="gelia-mensajeria-chat-layout relative flex flex-1 min-h-0 min-w-0 overflow-hidden">
            <ZonaDropAdjuntoChat
                onEnviarAdjunto={handleEnviarAdjuntoConRespuesta}
                enviando={enviando}
                className={`gelia-mensajeria-chat-column flex flex-1 flex-col min-w-0 min-h-0 ${driveAbierto ? 'hidden sm:flex' : 'flex'}`}
            >
            {({ prepararAdjunto }) => (
            <>
            <div className="gelia-mensajeria-chat-header border-b theme-border shrink-0">
                <div className="flex items-center gap-3 px-4 py-3 sm:px-5">
                <MensajeriaNavMenuButton className="!p-1.5" />
                {mostrarVolver && (
                    <button type="button" onClick={onVolver} className="p-1.5 rounded-full theme-element theme-text-main sm:hidden">
                        <ArrowLeft className="w-5 h-5" />
                    </button>
                )}

                <AvatarUsuario
                    foto={conversacion.foto}
                    nombre={conversacion.nombre}
                    esGrupo={esGrupo}
                    className="w-10 h-10 shrink-0"
                    iconClassName="w-5 h-5"
                />

                <div className="min-w-0 flex-1 sm:max-w-[10rem] lg:max-w-[14rem]">
                    <p className="text-sm font-black truncate theme-text-main m-0">{conversacion.nombre}</p>
                    {!esGrupo && conversacion.presencia_otro && (
                        <EstadoPresenciaTexto presencia={conversacion.presencia_otro} compact className="mt-0.5" />
                    )}
                    {esGrupo && (
                        <button
                            type="button"
                            onClick={() => setParticipantesAbierto(true)}
                            className="text-[10px] font-bold theme-text-muted hover:theme-text-main transition-colors text-left truncate max-w-full m-0 mt-0.5 p-0 border-0 bg-transparent outline-none"
                            title="Ver participantes del grupo"
                        >
                            {conversacion.participantes?.length || 0} participantes · Ver lista
                        </button>
                    )}
                </div>

                <div className="hidden sm:block shrink-0">
                    <SelectorEstadoPresencia compact />
                </div>

                <div className="hidden md:block flex-1 min-w-0 max-w-xl mx-1">
                    <BuscadorInteligente
                        conversacionId={conversacion.id}
                        placeholder="Buscar en este chat…"
                        onIrAMensaje={onIrAMensaje}
                        className="w-full"
                    />
                </div>

                <button
                    type="button"
                    onClick={() => {
                        setDriveAbierto((v) => !v);
                        setParticipantesAbierto(false);
                    }}
                    className={`p-2.5 rounded-full theme-element border theme-border transition-all outline-none shrink-0 ${
                        driveAbierto
                            ? 'border-[var(--color-primario)] theme-text-main'
                            : 'theme-text-muted hover:border-[var(--color-primario)] hover:theme-text-main'
                    }`}
                    title="Archivos del chat"
                    aria-label="Ver archivos compartidos"
                    aria-pressed={driveAbierto}
                >
                    <FolderOpen className="w-5 h-5" />
                </button>

                {esGrupo && (
                    <button
                        type="button"
                        onClick={() => {
                            setParticipantesAbierto(true);
                            setDriveAbierto(false);
                        }}
                        className="p-2.5 rounded-full theme-element border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:theme-text-main transition-all outline-none shrink-0"
                        title="Ver participantes"
                        aria-label="Ver participantes del grupo"
                    >
                        <Users className="w-5 h-5" />
                    </button>
                )}
                </div>

                <div className="md:hidden px-4 pb-3 border-t theme-border/50">
                    <BuscadorInteligente
                        conversacionId={conversacion.id}
                        placeholder="Buscar en este chat…"
                        onIrAMensaje={onIrAMensaje}
                    />
                </div>
            </div>

            <ParticipantesGrupoPanel
                isOpen={participantesAbierto && esGrupo}
                onClose={() => setParticipantesAbierto(false)}
                nombreGrupo={conversacion.nombre}
                participantes={conversacion.participantes || []}
                usuarioActualId={auth?.user?.id}
            />

            <div className="relative flex-1 min-h-0 min-w-0 overflow-hidden">
                <div
                    ref={scrollRef}
                    className="gelia-mensajeria-mensajes-scroll absolute inset-0 py-4 bg-black/[0.02] dark:bg-white/[0.02]"
                >
                    <div ref={topSentinelRef} className="h-1" />

                    {cargandoMensajes && hasMore && (
                        <div className="flex justify-center py-2">
                            <Loader2 className="w-5 h-5 animate-spin opacity-50" />
                        </div>
                    )}

                    {cargandoMensajes && mensajes.length === 0 && (
                        <div className="flex justify-center py-12">
                            <Loader2 className="w-6 h-6 animate-spin opacity-50" />
                        </div>
                    )}

                    {mensajesRender.map(({ mensaje, mostrarRemitente }) => (
                        <MensajeBurbuja
                            key={mensaje.id}
                            mensaje={mensaje}
                            esGrupo={esGrupo}
                            mostrarRemitente={mostrarRemitente}
                            participantesGrupo={esGrupo ? conversacion.participantes || [] : []}
                            onResponder={handleResponder}
                            onIrAMensaje={handleIrACitacion}
                        />
                    ))}
                </div>
            </div>

            <MensajeInput
                onEnviarTexto={handleEnviarTextoConRespuesta}
                onEnviarAdjunto={handleEnviarAdjuntoConRespuesta}
                onPrepararAdjunto={prepararAdjunto}
                respondiendoA={respondiendoA}
                onCancelarRespuesta={() => setRespondiendoA(null)}
                enviando={enviando}
            />
            </>
            )}
            </ZonaDropAdjuntoChat>

            <ChatDrivePanel
                isOpen={driveAbierto}
                onClose={() => setDriveAbierto(false)}
                conversacionId={conversacion.id}
                participantes={conversacion.participantes || []}
            />
            </div>
        </div>
    );
});

export default ChatPanel;
