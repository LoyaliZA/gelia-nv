import React, { useState } from 'react';
import VisorImagenMensaje from './VisorImagenMensaje';

export default function MensajeImagen({ adjunto }) {
    const [visorAbierto, setVisorAbierto] = useState(false);
    const src = adjunto.url || adjunto.thumbnail_url;
    if (!src) return null;

    return (
        <>
            <button
                type="button"
                onClick={() => setVisorAbierto(true)}
                className="block mt-1 rounded-xl overflow-hidden cursor-zoom-in focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)]"
                aria-label={`Ver imagen: ${adjunto.nombre_original || 'adjunto'}`}
            >
                <img
                    src={src}
                    alt={adjunto.nombre_original || 'Imagen'}
                    className="max-w-full max-h-64 rounded-xl object-cover hover:opacity-90 transition-opacity"
                    loading="lazy"
                />
            </button>

            {visorAbierto && (
                <VisorImagenMensaje
                    adjunto={adjunto}
                    onCerrar={() => setVisorAbierto(false)}
                />
            )}
        </>
    );
}
