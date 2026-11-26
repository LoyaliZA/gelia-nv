import React, { useState } from 'react';
import { ChevronDown, Copy, Check, Download, Eye, FileText } from 'lucide-react';
import { BTN_SECONDARY, guiaPdfDe, formatearFechaHoraAuditoria, etiquetaOrigenGuia } from './pedidosBmaStyles';

function GuiasDeEnvio({ guias, onVerPdf }) {
    const disponibles = guias.length === 1 ? '1 disponible' : `${guias.length} disponibles`;
    const foco = 'focus-visible:outline-2 focus-visible:outline-[var(--color-primario)]';

    return (
        <details className="group/guias border-t theme-border pt-4">
            <summary className={`flex min-h-11 cursor-pointer list-none flex-wrap items-center justify-between gap-x-2 gap-y-1 rounded-lg border theme-border theme-element px-3 py-2 theme-text-main [&::-webkit-details-marker]:hidden ${foco}`}>
                <span className="flex items-center gap-2 text-sm font-semibold">
                    <FileText size={16} className="text-[var(--color-primario)]" aria-hidden="true" />
                    Guías de envío
                </span>
                <span className="flex items-center gap-2 text-xs theme-text-muted">
                    {disponibles}
                    <ChevronDown size={16} className="group-open/guias:rotate-180" aria-hidden="true" />
                </span>
            </summary>
            <div
                role="region"
                aria-label="Guías disponibles por caja"
                tabIndex={0}
                className={`mt-2 max-h-52 overflow-y-auto rounded-lg p-1 [scrollbar-width:thin] ${foco}`}
            >
                <ul className="m-0 list-none space-y-2 p-0">
                    {guias.map(({ cajaId, nombreCaja, envio, documento }) => (
                        <li key={cajaId} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border theme-border theme-element px-2 py-1">
                            <div className="flex min-w-0 items-center gap-2">
                                <FileText size={14} className="shrink-0 text-[var(--color-primario)]" aria-hidden="true" />
                                <div>
                                    <p className="m-0 text-xs font-semibold theme-text-main">Envío {envio}</p>
                                    <p className="m-0 text-xs theme-text-muted">{nombreCaja}</p>
                                </div>
                            </div>
                            <div className="ml-auto flex shrink-0 gap-1">
                                <button
                                    type="button"
                                    className={`${BTN_SECONDARY} inline-flex min-h-11 items-center gap-1 px-2 text-xs ${foco}`}
                                    aria-label={[`Ver guía del envío ${envio}`, nombreCaja].filter(Boolean).join(', ')}
                                    onClick={() => onVerPdf(documento)}
                                >
                                    <Eye size={14} aria-hidden="true" /> Ver
                                </button>
                                <span className="group/descarga relative">
                                    <a
                                        className={`grid size-11 place-items-center rounded-[var(--theme-radius-input)] border border-[var(--theme-element-border)] bg-[var(--theme-surface-solid)] p-0 theme-text-main hover:border-[var(--color-primario)] hover:bg-[var(--theme-element-bg)] ${foco}`}
                                        href={documento.url}
                                        download={documento.nombre_original || 'guia.pdf'}
                                        aria-label={[`Descargar guía del envío ${envio}`, nombreCaja].filter(Boolean).join(', ')}
                                    >
                                        <Download size={16} aria-hidden="true" />
                                    </a>
                                    <span role="tooltip" className="pointer-events-none absolute right-full top-1/2 z-10 mr-1 hidden -translate-y-1/2 whitespace-nowrap rounded-md border theme-border bg-[var(--theme-surface-solid)] px-2 py-1 text-xs theme-text-main group-hover/descarga:block group-focus-within/descarga:block">
                                        Descargar guía
                                    </span>
                                </span>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </details>
    );
}

export default function SeccionGuiaRastreo({ pedido, onVerPdf, compact = false, guiasPorCaja = null }) {
    const [copiado, setCopiado] = useState(false);
    const guiaPdf = guiasPorCaja === null ? guiaPdfDe(pedido) : null;
    const tieneGuiasPorCaja = Boolean(guiasPorCaja?.length);
    const tieneRastreo = Boolean(pedido?.numero_rastreo);
    const tienePdf = Boolean(guiaPdf);

    if (!tieneRastreo && !tienePdf && !tieneGuiasPorCaja) {
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
            <p className={tieneGuiasPorCaja ? 'text-xs theme-text-muted m-0' : 'text-[9px] font-black uppercase theme-text-muted m-0'}>
                {tieneGuiasPorCaja ? 'Guías generadas por la empresa' : etiquetaOrigenGuia(pedido)}
            </p>

            {tieneRastreo && (
                <div className="flex items-center gap-3 flex-wrap">
                    <p className={`${compact ? 'text-lg' : 'text-2xl'} font-black font-mono theme-text-main m-0 tracking-wide break-all`}>
                        {pedido.numero_rastreo}
                    </p>
                    <button
                        type="button"
                        onClick={copiarGuia}
                        className={`${BTN_SECONDARY} flex items-center gap-2 text-xs outline-none shrink-0 min-h-[44px]`}
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

            {tieneGuiasPorCaja && <GuiasDeEnvio key={pedido.id} guias={guiasPorCaja} onVerPdf={onVerPdf} />}

            {tienePdf && (
                <div className="flex flex-wrap gap-2">
                    {onVerPdf ? (
                        <button
                            type="button"
                            onClick={abrirPdf}
                            className={`${BTN_SECONDARY} inline-flex items-center gap-2 text-xs outline-none min-h-[44px]`}
                        >
                            <Eye className="w-4 h-4" /> Ver / Descargar guía
                        </button>
                    ) : (
                        <a
                            href={guiaPdf.url}
                            download={guiaPdf.nombre_original || 'guia.pdf'}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={`${BTN_SECONDARY} inline-flex items-center gap-2 text-xs outline-none no-underline min-h-[44px]`}
                        >
                            <Download className="w-4 h-4" /> Descargar guía
                        </a>
                    )}
                </div>
            )}
        </div>
    );
}
