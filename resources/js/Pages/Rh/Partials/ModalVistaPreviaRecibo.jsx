import React from 'react';
import { Download } from 'lucide-react';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';

export default function ModalVistaPreviaRecibo({ abierto, onCerrar, previewUrl, downloadUrl, titulo = 'Vista previa — Recibo' }) {
    if (!abierto || !previewUrl) return null;

    return (
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4" style={{ backgroundColor: 'rgba(0,0,0,0.55)' }}>
            <div className="w-full max-w-5xl max-h-[92dvh] flex flex-col rounded-3xl theme-element border theme-border shadow-2xl overflow-hidden">
                <div className="px-5 py-4 border-b theme-border flex items-center justify-between gap-3">
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">{titulo}</h2>
                    <button type="button" onClick={onCerrar} className="text-[10px] font-black uppercase theme-text-muted">Cerrar</button>
                </div>
                <div className="flex-1 min-h-0">
                    <iframe
                        src={previewUrl}
                        title={titulo}
                        className="w-full border-0"
                        style={{ height: 'min(70vh, calc(100dvh - 12rem))' }}
                    />
                </div>
                <div className="p-5 flex flex-col-reverse sm:flex-row justify-end gap-3 border-t theme-border">
                    <button type="button" onClick={onCerrar} className={`${THEME_BTN_SECONDARY} min-h-[44px]`}>Cerrar</button>
                    {downloadUrl && (
                        <a href={downloadUrl} download className={`${THEME_BTN_PRIMARY} min-h-[44px] inline-flex items-center justify-center gap-2`}>
                            <Download className="w-4 h-4 shrink-0" /> Descargar PDF
                        </a>
                    )}
                </div>
            </div>
        </div>
    );
}
