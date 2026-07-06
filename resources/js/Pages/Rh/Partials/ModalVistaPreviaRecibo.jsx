import React from 'react';
import { createPortal } from 'react-dom';
import { Download, ExternalLink, X } from 'lucide-react';
import { THEME_BTN_ICON, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

export default function ModalVistaPreviaRecibo({
    abierto,
    onCerrar,
    previewUrl,
    downloadUrl,
    titulo = 'Vista previa — Recibo',
    nombreArchivo,
}) {
    if (!abierto || !previewUrl || typeof document === 'undefined') return null;

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`}
            role="dialog"
            aria-modal="true"
            aria-labelledby="recibo-preview-title"
            onClick={onCerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-5xl w-full modal-pop text-left`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between gap-3 p-5 md:p-6 border-b theme-border shrink-0">
                    <h2 id="recibo-preview-title" className="text-sm md:text-base font-black uppercase tracking-widest theme-text-main m-0 leading-tight">
                        {titulo}
                    </h2>
                    <button type="button" onClick={onCerrar} className={THEME_BTN_ICON} aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="gelia-modal-body flex-1 min-h-0 p-0">
                    <iframe
                        src={previewUrl}
                        title={titulo}
                        className="w-full border-0 block"
                        style={{ height: 'min(70vh, calc(100dvh - 14rem))' }}
                    />
                </div>

                <div className="gelia-modal-footer p-5 md:p-6 flex flex-col-reverse sm:flex-row justify-end gap-3">
                    <button type="button" onClick={onCerrar} className={`${THEME_BTN_SECONDARY} w-full sm:w-auto min-h-[44px]`}>
                        Cerrar
                    </button>
                    <a
                        href={previewUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={`${THEME_BTN_SECONDARY} w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2`}
                    >
                        <ExternalLink className="w-4 h-4 shrink-0" />
                        Abrir en nueva pestaña
                    </a>
                    {downloadUrl && (
                        <a
                            href={downloadUrl}
                            download={nombreArchivo || undefined}
                            className={`${THEME_BTN_PRIMARY} w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2`}
                        >
                            <Download className="w-4 h-4 shrink-0" />
                            Descargar PDF
                        </a>
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
