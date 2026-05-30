import React, { useRef, useState } from 'react';
import { Send, Paperclip, Image, Film, FileUp } from 'lucide-react';
import GrabadorAudio from './GrabadorAudio';
import AdjuntoPreview from './AdjuntoPreview';

const TIPOS = {
    imagen: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    video: ['video/mp4', 'video/webm', 'video/quicktime'],
    archivo: ['application/pdf', 'application/zip', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
};

const detectarTipo = (file) => {
    if (TIPOS.imagen.includes(file.type)) return 'imagen';
    if (TIPOS.video.includes(file.type)) return 'video';
    if (file.type.startsWith('audio/')) return 'audio';
    return 'archivo';
};

export default function MensajeInput({ onEnviarTexto, onEnviarAdjunto, enviando = false, compact = false }) {
    const [texto, setTexto] = useState('');
    const [preview, setPreview] = useState(null);
    const [mostrarAdjuntos, setMostrarAdjuntos] = useState(false);
    const inputImagen = useRef(null);
    const inputVideo = useRef(null);
    const inputArchivo = useRef(null);

    const handleEnviar = async (e) => {
        e?.preventDefault();
        if (enviando) return;

        if (preview) {
            await onEnviarAdjunto?.(preview.file, preview.tipo, texto.trim() || null);
            URL.revokeObjectURL(preview.url);
            setPreview(null);
            setTexto('');
            return;
        }

        if (!texto.trim()) return;
        await onEnviarTexto?.(texto.trim());
        setTexto('');
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleEnviar(e);
        }
    };

    const seleccionarArchivo = (file) => {
        if (!file) return;
        const tipo = detectarTipo(file);
        setPreview({ file, url: URL.createObjectURL(file), tipo });
        setMostrarAdjuntos(false);
    };

    const handleAudio = async (file) => {
        await onEnviarAdjunto?.(file, 'audio');
    };

    return (
        <div className={`gelia-mensajeria-input-bar border-t theme-border ${compact ? 'p-2' : 'p-3'} theme-surface shrink-0`}>
            <AdjuntoPreview
                preview={preview}
                onEliminar={() => {
                    if (preview?.url) URL.revokeObjectURL(preview.url);
                    setPreview(null);
                }}
            />

            <form onSubmit={handleEnviar} className="flex items-end gap-2">
                <div className="relative">
                    <button
                        type="button"
                        onClick={() => setMostrarAdjuntos((v) => !v)}
                        className="p-2 rounded-full theme-element theme-text-main hover:border-[var(--color-primario)] border theme-border transition-colors"
                        disabled={enviando}
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
                    <input ref={inputArchivo} type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip" className="hidden" onChange={(e) => seleccionarArchivo(e.target.files[0])} />
                </div>

                <GrabadorAudio onGrabado={handleAudio} disabled={enviando || !!preview} />

                <textarea
                    value={texto}
                    onChange={(e) => setTexto(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="Escribe un mensaje..."
                    rows={1}
                    disabled={enviando}
                    className={`flex-1 resize-none rounded-2xl px-4 py-2.5 text-sm theme-element theme-text-main border theme-border focus:border-[var(--color-primario)] outline-none max-h-32 ${compact ? 'text-xs' : ''}`}
                />

                <button
                    type="submit"
                    disabled={enviando || (!texto.trim() && !preview)}
                    className="p-2.5 rounded-full bg-[var(--color-primario)] text-white disabled:opacity-40 transition-opacity shrink-0"
                >
                    <Send className="w-5 h-5" />
                </button>
            </form>
        </div>
    );
}
