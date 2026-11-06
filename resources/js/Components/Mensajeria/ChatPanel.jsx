import React, { Fragment, forwardRef, useCallback, useEffect, useImperativeHandle, useMemo, useRef, useState } from 'react';
import { ArrowLeft, FolderOpen, Loader2, MoreVertical, Search, MessagesSquare } from 'lucide-react';
import BuscadorInteligente from './BuscadorInteligente';
import { usePage } from '@inertiajs/react';
import MensajeBurbuja from './MensajeBurbuja';
import MensajeInput from './MensajeInput';
import AvatarUsuario from './AvatarUsuario';
import ParticipantesGrupoPanel from './ParticipantesGrupoPanel';
import ChatDrivePanel from './ChatDrivePanel';
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
    const [perfilAbierto, setPerfilAbierto] = useState(false);
    const [driveAbierto, setDriveAbierto] = useState(false);
    const [buscarAbierto, setBuscarAbierto] = useState(false);
    const [menuAbierto, setMenuAbierto] = useState(false);
    const [respondiendoA, setRespondiendoA] = useState(null);
    const ultimoAnimado = useRef(null);
    const menuRef = useRef(null);
    const layoutRef = useRef(null);
    const [archivosSuperpuestos, setArchivosSuperpuestos] = useState(true);
    useEffect(() => {
        if (!layoutRef.current) return undefined;
        const observer = new ResizeObserver(([entrada]) => setArchivosSuperpuestos(entrada.contentRect.width < 760));
        observer.observe(layoutRef.current);
        return () => observer.disconnect();
    }, [conversacion?.id]);

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
        setPerfilAbierto(false);
        setBuscarAbierto(false);
        setMenuAbierto(false);
        setRespondiendoA(null);
        ultimoAnimado.current = null;
    }, [conversacion?.id]);

    useEffect(() => {
        if (!menuAbierto) return undefined;
        const cerrar = (evento) => {
            if (!menuRef.current?.contains(evento.target)) setMenuAbierto(false);
        };
        const tecla = (evento) => {
            if (evento.key === 'Escape') setMenuAbierto(false);
        };
        document.addEventListener('mousedown', cerrar);
        document.addEventListener('keydown', tecla);
        return () => {
            document.removeEventListener('mousedown', cerrar);
            document.removeEventListener('keydown', tecla);
        };
    }, [menuAbierto]);

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
            <div className="gelia-mensajeria-chat-pane gelia-mensajeria-bienvenida flex-1 flex flex-col items-center justify-center theme-text-main px-6 text-center">
                <div className="gelia-mensajeria-bienvenida-icono"><MessagesSquare size={42} strokeWidth={1.5} aria-hidden /></div>
                <p className="text-base font-semibold theme-text-main mt-5 m-0">Tu espacio para conversar</p>
                <p className="text-sm mt-2 m-0 theme-text-muted">Elige un chat o inicia una conversación con tu equipo.</p>
            </div>
        );
    }

    return (
        <div className="gelia-mensajeria-chat-pane flex-1 flex flex-col min-w-0 h-full theme-text-main">
            <div ref={layoutRef} className={`gelia-mensajeria-chat-layout relative flex flex-1 min-h-0 min-w-0 overflow-hidden ${archivosSuperpuestos ? 'gelia-mensajeria-chat-layout--estrecho' : ''}`}>
            <ZonaDropAdjuntoChat
                onEnviarAdjunto={handleEnviarAdjuntoConRespuesta}
                enviando={enviando}
                className={`gelia-mensajeria-chat-column flex flex-1 flex-col min-w-0 min-h-0 ${driveAbierto && archivosSuperpuestos ? 'invisible' : ''}`}
            >
            {({ prepararAdjunto }) => (
            <>
            <div className="gelia-mensajeria-chat-header border-b theme-border shrink-0">
                {buscarAbierto ? (
                    <div className="flex items-center gap-1 px-2 py-2 min-w-0">
                        <BotonIconoMensajeria
                            icon={ArrowLeft}
                            label="Cerrar búsqueda"
                            onClick={() => setBuscarAbierto(false)}
                            plano
                        />
                        <BuscadorInteligente
                            conversacionId={conversacion.id}
                            placeholder="Buscar en este chat…"
                            onIrAMensaje={onIrAMensaje}
                            className="flex-1 min-w-0"
                            autoFocus
                        />
                    </div>
                ) : (
                    <div className="flex items-center min-w-0 px-1 py-1">
                        {mostrarVolver && (
                            <BotonIconoMensajeria
                                icon={ArrowLeft}
                                label="Volver a los mensajes"
                                onClick={onVolver}
                                plano
                                className={volverBreakpoint === 'siempre' ? '' : volverBreakpoint === 'md' ? 'md:hidden' : 'sm:hidden'}
                            />
                        )}

                        <button
                            type="button"
                            className="gelia-mensajeria-identidad"
                            onClick={() => {
                                setPerfilAbierto(true);
                                setMenuAbierto(false);
                            }}
                        >
                            <AvatarUsuario
                                foto={conversacion.foto}
                                nombre={conversacion.nombre}
                                esGrupo={esGrupo}
                                enLinea={!esGrupo && conversacion.presencia_otro?.en_linea === true}
                                className="w-10 h-10 shrink-0"
                                iconClassName="w-5 h-5"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold truncate theme-text-main">{conversacion.nombre}</span>
                                {!esGrupo && conversacion.presencia_otro && (
                                    <EstadoPresenciaTexto presencia={conversacion.presencia_otro} compact as="span" className="mt-0.5" />
                                )}
                                {esGrupo && (
                                    <span className="block text-[11px] theme-text-muted truncate mt-0.5">
                                        {(conversacion.participantes?.length || 0) === 1
                                            ? '1 integrante'
                                            : `${conversacion.participantes?.length || 0} integrantes`}
                                    </span>
                                )}
                            </span>
                        </button>

                        <BotonIconoMensajeria
                            icon={Search}
                            label="Buscar"
                            onClick={() => {
                                setBuscarAbierto(true);
                                setMenuAbierto(false);
                            }}
                            plano
                        />
                        <BotonIconoMensajeria
                            icon={FolderOpen}
                            label="Archivos, enlaces y docs."
                            pressed={driveAbierto}
                            onClick={() => {
                                setDriveAbierto((v) => !v);
                                setPerfilAbierto(false);
                                setMenuAbierto(false);
                            }}
                            plano
                        />
                        <div className="relative shrink-0" ref={menuRef}>
                            <BotonIconoMensajeria
                                icon={MoreVertical}
                                label="Más opciones"
                                pressed={menuAbierto}
                                onClick={() => setMenuAbierto((v) => !v)}
                                plano
                            />
                            {menuAbierto && (
                                <div className="gelia-mensajeria-menu" role="menu">
                                    <button type="button" role="menuitem" onClick={() => { setPerfilAbierto(true); setMenuAbierto(false); }}>
                                        Ver perfil
                                    </button>
                                    <button type="button" role="menuitem" onClick={() => { setBuscarAbierto(true); setMenuAbierto(false); }}>
                                        Buscar
                                    </button>
                                    <button type="button" role="menuitem" onClick={() => { setDriveAbierto(true); setPerfilAbierto(false); setMenuAbierto(false); setBuscarAbierto(false); }}>
                                        Archivos, enlaces y docs.
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <ParticipantesGrupoPanel
                isOpen={perfilAbierto}
                onClose={() => setPerfilAbierto(false)}
                conversacion={conversacion}
                usuarioActualId={auth?.user?.id}
                onBuscar={() => {
                    setPerfilAbierto(false);
                    setBuscarAbierto(true);
                }}
                onAbrirArchivos={() => {
                    setPerfilAbierto(false);
                    setDriveAbierto(true);
                    setBuscarAbierto(false);
                }}
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

            <MensajeInput key={conversacion.id}
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
