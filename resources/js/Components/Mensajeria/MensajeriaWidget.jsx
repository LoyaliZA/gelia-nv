import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { createPortal } from 'react-dom';
import { MessageCircle, MessageSquare, X, ExternalLink, Users, Loader2 } from 'lucide-react';
import { Link, usePage } from '@inertiajs/react';
import MensajeriaService from '@/Services/MensajeriaService';
import MensajeInput from '@/Components/Mensajeria/MensajeInput';
import MensajeBurbuja from '@/Components/Mensajeria/MensajeBurbuja';
import AvatarUsuario from '@/Components/Mensajeria/AvatarUsuario';
import useChatScroll from '@/hooks/useChatScroll';
import useMensajeriaEcho from '@/hooks/useMensajeriaEcho';
import { setConversacionActivaMensajeria, setMensajeriaWidgetAbierto } from '@/utils/mensajeriaNotificaciones';
import { normalizarMensajeParaViewer, aplicarActualizacionLectura } from '@/utils/mensajeriaMensaje';
import { prepararMensajesGrupo } from '@/utils/mensajeriaGrupo';
import ParticipantesGrupoPanel from '@/Components/Mensajeria/ParticipantesGrupoPanel';
import ZonaDropAdjuntoChat from '@/Components/Mensajeria/ZonaDropAdjuntoChat';
import SelectorEstadoPresencia from '@/Components/Mensajeria/SelectorEstadoPresencia';
import EstadoPresenciaTexto from '@/Components/Mensajeria/EstadoPresenciaTexto';
import usePresenciaHeartbeat from '@/hooks/usePresenciaHeartbeat';
import usePresenciaContactos from '@/hooks/usePresenciaContactos';

export function MensajeriaCountBadge({ count, className = '-top-1.5 -right-1.5' }) {
    if (!count || count <= 0) return null;

    return (
        <span
            className={`absolute z-30 flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 text-[9px] font-black leading-none text-white rounded-full shadow-md border-2 theme-surface pointer-events-none ${className}`}
            style={{ backgroundColor: 'var(--color-primario)' }}
            aria-hidden="true"
        >
            {count > 9 ? '9+' : count}
        </span>
    );
}

const formatearPreview = (c) => c.ultimo_mensaje_preview || 'Sin mensajes';

