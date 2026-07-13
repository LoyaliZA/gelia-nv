import React, { useState } from 'react';
import { Copy, Check, Download, Eye } from 'lucide-react';
import { BTN_SECONDARY, guiaPdfDe, formatearFechaHoraAuditoria } from './pedidosBmaStyles';

export default function SeccionGuiaRastreo({ pedido, onVerPdf, compact = false }) {
    const [copiado, setCopiado] = useState(false);
    const guiaPdf = guiaPdfDe(pedido);
    const tieneRastreo = Boolean(pedido?.numero_rastreo);
    const tienePdf = Boolean(guiaPdf);

    if (!tieneRastreo && !tienePdf) {
        return null;
    }

    const copiarGuia = async () => {
        if (!pedido.numero_rastreo) return;
        try {
            await navigator.clipboard.writeText(pedido.numero_rastreo);
            setCopiado(true);
            setTimeout(() => setCopiado(false), 2000);
        } catch {
            setCopiado(false);
        }
    };

    const abrirPdf = () => {
        if (guiaPdf && onVerPdf) {
            onVerPdf(guiaPdf);
        }
    };

    return (
        <div className={`${compact ? 'mb-4' : 'mb-6'} p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 space-y-3`}>
            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Guía de rastreo</p>

            {tieneRastreo && (
                <div className="flex items-center gap-3 flex-wrap">
                    <p className={`${compact ? 'text-lg' : 'text-2xl'} font-black font-mono theme-text-main m-0 tracking-wide break-all`}>
                        {pedido.numero_rastreo}
                    </p>
                    <button
                        type="button"
                        onClick={copiarGuia}
                        className={`${BTN_SECONDARY} flex items-center gap-2 text-xs outline-none shrink-0`}
                    >
                        {copiado ? <Check className="w-4 h-4 text-emerald-600" /> : <Copy className="w-4 h-4" />}
                        {copiado ? 'Copiado' : 'Copiar'}
                    </button>
                </div>
            )}

            {pedido.guia_subida_at && (
                <p className="text-[10px] font-bold theme-text-muted m-0 font-mono">
                    Liberado: {formatearFechaHoraAuditoria(pedido.guia_subida_at)}
                </p>
            )}

            {tienePdf && (
                <div className="flex flex-wrap gap-2">
                    {onVerPdf ? (
                        <button
                            type="button"
                            onClick={abrirPdf}
                            className={`${BTN_SECONDARY} inline-flex items-center gap-2 text-xs outline-none`}
                        >
                            <Eye className="w-4 h-4" /> Ver / Descargar guía
                        </button>
                    ) : (
                        <a
                            href={guiaPdf.url}
                            download={guiaPdf.nombre_original || 'guia.pdf'}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={`${BTN_SECONDARY} inline-flex items-center gap-2 text-xs outline-none no-underline`}
                        >
                            <Download className="w-4 h-4" /> Descargar guía
                        </a>
                    )}
                </div>
            )}
        </div>
    );
}
