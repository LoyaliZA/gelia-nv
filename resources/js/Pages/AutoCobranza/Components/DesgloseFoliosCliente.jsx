import React from 'react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import {
    diasHastaVencimiento,
    esFacturaVencida,
    facturasOrdenadasCliente,
    facturasOrdenadasHistorialCliente,
    parseFechaCobranza,
} from '../../../utils/cobranzaCliente';

function formatearFecha(fechaIso) {
    const fecha = parseFechaCobranza(fechaIso);
    if (!fecha) return '—';
    return fecha.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: '2-digit' });
}

export default function DesgloseFoliosCliente({
    cliente,
    compacto = false,
    maxItems = null,
    onVerTodos = null,
    modoHistorial = false,
}) {
    const facturas = modoHistorial
        ? facturasOrdenadasHistorialCliente(cliente)
        : facturasOrdenadasCliente(cliente);
    const hoy = new Date();
    const visibles = maxItems != null ? facturas.slice(0, maxItems) : facturas;
    const ocultos = maxItems != null ? Math.max(0, facturas.length - maxItems) : 0;

    if (facturas.length === 0) {
        return (
            <p className="text-xs theme-text-muted uppercase tracking-widest font-bold">
                {modoHistorial ? 'Sin historial de folios' : 'Sin folios activos'}
            </p>
        );
    }

    return (
        <div className="space-y-2">
            {!compacto && (
                <h4 className="text-xs font-black uppercase tracking-widest theme-text-muted">
                    {modoHistorial ? `Historial de folios (${facturas.length})` : `Folios activos (${facturas.length})`}
                </h4>
            )}
            <div className="space-y-2">
                {visibles.map((factura) => {
                    const liquidada = Boolean(factura.pagada);
                    const vencida = !liquidada && esFacturaVencida(factura, hoy);
                    const dias = liquidada ? null : diasHastaVencimiento(factura.fecha_vencimiento, hoy);

                    return (
                        <div
                            key={factura.id}
                            className={`rounded-lg border px-3 py-2.5 ${
                                liquidada
                                    ? 'border-emerald-500/30 bg-emerald-500/5'
                                    : vencida
                                        ? 'border-red-500/30 bg-red-500/5'
                                        : 'theme-border bg-black/[0.02] dark:bg-white/[0.02]'
                            }`}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-1.5 flex-wrap">
                                        <span className="text-sm font-black theme-text-main">
                                            Folio {factura.folio}
                                        </span>
                                        {liquidada ? (
                                            <span className="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/10 text-emerald-500">
                                                <CheckCircle2 className="w-3 h-3" />
                                                Liquidado
                                            </span>
                                        ) : vencida && (
                                            <span className="inline-flex items-center gap-0.5 px-2 py-0.5 rounded text-[10px] font-black uppercase bg-red-500/10 text-red-500">
                                                <AlertTriangle className="w-3 h-3" />
                                                Vencido
                                            </span>
                                        )}
                                        {!liquidada && factura.tiene_abono && (
                                            <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-500/10 text-emerald-500">
                                                Abonado
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs theme-text-muted mt-1">
                                        {liquidada ? (
                                            <>Liquidado · vencía {formatearFecha(factura.fecha_vencimiento)}</>
                                        ) : (
                                            <>
                                                Vence {formatearFecha(factura.fecha_vencimiento)}
                                                {dias != null && (
                                                    <span className={vencida ? ' text-red-500 font-bold' : ''}>
                                                        {' '}({vencida ? `hace ${Math.abs(dias)} d` : `en ${dias} d`})
                                                    </span>
                                                )}
                                            </>
                                        )}
                                    </p>
                                </div>
                                <span className={`text-sm font-black shrink-0 ${
                                    liquidada
                                        ? 'text-emerald-500'
                                        : vencida
                                            ? 'text-red-500'
                                            : 'theme-text-main'
                                }`}>
                                    {liquidada ? formatoMoneda(factura.monto) : formatoMoneda(factura.monto)}
                                </span>
                            </div>
                        </div>
                    );
                })}
            </div>
            {ocultos > 0 && onVerTodos && (
                <button
                    type="button"
                    onClick={onVerTodos}
                    className="text-xs font-black uppercase tracking-widest text-[var(--color-primario)] hover:underline"
                >
                    Ver {ocultos} folio{ocultos === 1 ? '' : 's'} más
                </button>
            )}
        </div>
    );
}
