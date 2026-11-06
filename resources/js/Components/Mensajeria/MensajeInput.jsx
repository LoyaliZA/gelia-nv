import React, { useCallback, useEffect, useRef, useState } from 'react';
import { FileUp, Film, Image, Paperclip, Send, Sticker } from 'lucide-react';
import GrabadorAudio from './GrabadorAudio';
import BarraRespuestaMensaje from './BarraRespuestaMensaje';
import BandejaStickers from './BandejaStickers';
import BotonIconoMensajeria from './BotonIconoMensajeria';
import { detectarTipoArchivo } from '@/utils/mensajeriaArchivoLocal';
import { archivosImagenDesdePortapapeles } from '@/utils/mensajeriaPortapapeles';
import { animarEntrada } from '@/utils/animarMensajeria';
import useIconoHover from '@/hooks/useIconoHover';

function OpcionAdjunto({ icon, children, onClick }) {
    const { nodo, triggerProps } = useIconoHover(icon, { size: 16, className: 'w-4 h-4' });

    return (
        <button
            type="button"
            className="gelia-toque flex items-center gap-2 px-3 text-sm rounded-xl theme-text-main hover:theme-element"
            {...triggerProps}
            onClick={onClick}
        >
            {nodo}
            {children}
        </button>
    );
}

