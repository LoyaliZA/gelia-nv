import React from 'react';
import { FileText, Download } from 'lucide-react';

const formatearTamano = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export default function MensajeArchivo({ adjunto }) {
    const url = route('mensajeria.adjuntos.show', adjunto.id);

    return (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-3 mt-1 p-3 rounded-xl theme-element border theme-border hover:border-[var(--color-primario)] transition-colors"
        >
            <FileText className="w-8 h-8 shrink-0 opacity-70" />
            <div className="flex-1 min-w-0">
                <p className="text-xs font-bold truncate">{adjunto.nombre_original || 'Archivo'}</p>
                <p className="text-[10px] opacity-60">{formatearTamano(adjunto.tamano)}</p>
            </div>
            <Download className="w-4 h-4 shrink-0 opacity-50" />
        </a>
    );
}
