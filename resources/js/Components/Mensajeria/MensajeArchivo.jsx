import React, { useState } from 'react';
import { FileText, Download, Eye } from 'lucide-react';
import VisorDocumentoMensaje from './VisorDocumentoMensaje';
import {
    puedePrevisualizarAdjunto,
    urlAdjuntoMensajeria,
    manejarClickPrevisualizar,
} from '@/utils/adjuntoPreview';

const formatearTamano = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export default function MensajeArchivo({ adjunto }) {
    const [previewAbierto, setPreviewAbierto] = useState(false);
    const puedeVer = puedePrevisualizarAdjunto(adjunto);
    const downloadUrl = urlAdjuntoMensajeria(adjunto.id);

    const abrirPreview = (e) => {
        manejarClickPrevisualizar(e, () => setPreviewAbierto(true));
    };

    const descargar = (e) => {
        e.preventDefault();
        e.stopPropagation();
        const enlace = document.createElement('a');
        enlace.href = downloadUrl;
        enlace.download = adjunto.nombre_original || 'archivo';
        enlace.rel = 'noopener noreferrer';
        document.body.appendChild(enlace);
        enlace.click();
        enlace.remove();
    };

    if (puedeVer) {
        return (
            <>
                <div className="flex items-center gap-2 mt-1">
                    <button
                        type="button"
                        onClick={abrirPreview}
                        className="flex flex-1 items-center gap-3 p-3 rounded-xl theme-element border theme-border hover:border-[var(--color-primario)] transition-colors text-left outline-none min-w-0"
                    >
                        <FileText className="w-8 h-8 shrink-0 opacity-70" />
                        <div className="flex-1 min-w-0">
                            <p className="text-xs font-bold truncate m-0 theme-text-main">
                                {adjunto.nombre_original || 'Archivo'}
                            </p>
                            <p className="text-[10px] theme-text-muted m-0">{formatearTamano(adjunto.tamano)}</p>
                            <p className="text-[10px] font-bold m-0 mt-1" style={{ color: 'var(--color-primario)' }}>
                                Toca para vista previa
                            </p>
                        </div>
                        <Eye className="w-4 h-4 shrink-0 opacity-60" />
                    </button>
                    <button
                        type="button"
                        onClick={descargar}
                        className="p-3 rounded-xl theme-element border theme-border theme-text-muted hover:border-[var(--color-primario)] shrink-0 outline-none"
                        title="Descargar"
                        aria-label="Descargar archivo"
                    >
                        <Download className="w-4 h-4" />
                    </button>
                </div>

                {previewAbierto && (
                    <VisorDocumentoMensaje
                        adjunto={adjunto}
                        onCerrar={() => setPreviewAbierto(false)}
                    />
                )}
            </>
        );
    }

    return (
        <a
            href={downloadUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-3 mt-1 p-3 rounded-xl theme-element border theme-border hover:border-[var(--color-primario)] transition-colors"
        >
            <FileText className="w-8 h-8 shrink-0 opacity-70" />
            <div className="flex-1 min-w-0">
                <p className="text-xs font-bold truncate m-0">{adjunto.nombre_original || 'Archivo'}</p>
                <p className="text-[10px] opacity-60 m-0">{formatearTamano(adjunto.tamano)}</p>
            </div>
            <Download className="w-4 h-4 shrink-0 opacity-50" />
        </a>
    );
}
