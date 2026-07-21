import React, { useState } from 'react';
import { Copy, Check } from 'lucide-react';
import { BTN_SECONDARY } from './pedidosBmaStyles';

export default function BotonCopiar({ texto, etiqueta = 'Copiar', className = '' }) {
    const [copiado, setCopiado] = useState(false);

    if (!texto) return null;

    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(String(texto));
            setCopiado(true);
            setTimeout(() => setCopiado(false), 2000);
        } catch {
            setCopiado(false);
        }
    };

    return (
        <button
            type="button"
            onClick={copiar}
            className={`${BTN_SECONDARY} inline-flex items-center gap-1.5 text-[10px] outline-none shrink-0 ${className}`}
            aria-label={`${etiqueta}: ${texto}`}
        >
            {copiado ? <Check className="w-3.5 h-3.5 text-emerald-600" /> : <Copy className="w-3.5 h-3.5" />}
            {copiado ? 'Copiado' : etiqueta}
        </button>
    );
}
