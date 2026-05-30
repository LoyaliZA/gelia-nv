import React, { useCallback, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { X, Download } from 'lucide-react';

export default function VisorImagenMensaje({ adjunto, onCerrar }) {
    const src = adjunto.url || adjunto.thumbnail_url;
    const downloadUrl = route('mensajeria.adjuntos.show', adjunto.id);
    const nombre = adjunto.nombre_original || 'imagen';

    const cerrar = useCallback(() => onCerrar?.(), [onCerrar]);

    useEffect(() => {
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = prev; };
    }, []);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') cerrar();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [cerrar]);

    if (typeof document === 'undefined' || !src) return null;

    return createPortal(
        <div
            className="fixed inset-0 z-[10050] flex flex-col items-center justify-center p-4 bg-black/80 backdrop-blur-md animate-fade-in"
            onClick={cerrar}
            role="dialog"
            aria-modal="true"
            aria-label={`Vista ampliada: ${nombre}`}
        >
            <div
                className="absolute top-4 right-4 flex items-center gap-2 z-10"
                onClick={(e) => e.stopPropagation()}
            >
                <a
                    href={downloadUrl}
                    download={nombre}
                    className="flex items-center gap-2 px-3 py-2 rounded-full bg-black/50 text-white text-xs font-bold uppercase tracking-wider hover:bg-black/70 transition-colors"
                    title="Descargar imagen"
                >
                    <Download className="w-4 h-4" />
                    Descargar
                </a>
                <button
                    type="button"
                    onClick={cerrar}
                    className="p-2 rounded-full bg-black/50 text-white hover:bg-black/70 transition-colors"
                    aria-label="Cerrar"
                >
                    <X className="w-5 h-5" />
                </button>
            </div>

            <img
                src={src}
                alt={nombre}
                className="max-w-full max-h-[85dvh] object-contain rounded-xl shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            />

            {adjunto.nombre_original && (
                <p
                    className="mt-3 max-w-full truncate text-center text-xs font-bold text-white/80 px-4"
                    onClick={(e) => e.stopPropagation()}
                >
                    {adjunto.nombre_original}
                </p>
            )}
        </div>,
        document.body,
    );
}