export default function MensajeInput({
    onEnviarTexto,
    onEnviarAdjunto,
    onEnviarSticker,
    onPrepararAdjunto,
    respondiendoA = null,
    onCancelarRespuesta,
    enviando = false,
    compact = false,
}) {
    const [texto, setTexto] = useState('');
    const [error, setError] = useState('');
    const envioEnCurso = useRef(false);
    const [mostrarAdjuntos, setMostrarAdjuntos] = useState(false);
    const [mostrarStickers, setMostrarStickers] = useState(false);
    const inputImagen = useRef(null);
    const inputVideo = useRef(null);
    const inputArchivo = useRef(null);
    const textareaRef = useRef(null);
    const menuAdjuntos = useRef(null);

    const preparar = onPrepararAdjunto || ((file) => {
        const tipo = detectarTipoArchivo(file);
        if (tipo === 'audio') {
            onEnviarAdjunto?.(file, 'audio', null);
            return;
        }
        onEnviarAdjunto?.(file, tipo, null);
    });

    const restaurarFocoInput = useCallback(() => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                const el = textareaRef.current;
                if (!el || el.disabled) return;
                el.focus();
            });
        });
    }, []);

    useEffect(() => {
        if (respondiendoA) {
            restaurarFocoInput();
        }
    }, [respondiendoA?.id, restaurarFocoInput]);

    useEffect(() => {
        if (!mostrarAdjuntos) return undefined;
        animarEntrada(menuAdjuntos.current, { eje: 'y', distancia: 8, duration: 220 });
        return undefined;
    }, [mostrarAdjuntos]);

    useEffect(() => {
        const el = textareaRef.current;
        if (el) { el.style.height = 'auto'; el.style.height = `${Math.min(el.scrollHeight, 128)}px`; }
    }, [texto]);

    useEffect(() => {
        if (!mostrarAdjuntos && !mostrarStickers) return undefined;
        const cerrar = (e) => {
            if (e.key === 'Escape') { setMostrarAdjuntos(false); setMostrarStickers(false); }
        };
        const fuera = (e) => {
            if (mostrarAdjuntos && !e.target.closest('.gelia-mensajeria-composer')) setMostrarAdjuntos(false);
        };
        document.addEventListener('keydown', cerrar);
        document.addEventListener('pointerdown', fuera);
        return () => { document.removeEventListener('keydown', cerrar); document.removeEventListener('pointerdown', fuera); };
    }, [mostrarAdjuntos, mostrarStickers]);

    const handleEnviar = async (e) => {
        e?.preventDefault();
        if (enviando || envioEnCurso.current || !texto.trim()) return;

        const contenido = texto.trim();
        envioEnCurso.current = true;
        setError('');
        try {
            await onEnviarTexto?.(contenido);
            setTexto((actual) => actual.trim() === contenido ? '' : actual);
        } catch {
            setError('No se pudo enviar. Tu mensaje sigue aquí para volver a intentarlo.');
        } finally {
            envioEnCurso.current = false;
            restaurarFocoInput();
        }
    };

    const handlePaste = (event) => {
        const archivos = archivosImagenDesdePortapapeles(event.clipboardData);
        if (!archivos.length) return;
        event.preventDefault();
        event.stopPropagation();
        preparar(archivos[0]);
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
            e.preventDefault();
            handleEnviar(e);
        }
        if (e.key === 'Escape' && respondiendoA) {
            onCancelarRespuesta?.();
        }
    };

    const seleccionarArchivo = (file) => {
        if (!file) return;
        preparar(file);
        setMostrarAdjuntos(false);
        if (inputImagen.current) inputImagen.current.value = '';
        if (inputVideo.current) inputVideo.current.value = '';
        if (inputArchivo.current) inputArchivo.current.value = '';
    };

    const handleAudio = async (file) => {
        try {
            await onEnviarAdjunto?.(file, 'audio', null);
        } catch {
            setError('No se pudo enviar el audio. Inténtalo de nuevo.');
        } finally {
            restaurarFocoInput();
        }
    };

    const enviarSticker = async (stickerId) => {
        try {
            await onEnviarSticker?.(stickerId);
            setMostrarStickers(false);
        } finally {
            restaurarFocoInput();
        }
    };

    return (
        <div className={`gelia-mensajeria-input-bar ${compact ? 'px-2 pb-2' : 'px-3 pb-3'} theme-surface shrink-0`}>
            {error && <p role="alert" className="gelia-mensajeria-error">{error}</p>}
            <BarraRespuestaMensaje mensaje={respondiendoA} onCancelar={onCancelarRespuesta} />
            <BandejaStickers
                abierto={mostrarStickers}
                enviando={enviando}
                onCerrar={() => setMostrarStickers(false)}
                onEnviar={enviarSticker}
            />

            <form onSubmit={handleEnviar} className="gelia-mensajeria-composer">
                <div className="relative">
                    <BotonIconoMensajeria
                        icon={Paperclip}
                        label="Adjuntar archivo"
                        onClick={() => {
                            setMostrarAdjuntos((v) => !v);
                            setMostrarStickers(false);
                        }}
                        pressed={mostrarAdjuntos}
                        disabled={enviando}
                        className="rounded-full theme-text-main"
                    />

                    {mostrarAdjuntos && (
                        <div ref={menuAdjuntos} className="gelia-menu-adjuntos absolute bottom-full left-0 mb-2 flex flex-col gap-1 p-2 rounded-2xl theme-surface border theme-border shadow-lg z-10 min-w-[9.5rem]">
                            <OpcionAdjunto icon={Image} onClick={() => inputImagen.current?.click()}>Imagen</OpcionAdjunto>
                            <OpcionAdjunto icon={Film} onClick={() => inputVideo.current?.click()}>Video</OpcionAdjunto>
                            <OpcionAdjunto icon={FileUp} onClick={() => inputArchivo.current?.click()}>Archivo</OpcionAdjunto>
                        </div>
                    )}

                    <input ref={inputImagen} type="file" accept="image/*" className="hidden" onChange={(e) => seleccionarArchivo(e.target.files[0])} />
                    <input ref={inputVideo} type="file" accept="video/*" className="hidden" onChange={(e) => seleccionarArchivo(e.target.files[0])} />
                    <input
                        ref={inputArchivo}
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.xlsm,.zip,.sql,.csv,.txt,application/pdf"
                        className="hidden"
                        onChange={(e) => seleccionarArchivo(e.target.files[0])}
                    />
                </div>

                <BotonIconoMensajeria
                    icon={Sticker}
                    label="Stickers"
                    pressed={mostrarStickers}
                    disabled={enviando}
                    onClick={() => {
                        setMostrarStickers((v) => !v);
                        setMostrarAdjuntos(false);
                    }}
                    className="rounded-full theme-text-main"
                />

                <GrabadorAudio onError={setError} onGrabado={handleAudio} disabled={enviando} />

                <textarea
                    ref={textareaRef}
                    value={texto}
                    aria-label="Mensaje"
                    onChange={(e) => setTexto(e.target.value)}
                    onKeyDown={handleKeyDown}
                    onPaste={handlePaste}
                    placeholder={respondiendoA ? 'Escribe una respuesta…' : 'Escribe un mensaje…'}
                    rows={1}
                    aria-busy={enviando}
                    className={`flex-1 resize-none bg-transparent border-0 px-1 py-2.5 text-sm theme-text-main outline-none max-h-32 ${compact ? 'text-xs' : ''}`}
                />

                <BotonIconoMensajeria
                    icon={Send}
                    label="Enviar mensaje"
                    type="submit"
                    disabled={enviando || !texto.trim()}
                    className="gelia-mensajeria-enviar rounded-full bg-[var(--color-primario)] text-white disabled:opacity-40"
                />
            </form>
        </div>
    );
}
