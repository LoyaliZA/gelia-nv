import React from 'react';
import { Download } from 'lucide-react';
import ActivosModalShell from './ActivosModalShell';
import { BTN_PRIMARY_CLASS, BTN_SECONDARY_CLASS } from './activosFormStyles';

export default function ModalVistaPreviaResponsiva({ abierto, onCerrar, previewUrl, downloadUrl, titulo = 'Vista previa — Responsiva' }) {
    if (!abierto || !previewUrl) return null;

    return (
        <ActivosModalShell title={titulo} onClose={onCerrar} size="max-w-5xl">
            <div className="flex flex-col flex-1 min-h-0">
                <div className="gelia-modal-body flex-1 min-h-0 p-0">
                    <iframe
                        src={previewUrl}
                        title={titulo}
                        className="w-full border-0"
                        style={{ height: 'min(70vh, calc(100dvh - 12rem))' }}
                    />
                </div>
                <div className="gelia-modal-footer p-5 md:p-6 flex flex-col-reverse sm:flex-row justify-end gap-3">
                    <button type="button" onClick={onCerrar} className={`${BTN_SECONDARY_CLASS} w-full sm:w-auto min-h-[44px]`}>
                        Cerrar
                    </button>
                    {downloadUrl && (
                        <a
                            href={downloadUrl}
                            download
                            className={`${BTN_PRIMARY_CLASS} w-full sm:w-auto min-h-[44px] inline-flex items-center justify-center gap-2`}
                        >
                            <Download className="w-4 h-4 shrink-0" />
                            Descargar PDF
                        </a>
                    )}
                </div>
            </div>
        </ActivosModalShell>
    );
}
