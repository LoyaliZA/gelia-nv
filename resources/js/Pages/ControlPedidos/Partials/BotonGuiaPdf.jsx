import React from 'react';
import { Download } from 'lucide-react';
import { BTN_SECONDARY, guiaPdfDe } from './pedidosBmaStyles';

export default function BotonGuiaPdf({ pedido, onVerPdf, compact = false, className = '' }) {
    const guiaPdf = guiaPdfDe(pedido);

    if (!guiaPdf) return null;

    return (
        <button
            type="button"
            onClick={() => onVerPdf(guiaPdf)}
            className={`${BTN_SECONDARY} flex items-center gap-1.5 ${compact ? 'text-[10px]' : 'text-xs'} outline-none ${className}`}
        >
            <Download className="w-3.5 h-3.5" />
            Descargar guía
        </button>
    );
}
