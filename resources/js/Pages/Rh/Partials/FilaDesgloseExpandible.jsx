import React, { useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { formatoMoneda } from '../../../utils/formatoMoneda';

function LineaDetalle({ label, value }) {
    return (
        <div className="flex justify-between gap-3 text-[11px] py-1">
            <span className="theme-text-muted">{label}</span>
            <span className="theme-text-main font-medium text-right">{value}</span>
        </div>
    );
}

function LineaMovimiento({ fecha, folio, concepto, monto, negativo = false, extra }) {
    const prefijo = negativo && monto > 0 ? '−' : '';

    return (
        <div className="py-2 border-b border-dashed theme-border last:border-0">
            <div className="flex justify-between items-start gap-2">
                <div className="min-w-0 flex-1">
                    <p className="text-[10px] font-mono theme-text-muted m-0">{fecha || '—'}</p>
                    <p className="text-[11px] font-bold theme-text-main m-0 mt-0.5 truncate">
                        {folio ? `${folio} · ` : ''}{concepto}
                    </p>
                    {extra && <p className="text-[10px] theme-text-muted m-0 mt-0.5">{extra}</p>}
                </div>
                <span className={`text-[11px] font-black shrink-0 ${negativo ? 'text-red-600' : 'theme-text-main'}`}>
                    {prefijo}{formatoMoneda(monto)}
                </span>
            </div>
        </div>
    );
}

function SinRegistros() {
    return <p className="text-[11px] theme-text-muted italic m-0 py-1">Sin registros en el periodo</p>;
}

export default function FilaDesgloseExpandible({
    label,
    value,
    detalle = [],
    movimientos = [],
    destacado = false,
    defaultAbierto = false,
    cargando = false,
}) {
    const [abierto, setAbierto] = useState(defaultAbierto);
    const expandible = detalle.length > 0 || movimientos.length > 0 || cargando;

    const toggle = () => {
        if (!expandible || cargando) return;
        setAbierto((prev) => !prev);
    };

    return (
        <div className={`border-b theme-border last:border-0 ${destacado ? 'bg-black/[0.02] dark:bg-white/[0.02] -mx-4 px-4' : ''}`}>
            <button
                type="button"
                onClick={toggle}
                disabled={!expandible || cargando}
                aria-expanded={expandible ? abierto : undefined}
                className={`w-full flex items-center justify-between gap-3 py-2.5 text-left ${
                    expandible && !cargando ? 'hover:bg-black/[0.02] dark:hover:bg-white/[0.02] cursor-pointer' : 'cursor-default'
                }`}
            >
                <span className="theme-text-muted uppercase text-[10px] tracking-wide shrink-0">{label}</span>
                <span className="flex items-center gap-2 min-w-0">
                    <span className={`theme-text-main text-right text-xs truncate ${destacado ? 'font-black text-sm' : 'font-bold'}`}>
                        {value}
                    </span>
                    {cargando && (
                        <span className="text-[9px] font-bold uppercase theme-text-muted shrink-0">Cargando…</span>
                    )}
                    {expandible && !cargando && (
                        <span className="p-1 rounded-lg theme-element border theme-border shrink-0" aria-hidden>
                            {abierto ? (
                                <ChevronUp className="w-3.5 h-3.5 theme-text-muted" />
                            ) : (
                                <ChevronDown className="w-3.5 h-3.5 theme-text-muted" />
                            )}
                        </span>
                    )}
                </span>
            </button>
            {expandible && abierto && (
                <div className="pb-3 pl-1 pr-1 border-t theme-border border-dashed mt-0.5 pt-2 max-h-48 overflow-y-auto">
                    {cargando ? (
                        <p className="text-[11px] theme-text-muted italic m-0 py-1">Cargando detalle…</p>
                    ) : (
                        <>
                            {detalle.map((item) => (
                                <LineaDetalle key={`${item.label}-${item.value}`} label={item.label} value={item.value} />
                            ))}
                            {movimientos.length > 0 ? (
                                movimientos.map((item) => (
                                    <LineaMovimiento
                                        key={`${item.folio || item.fecha}-${item.concepto}-${item.monto}`}
                                        fecha={item.fecha}
                                        folio={item.folio}
                                        concepto={item.concepto}
                                        monto={item.monto}
                                        negativo={item.negativo}
                                        extra={item.extra}
                                    />
                                ))
                            ) : (
                                detalle.length === 0 && <SinRegistros />
                            )}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
