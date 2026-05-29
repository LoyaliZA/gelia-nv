import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronLeft, ChevronRight, X } from 'lucide-react';

export default function LightboxFotos({ fotos = [], indiceInicial = 0, onCerrar }) {
    const [indice, setIndice] = useState(indiceInicial);
    const [visible, setVisible] = useState(true);

    const total = fotos.length;
    const hayVarias = total > 1;

    const cerrar = useCallback(() => {
        setVisible(false);
        setTimeout(() => onCerrar?.(), 180);
    }, [onCerrar]);

    const anterior = useCallback(() => {
        setIndice((i) => (i - 1 + total) % total);
    }, [total]);

    const siguiente = useCallback(() => {
        setIndice((i) => (i + 1) % total);
    }, [total]);

    useEffect(() => {
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.body.style.overflow = prev; };
    }, []);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') cerrar();
            if (e.key === 'ArrowLeft' && hayVarias) anterior();
            if (e.key === 'ArrowRight' && hayVarias) siguiente();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [cerrar, anterior, siguiente, hayVarias]);

    if (typeof document === 'undefined' || !fotos.length) return null;

    return createPortal(
        <div
            className={`fixed inset-0 z-[600] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl ${visible ? 'animate-fade-in' : 'opacity-0 transition-opacity duration-150'}`}
            onClick={cerrar}
            role="dialog"
            aria-modal="true"
            aria-label="Visor de fotografías"
        >
            <button
                type="button"
                onClick={(e) => { e.stopPropagation(); cerrar(); }}
                className="absolute top-4 right-4 p-2 rounded-full bg-black/50 text-white hover:bg-black/70 transition-colors z-10"
                aria-label="Cerrar"
            >
                <X className="w-5 h-5" />
            </button>

            {hayVarias && (
                <>
                    <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); anterior(); }}
                        className="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 p-2 rounded-full bg-black/50 text-white hover:bg-black/70 transition-colors z-10"
                        aria-label="Anterior"
                    >
                        <ChevronLeft className="w-6 h-6" />
                    </button>
                    <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); siguiente(); }}
                        className="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 p-2 rounded-full bg-black/50 text-white hover:bg-black/70 transition-colors z-10"
                        aria-label="Siguiente"
                    >
                        <ChevronRight className="w-6 h-6" />
                    </button>
                    <span className="absolute bottom-4 left-1/2 -translate-x-1/2 text-white text-xs font-black uppercase tracking-wider bg-black/50 px-3 py-1 rounded-full">
                        {indice + 1}/{total}
                    </span>
                </>
            )}

            <img
                src={fotos[indice]}
                alt=""
                className="max-w-full max-h-[90dvh] object-contain rounded-xl shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            />
        </div>,
        document.body,
    );
}
