import React, { useEffect, useRef } from 'react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import MensajeBurbuja from './MensajeBurbuja';
import MensajeInput from './MensajeInput';
import AvatarUsuario from './AvatarUsuario';

export default function ChatPanel({
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
}) {
    const scrollRef = useRef(null);
    const topSentinelRef = useRef(null);
    const prevScrollHeight = useRef(0);
    const isInitialLoad = useRef(true);

    useEffect(() => {
        if (!scrollRef.current) return;

        if (isInitialLoad.current && mensajes.length > 0) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
            isInitialLoad.current = false;
        }
    }, [mensajes, conversacion?.id]);

    useEffect(() => {
        isInitialLoad.current = true;
    }, [conversacion?.id]);

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
    }, [hasMore, cargandoMensajes, onCargarMas]);

    useEffect(() => {
        if (prevScrollHeight.current && scrollRef.current) {
            const diff = scrollRef.current.scrollHeight - prevScrollHeight.current;
            scrollRef.current.scrollTop = diff;
            prevScrollHeight.current = 0;
        }
    }, [mensajes.length]);

    if (!conversacion) {
        return (
            <div className="flex-1 flex flex-col items-center justify-center theme-surface theme-text-main">
                <AvatarUsuario className="w-16 h-16" iconClassName="w-8 h-8" />
                <p className="text-sm font-bold uppercase italic theme-text-main mt-4">Selecciona un chat_</p>
                <p className="text-xs mt-1 theme-text-muted">O inicia una nueva conversación</p>
            </div>
        );
    }

    const esGrupo = conversacion.tipo === 'grupo';

    return (
        <div className="flex-1 flex flex-col min-w-0 h-full theme-surface theme-text-main">
            <div className="gelia-mensajeria-chat-header flex items-center gap-3 px-4 py-3 border-b theme-border shrink-0 bg-black/[0.03] dark:bg-white/[0.03]">
                {mostrarVolver && (
                    <button type="button" onClick={onVolver} className="p-1.5 rounded-full theme-element theme-text-main sm:hidden">
                        <ArrowLeft className="w-5 h-5" />
                    </button>
                )}

                <AvatarUsuario
                    foto={conversacion.foto}
                    nombre={conversacion.nombre}
                    esGrupo={esGrupo}
                    className="w-10 h-10"
                    iconClassName="w-5 h-5"
                />

                <div className="min-w-0">
                    <p className="text-sm font-black truncate theme-text-main">{conversacion.nombre}</p>
                    {esGrupo && (
                        <p className="text-[10px] theme-text-muted">
                            {conversacion.participantes?.length || 0} participantes
                        </p>
                    )}
                </div>
            </div>

            <div ref={scrollRef} className="flex-1 overflow-y-auto py-4 bg-black/[0.02] dark:bg-white/[0.02]">
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

                {mensajes.map((m) => (
                    <MensajeBurbuja key={m.id} mensaje={m} esGrupo={esGrupo} />
                ))}
            </div>

            <MensajeInput
                onEnviarTexto={onEnviarTexto}
                onEnviarAdjunto={onEnviarAdjunto}
                enviando={enviando}
            />
        </div>
    );
}
