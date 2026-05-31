import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Send, Paperclip, Image, Film, FileUp } from 'lucide-react';
import GrabadorAudio from './GrabadorAudio';
import BarraRespuestaMensaje from './BarraRespuestaMensaje';
import { detectarTipoArchivo } from '@/utils/mensajeriaArchivoLocal';

export default function MensajeInput({
    onEnviarTexto,
    onEnviarAdjunto,
    onPrepararAdjunto,
    respondiendoA = null,
    onCancelarRespuesta,
    enviando = false,
    compact = false,
}) {
    const [texto, setTexto] = useState('');
    const [mostrarAdjuntos, setMostrarAdjuntos] = useState(false);
    const inputImagen = useRef(null);
    const inputVideo = useRef(null);
    const inputArchivo = useRef(null);
    const textareaRef = useRef(null);

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

    const handleEnviar = async (e) => {
        e?.preventDefault();
        if (enviando || !texto.trim()) return;

        const contenido = texto.trim();
        setTexto('');

        try {
            await onEnviarTexto?.(contenido);
        } finally {
            restaurarFocoInput();
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
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
        } finally {
            restaurarFocoInput();
        }
    };

    return (
        <div className={`gelia-mensajeria-input-bar border-t theme-border ${compact ? 'p-2' : 'p-3'} theme-surface shrink-0`}>
            <BarraRespuestaMensaje mensaje={respondiendoA} onCancelar={onCancelarRespuesta} />

            <form onSubmit={handleEnviar} className="flex items-end gap-2">
                <div className="relative">
                    <button
                        type="button"
                        onClick={() => setMostrarAdjuntos((v) => !v)}
                        className="p-2 rounded-full theme-element theme-text-main hover:border-[var(--color-primario)] border theme-border transition-colors"
                        disabled={enviando}
                        title="Adjuntar archivo"
                    >
                        <Paperclip className="w-5 h-5" />
                    </button>

                    {mostrarAdjuntos && (
                        <div className="absolute bottom-full left-0 mb-2 flex flex-col gap-1 p-2 rounded-xl theme-surface border theme-border shadow-lg z-10">
                            <button type="button" onClick={() => inputImagen.current?.click()} className="flex items-center gap-2 px-3 py-2 text-xs rounded-lg theme-text-main hover:theme-element">
                                <Image className="w-4 h-4" /> Imagen
                            </button>
                            <button type="button" onClick={() => inputVideo.current?.click()} className="flex items-center gap-2 px-3 py-2 text-xs rounded-lg theme-text-main hover:theme-element">
                                <Film className="w-4 h-4" /> Video
                            </button>
                            <button type="button" onClick={() => inputArchivo.current?.click()} className="flex items-center gap-2 px-3 py-2 text-xs rounded-lg theme-text-main hover:theme-element">
                                <FileUp className="w-4 h-4" /> Archivo
                            </button>
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

                <GrabadorAudio onGrabado={handleAudio} disabled={enviando} />

                <textarea
                    ref={textareaRef}
                    value={texto}
                    onChange={(e) => setTexto(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={respondiendoA ? 'Escribe tu respuesta…' : 'Escribe un mensaje…'}
                    rows={1}
                    aria-busy={enviando}
                    className={`flex-1 resize-none rounded-2xl px-4 py-2.5 text-sm theme-element theme-text-main border theme-border focus:border-[var(--color-primario)] outline-none max-h-32 ${compact ? 'text-xs' : ''}`}
                />

                <button
                    type="submit"
                    disabled={enviando || !texto.trim()}
                    className="p-2.5 rounded-full bg-[var(--color-primario)] text-white disabled:opacity-40 transition-opacity shrink-0"
                    title="Enviar mensaje"
                >
                    <Send className="w-5 h-5" />
                </button>
            </form>
        </div>
    );
}