export default function MensajeriaWidget({ iconButtonClassName = '' }) {
    const { auth } = usePage().props;
    const resumenInicial = auth?.mensajeria_resumen;

    const [isOpen, setIsOpen] = useState(false);
    const [conversaciones, setConversaciones] = useState(resumenInicial?.conversaciones || []);
    const [totalUnread, setTotalUnread] = useState(resumenInicial?.total_unread || 0);
    const [activa, setActiva] = useState(null);
    const [mensajes, setMensajes] = useState([]);
    const [cursor, setCursor] = useState(null);
    const [hasMore, setHasMore] = useState(false);
    const [cargandoMensajes, setCargandoMensajes] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const topSentinelRef = useRef(null);
    const prevScrollHeightRef = useRef(0);
    const [participantesAbierto, setParticipantesAbierto] = useState(false);
    const [respondiendoA, setRespondiendoA] = useState(null);

    const { scrollRef } = useChatScroll(mensajes, activa?.id);

    const esGrupoActivo = activa?.tipo === 'grupo';
    const mensajesRender = useMemo(
        () => prepararMensajesGrupo(mensajes, esGrupoActivo),
        [mensajes, esGrupoActivo]
    );

    usePresenciaHeartbeat(isOpen);
    usePresenciaContactos(setConversaciones, setActiva);

    useEffect(() => {
        if (!resumenInicial) return;
        setConversaciones(resumenInicial.conversaciones || []);
        setTotalUnread(resumenInicial.total_unread || 0);
    }, [resumenInicial]);

    useEffect(() => {
        setMensajeriaWidgetAbierto(isOpen);
        if (isOpen) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = 'unset';
        return () => {
            setMensajeriaWidgetAbierto(false);
            document.body.style.overflow = 'unset';
        };
    }, [isOpen]);

    useEffect(() => {
        setConversacionActivaMensajeria(isOpen && activa ? activa.id : null);
        return () => {
            if (!isOpen) setConversacionActivaMensajeria(null);
        };
    }, [isOpen, activa]);

    const handleMensajeRecibido = useCallback((event) => {
        const mensaje = event.detail;
        if (!mensaje || mensaje.user?.id === auth?.user?.id) return;

        setConversaciones((prev) => {
            const idx = prev.findIndex((c) => c.id === mensaje.conversacion_id);
            if (idx === -1) {
                return prev;
            }
            const actualizada = {
                ...prev[idx],
                ultimo_mensaje_preview: mensaje.contenido || `[${mensaje.tipo}]`,
                ultimo_mensaje_at: mensaje.created_at,
                unread_count: isOpen && activa?.id === mensaje.conversacion_id
                    ? 0
                    : (prev[idx].unread_count || 0) + 1,
            };
            const resto = prev.filter((c) => c.id !== mensaje.conversacion_id);
            return [actualizada, ...resto];
        });

        if (isOpen && activa?.id === mensaje.conversacion_id) {
            const normalizado = normalizarMensajeParaViewer(mensaje, auth?.user?.id);
            setMensajes((prev) => {
                if (prev.some((m) => m.id === normalizado.id)) return prev;
                return [...prev, normalizado];
            });
            MensajeriaService.marcarLeida(mensaje.conversacion_id);
        } else {
            setTotalUnread((n) => n + 1);
        }
    }, [activa, auth?.user?.id, isOpen]);

    useEffect(() => {
        window.addEventListener('mensajeria-mensaje-recibido', handleMensajeRecibido);
        return () => window.removeEventListener('mensajeria-mensaje-recibido', handleMensajeRecibido);
    }, [handleMensajeRecibido]);

    const handleMensajeLeido = useCallback((mensaje) => {
        if (!mensaje || Number(mensaje.user?.id) !== Number(auth?.user?.id)) return;

        setMensajes((prev) =>
            prev.map((m) =>
                m.id === mensaje.id
                    ? aplicarActualizacionLectura(m, mensaje, auth?.user?.id)
                    : m
            )
        );
    }, [auth?.user?.id]);

    useMensajeriaEcho(isOpen && activa?.id ? activa.id : null, {
        onMensajeLeido: handleMensajeLeido,
    });

    useEffect(() => {
        const handler = (event) => handleMensajeLeido(event.detail);
        window.addEventListener('mensajeria-mensaje-leido', handler);
        return () => window.removeEventListener('mensajeria-mensaje-leido', handler);
    }, [handleMensajeLeido]);

    useEffect(() => {
        if (!isOpen || !activa?.id) return undefined;

        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                MensajeriaService.marcarLeida(activa.id);
            }
        };

        document.addEventListener('visibilitychange', onVisible);

        return () => document.removeEventListener('visibilitychange', onVisible);
    }, [isOpen, activa?.id]);

    const scrollAMensaje = useCallback((mensajeId) => {
        requestAnimationFrame(() => {
            const el = document.getElementById(`mensaje-${mensajeId}`);
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el?.classList.add('gelia-mensaje--destacado');
            setTimeout(() => el?.classList.remove('gelia-mensaje--destacado'), 2400);
        });
    }, []);

    const abrirConversacion = async (conversacion) => {
        setActiva(conversacion);
        setRespondiendoA(null);
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
            setConversaciones((prev) =>
                prev.map((c) => c.id === conversacion.id ? { ...c, unread_count: 0 } : c)
            );
            setTotalUnread((prev) => Math.max(0, prev - (conversacion.unread_count || 0)));
        } finally {
            setCargandoMensajes(false);
        }
    };

    const cargarMasMensajes = useCallback(async () => {
        if (!activa || !hasMore || !cursor || cargandoMensajes) return;

        setCargandoMensajes(true);
        try {
            const data = await MensajeriaService.listarMensajes(activa.id, cursor);
            setMensajes((prev) => [...data.mensajes, ...prev]);
            setCursor(data.next_cursor);
            setHasMore(data.has_more);
        } finally {
            setCargandoMensajes(false);
        }
    }, [activa, hasMore, cursor, cargandoMensajes]);

    useEffect(() => {
        const sentinel = topSentinelRef.current;
        const root = scrollRef.current;
        if (!sentinel || !root || !activa) return;

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && hasMore && !cargandoMensajes) {
                    prevScrollHeightRef.current = root.scrollHeight;
                    cargarMasMensajes();
                }
            },
            { root, threshold: 0.1 }
        );

        observer.observe(sentinel);
        return () => observer.disconnect();
    }, [activa?.id, hasMore, cargandoMensajes, cargarMasMensajes, scrollRef]);

    useEffect(() => {
        const root = scrollRef.current;
        if (!prevScrollHeightRef.current || !root) return;

        const diff = root.scrollHeight - prevScrollHeightRef.current;
        root.scrollTop = diff;
        prevScrollHeightRef.current = 0;
    }, [mensajes.length, scrollRef]);

    const enviarTexto = async (texto) => {
        if (!activa) return;
        setEnviando(true);
        try {
            const mensaje = await MensajeriaService.enviarMensaje(
                activa.id,
                texto,
                respondiendoA?.id ?? null
            );
            setMensajes((prev) => [...prev, mensaje]);
            setRespondiendoA(null);
        } finally {
            setEnviando(false);
        }
    };

    const enviarAdjunto = async (file, tipo, contenido) => {
        if (!activa) return;
        setEnviando(true);
        try {
            const mensaje = await MensajeriaService.enviarAdjunto(
                activa.id,
                file,
                tipo,
                contenido,
                respondiendoA?.id ?? null
            );
            setMensajes((prev) => [...prev, mensaje]);
            setRespondiendoA(null);
        } finally {
            setEnviando(false);
        }
    };

    const cerrarDrawer = () => {
        setIsOpen(false);
        setActiva(null);
        setMensajes([]);
        setCursor(null);
        setHasMore(false);
        setParticipantesAbierto(false);
        setRespondiendoA(null);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setIsOpen(true)}
                className={`relative overflow-visible theme-element border theme-border rounded-2xl hover:border-[var(--color-primario)] transition-all group outline-none shrink-0 ${iconButtonClassName || 'p-3'}`}
                aria-label="Mensajería"
            >
                {totalUnread > 0 ? (
                    <MessageSquare className="w-5 h-5 animate-pulse" style={{ color: 'var(--color-primario)' }} />
                ) : (
                    <MessageCircle className="w-5 h-5 theme-text-muted group-hover:text-[var(--color-primario)] transition-colors" />
                )}
                <MensajeriaCountBadge count={totalUnread} />
            </button>

            {isOpen && createPortal(
                <div className="gelia-mensajeria-portal fixed inset-0 z-[9999] flex justify-end">
                    <div className="absolute inset-0 bg-black/60 backdrop-blur-sm animate-fade-in" onClick={cerrarDrawer} />

                    <div className="relative w-full max-w-md h-full theme-surface theme-text-main border-l theme-border shadow-2xl flex flex-col animate-slide-in-right">
                        <div className="p-5 md:p-6 border-b theme-border flex justify-between items-center bg-black/5 dark:bg-white/5 shrink-0">
                            <div className="flex items-center gap-3 min-w-0">
                                <div className="p-2 rounded-xl shrink-0" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                    <MessageCircle className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                </div>
                                <div className="min-w-0">
                                    <h3 className="text-base font-black uppercase tracking-tighter theme-text-main italic m-0 truncate">
                                        Mensajes_
                                    </h3>
                                    <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-0.5">
                                        {totalUnread > 0 ? `${totalUnread} sin leer` : 'Al día'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-1 shrink-0">
                                <Link
                                    href={route('mensajeria.index')}
                                    onClick={cerrarDrawer}
                                    className="p-2 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-xl hover:border-[var(--color-primario)] transition-colors"
                                    title="Abrir mensajería completa"
                                >
                                    <ExternalLink className="w-4 h-4" />
                                </Link>
                                <button
                                    type="button"
                                    onClick={cerrarDrawer}
                                    className="p-2 theme-text-muted hover:theme-text-main bg-white/10 dark:bg-black/10 rounded-full transition-transform hover:scale-110 outline-none"
                                >
                                    <X className="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        {!activa ? (
                            <div className="flex-1 overflow-y-auto custom-scrollbar">
                                {conversaciones.length === 0 && (
                                    <p className="text-xs theme-text-muted text-center py-10 px-4">
                                        No hay conversaciones. Abre la mensajería completa para iniciar un chat.
                                    </p>
                                )}
                                {conversaciones.map((c) => (
                                    <button
                                        key={c.id}
                                        type="button"
                                        onClick={() => abrirConversacion(c)}
                                        className="w-full flex items-center gap-3 px-4 py-3.5 text-left hover:theme-element border-b theme-border/50 transition-colors"
                                    >
                                        <AvatarUsuario
                                            foto={c.foto}
                                            nombre={c.nombre}
                                            esGrupo={c.tipo === 'grupo'}
                                        />
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-bold theme-text-main truncate">{c.nombre}</p>
                                            <p className="text-xs theme-text-muted truncate">{formatearPreview(c)}</p>
                                        </div>
                                        {c.unread_count > 0 && (
                                            <span
                                                className="shrink-0 min-w-[1.25rem] h-5 px-1.5 flex items-center justify-center text-[10px] font-black text-white rounded-full"
                                                style={{ backgroundColor: 'var(--color-primario)' }}
                                            >
                                                {c.unread_count > 9 ? '9+' : c.unread_count}
                                            </span>
                                        )}
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <ZonaDropAdjuntoChat
                                onEnviarAdjunto={enviarAdjunto}
                                enviando={enviando}
                                className="gelia-mensajeria-chat-column flex flex-1 flex-col min-h-0 min-w-0"
                            >
                            {({ prepararAdjunto }) => (
                            <>
                                <div className="flex items-center gap-2 px-4 py-3 border-b theme-border shrink-0 bg-black/[0.03] dark:bg-white/[0.03]">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setActiva(null);
                                            setMensajes([]);
                                            setCursor(null);
                                            setHasMore(false);
                                            setParticipantesAbierto(false);
                                        }}
                                        className="text-xs font-bold theme-text-muted hover:theme-text-main transition-colors shrink-0"
                                    >
                                        ← Volver
                                    </button>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-bold theme-text-main truncate m-0">{activa.nombre}</p>
                                        {!esGrupoActivo && activa.presencia_otro && (
                                            <EstadoPresenciaTexto presencia={activa.presencia_otro} compact className="mt-0.5" />
                                        )}
                                        {esGrupoActivo && (
                                            <button
                                                type="button"
                                                onClick={() => setParticipantesAbierto(true)}
                                                className="text-[10px] font-bold theme-text-muted hover:theme-text-main transition-colors text-left truncate max-w-full m-0 mt-0.5 p-0 border-0 bg-transparent outline-none"
                                                title="Ver participantes del grupo"
                                            >
                                                {activa.participantes?.length || 0} participantes · Ver lista
                                            </button>
                                        )}
                                    </div>
                                    <SelectorEstadoPresencia compact />
                                    {esGrupoActivo && (
                                        <button
                                            type="button"
                                            onClick={() => setParticipantesAbierto(true)}
                                            className="p-2 rounded-full theme-element border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:theme-text-main transition-all outline-none shrink-0"
                                            title="Ver participantes"
                                            aria-label="Ver participantes del grupo"
                                        >
                                            <Users className="w-4 h-4" />
                                        </button>
                                    )}
                                </div>

                                <ParticipantesGrupoPanel
                                    isOpen={participantesAbierto && esGrupoActivo}
                                    onClose={() => setParticipantesAbierto(false)}
                                    nombreGrupo={activa.nombre}
                                    participantes={activa.participantes || []}
                                    usuarioActualId={auth?.user?.id}
                                />

                                <div className="relative flex-1 min-h-0 min-w-0 overflow-hidden">
                                    <div
                                        ref={scrollRef}
                                        className="gelia-mensajeria-mensajes-scroll absolute inset-0 custom-scrollbar py-3 bg-black/[0.02] dark:bg-white/[0.02]"
                                    >
                                        <div ref={topSentinelRef} className="h-1" aria-hidden />

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
                                                esGrupo={esGrupoActivo}
                                                mostrarRemitente={mostrarRemitente}
                                                participantesGrupo={esGrupoActivo ? activa.participantes || [] : []}
                                                onResponder={setRespondiendoA}
                                                onIrAMensaje={scrollAMensaje}
                                            />
                                        ))}
                                    </div>
                                </div>

                                <MensajeInput
                                    onEnviarTexto={enviarTexto}
                                    onEnviarAdjunto={enviarAdjunto}
                                    onPrepararAdjunto={prepararAdjunto}
                                    respondiendoA={respondiendoA}
                                    onCancelarRespuesta={() => setRespondiendoA(null)}
                                    enviando={enviando}
                                    compact
                                />
                            </>
                            )}
                            </ZonaDropAdjuntoChat>
                        )}
                    </div>
                </div>,
                document.body
            )}
        </>
    );
}
