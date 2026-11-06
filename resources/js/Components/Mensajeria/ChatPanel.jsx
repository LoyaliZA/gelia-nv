import React, { Fragment, forwardRef, useCallback, useEffect, useImperativeHandle, useMemo, useRef, useState } from 'react';
import { ArrowLeft, FolderOpen, Loader2, Users } from 'lucide-react';
import BuscadorInteligente from './BuscadorInteligente';
import { usePage } from '@inertiajs/react';
import MensajeBurbuja from './MensajeBurbuja';
import MensajeInput from './MensajeInput';
import AvatarUsuario from './AvatarUsuario';
import ParticipantesGrupoPanel from './ParticipantesGrupoPanel';
import ChatDrivePanel from './ChatDrivePanel';
import MensajeriaNavMenuButton from './MensajeriaNavMenuButton';
import BotonIconoMensajeria from './BotonIconoMensajeria';
import EstadoPresenciaTexto from './EstadoPresenciaTexto';
import ZonaDropAdjuntoChat from './ZonaDropAdjuntoChat';
import useChatScroll from '@/hooks/useChatScroll';
import { prepararMensajesGrupo } from '@/utils/mensajeriaGrupo';
import { animarEntrada, animarPop } from '@/utils/animarMensajeria';

function etiquetaDia(fecha) {
    if (!fecha) return '';
    const dia = new Date(fecha);
    if (Number.isNaN(dia.getTime())) return '';
    const hoy = new Date();
    const ayer = new Date();
    ayer.setDate(hoy.getDate() - 1);
    if (dia.toDateString() === hoy.toDateString()) return 'Hoy';
    if (dia.toDateString() === ayer.toDateString()) return 'Ayer';
    return dia.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
}

const ChatPanel = forwardRef(function ChatPanel({
    conversacion,
    mensajes,
    cargandoMensajes,
    hasMore,
    enviando,
    onEnviarTexto,
    onEnviarAdjunto,
    onEnviarSticker,
    onCargarMas,
    onVolver,
    mostrarVolver = false,
    volverBreakpoint = 'sm',
    onIrAMensaje,
}, ref) {
    const { auth } = usePage().props;
    const topSentinelRef = useRef(null);
    const prevScrollHeight = useRef(0);
    const [participantesAbierto, setParticipantesAbierto] = useState(false);
    const [driveAbierto, setDriveAbierto] = useState(false);
    const [respondiendoA, setRespondiendoA] = useState(null);
    const ultimoAnimado = useRef(null);

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
        ultimoAnimado.current = null;
    }, [conversacion?.id]);

    useEffect(() => {
        const ultimo = mensajes[mensajes.length - 1];
        if (!ultimo?.id) return;
        if (ultimoAnimado.current === null) {
            ultimoAnimado.current = ultimo.id;
            return;
        }
        if (ultimoAnimado.current === ultimo.id) return;
        ultimoAnimado.current = ultimo.id;
        const el = document.getElementById(`mensaje-${ultimo.id}`);
        if (ultimo.tipo === 'sticker') animarPop(el);
        else animarEntrada(el, { eje: 'y', distancia: 10, duration: 260 });
    }, [mensajes]);

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

    const handleEnviarSticker = useCallback(async (stickerId) => {
        await onEnviarSticker?.(stickerId, respondiendoA?.id ?? null);
        setRespondiendoA(null);
    }, [onEnviarSticker, respondiendoA?.id]);

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
                <p className="text-base font-semibold theme-text-main mt-5 m-0">Elige una conversación</p>
                <p className="text-sm mt-2 m-0 theme-text-muted">O inicia un chat nuevo.</p>
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
                    <BotonIconoMensajeria
                        icon={ArrowLeft}
                        label="Volver a los mensajes"
                        onClick={onVolver}
                        className={`rounded-full theme-element theme-text-main ${volverBreakpoint === 'md' ? 'md:hidden' : 'sm:hidden'}`}
                    />
                )}

                <AvatarUsuario
                    foto={conversacion.foto}
                    nombre={conversacion.nombre}
                    esGrupo={esGrupo}
                    enLinea={!esGrupo && conversacion.presencia_otro?.en_linea === true}
                    className="w-10 h-10 shrink-0"
                    iconClassName="w-5 h-5"
                />

                <div className="min-w-0 flex-1 sm:max-w-[16rem] lg:max-w-xs">
                    <p className="text-sm font-semibold truncate theme-text-main m-0">{conversacion.nombre}</p>
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

                <div className="hidden md:block flex-1 min-w-0 max-w-xl mx-1">
                    <BuscadorInteligente
                        conversacionId={conversacion.id}
                        placeholder="Buscar en este chat…"
                        onIrAMensaje={onIrAMensaje}
                        className="w-full"
                    />
                </div>

                <BotonIconoMensajeria
                    icon={FolderOpen}
                    label="Ver archivos compartidos"
                    pressed={driveAbierto}
                    onClick={() => {
                        setDriveAbierto((v) => !v);
                        setParticipantesAbierto(false);
                    }}
                    className={`rounded-full theme-element border theme-border shrink-0 ${
                        driveAbierto ? 'border-[var(--color-primario)] theme-text-main' : 'theme-text-muted'
                    }`}
                />

                {esGrupo && (
                    <BotonIconoMensajeria
                        icon={Users}
                        label="Ver participantes del grupo"
                        onClick={() => {
                            setParticipantesAbierto(true);
                            setDriveAbierto(false);
                        }}
                        className="rounded-full theme-element border theme-border theme-text-muted shrink-0"
                    />
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
                    className="gelia-mensajeria-mensajes-scroll absolute inset-0 py-4"
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

                    {mensajesRender.map(({ mensaje, mostrarRemitente }, indice) => {
                        const dia = etiquetaDia(mensaje.created_at);
                        const anterior = indice > 0 ? etiquetaDia(mensajesRender[indice - 1].mensaje.created_at) : '';
                        return (
                            <Fragment key={mensaje.id}>
                                {dia && dia !== anterior && (
                                    <div className="gelia-mensaje-dia">{dia}</div>
                                )}
                                <MensajeBurbuja
                                    mensaje={mensaje}
                                    esGrupo={esGrupo}
                                    mostrarRemitente={mostrarRemitente}
                                    participantesGrupo={esGrupo ? conversacion.participantes || [] : []}
                                    onResponder={handleResponder}
                                    onIrAMensaje={handleIrACitacion}
                                />
                            </Fragment>
                        );
                    })}
                </div>
            </div>

            <MensajeInput
                onEnviarTexto={handleEnviarTextoConRespuesta}
                onEnviarAdjunto={handleEnviarAdjuntoConRespuesta}
                onEnviarSticker={handleEnviarSticker}
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
