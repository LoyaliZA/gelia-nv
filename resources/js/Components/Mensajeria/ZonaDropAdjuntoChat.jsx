import React from 'react';
import { Upload } from 'lucide-react';
import usePrepararAdjuntoDrop from '@/hooks/usePrepararAdjuntoDrop';
import ModalPrepararAdjunto from './ModalPrepararAdjunto';

export default function ZonaDropAdjuntoChat({
    children,
    onEnviarAdjunto,
    enviando = false,
    className = '',
}) {
    const {
        dragActivo,
        zoneProps,
        adjuntoPendiente,
        prepararAdjunto,
        cerrarPendiente,
        confirmarEnvio,
    } = usePrepararAdjuntoDrop({ onEnviarAdjunto });

    return (
        <div
            className={`gelia-drop-adjunto-zona relative flex flex-col min-h-0 min-w-0 ${className}`}
            {...zoneProps}
        >
            {dragActivo && (
                <div className="gelia-drop-adjunto-overlay" aria-hidden>
                    <div className="gelia-drop-adjunto-overlay__inner">
                        <Upload className="w-12 h-12" strokeWidth={1.5} />
                        <p className="text-sm font-black uppercase m-0 mt-3">Suelta el archivo aquí</p>
                        <p className="text-[11px] font-bold theme-text-muted m-0 mt-1">
                            PDF, Excel, imágenes y más · Vista previa antes de enviar
                        </p>
                    </div>
                </div>
            )}

            {typeof children === 'function' ? children({ prepararAdjunto }) : children}

            <ModalPrepararAdjunto
                pendiente={adjuntoPendiente}
                onCerrar={cerrarPendiente}
                onConfirmar={confirmarEnvio}
                enviando={enviando}
            />
        </div>
    );
}
