import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Download, X } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, BTN_PRIMARY, BTN_SECONDARY } from './pedidosBmaStyles';

const esPdf = (doc) => {
    const nombre = String(doc?.nombre_original || doc?.url || '').toLowerCase();
    const mime = doc?.mime_type || doc?.mime || '';
    return nombre.endsWith('.pdf') || mime === 'application/pdf';
};

export default function ModalVistaPreviaDocumento({ abierto, documento, onClose }) {
    useEffect(() => {
        if (abierto) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
        return () => { document.body.style.overflow = ''; };
    }, [abierto]);

    if (!abierto || !documento?.url) return null;

    const titulo = documento.nombre_original || 'Vista previa';
    const pdf = esPdf(documento);

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-5xl w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <h2 className="text-lg font-black italic uppercase theme-text-main m-0 truncate">{titulo}</h2>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="gelia-modal-body flex-1 min-h-0 p-0 flex items-center justify-center bg-black/5">
                    {pdf ? (
                        <iframe
                            src={documento.url}
                            title={titulo}
                            className="w-full border-0"
                            style={{ height: 'min(70vh, calc(100dvh - 12rem))' }}
                        />
                    ) : (
                        <img
                            src={documento.url}
                            alt={titulo}
                            className="max-w-full max-h-[70vh] object-contain p-4"
                        />
                    )}
                </div>
                <div className="gelia-modal-footer p-5 md:p-6 flex flex-col-reverse sm:flex-row justify-end gap-3 border-t theme-border">
                    <button type="button" onClick={onClose} className={`${BTN_SECONDARY} w-full sm:w-auto min-h-[44px]`}>
                        Cerrar
                    </button>
                    <a
                        href={documento.url}
                        download={documento.nombre_original || undefined}
                        className={`${BTN_PRIMARY} w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2`}
                    >
                        <Download className="w-4 h-4 shrink-0" />
                        Descargar
                    </a>
                </div>
            </div>
        </div>,
        document.body
    );
}

export function MiniaturaDocumento({ documento, onVer, className = 'block w-20 h-20 rounded-xl overflow-hidden border theme-border theme-element cursor-pointer' }) {
    const pdf = esPdf(documento);

    return (
        <button
            type="button"
            onClick={() => onVer(documento)}
            className={`${className} outline-none hover:border-[var(--color-primario)] transition-colors`}
            title={documento.nombre_original || 'Ver documento'}
        >
            {pdf ? (
                <div className="w-full h-full flex items-center justify-center theme-element text-[9px] font-black uppercase theme-text-muted">
                    PDF
                </div>
            ) : (
                <img src={documento.url} alt={documento.nombre_original} className="w-full h-full object-cover" />
            )}
        </button>
    );
}
