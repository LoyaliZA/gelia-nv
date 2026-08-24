import React from 'react';
import { X, Cloud, HardDrive, AlertTriangle } from 'lucide-react';
import { etiquetaEstatusPedido } from '../pedidosBmaStyles';

/**
 * Encabezado siempre visible del formulario progresivo.
 * "Guardado" solo tras confirmación del servidor.
 */
export default function EncabezadoPedido({
    titulo = 'Pedido',
    folio = null,
    clienteNombre = null,
    estatus = null,
    esResguardo = false,
    estadoGuardado = null, // 'guardando' | 'guardado' | 'error' | null
    onClose,
}) {
    const labelGuardado = estadoGuardado === 'guardando'
        ? 'Guardando…'
        : estadoGuardado === 'guardado'
            ? 'Guardado'
            : estadoGuardado === 'error'
                ? 'Error al guardar'
                : null;

    return (
        <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
            <div className="min-w-0 space-y-1">
                <h2 className="text-xl md:text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0">
                    {titulo}{folio ? ` · ${folio}` : ''}
                    {esResguardo ? ' · Resguardo' : ''}
                </h2>
                {clienteNombre && (
                    <p className="text-sm font-bold theme-text-main m-0 truncate">{clienteNombre}</p>
                )}
                <div className="flex flex-wrap gap-x-4 gap-y-1 text-[10px] font-bold uppercase tracking-widest theme-text-muted">
                    {estatus && (
                        <span aria-label={`Estado: ${etiquetaEstatusPedido(estatus, { esResguardo })}`}>
                            {etiquetaEstatusPedido(estatus, { esResguardo })}
                        </span>
                    )}
                    {labelGuardado && (
                        <span
                            className="inline-flex items-center gap-1"
                            role="status"
                            aria-live="polite"
                        >
                            {estadoGuardado === 'error'
                                ? <AlertTriangle className="w-3 h-3" />
                                : estadoGuardado === 'guardando'
                                    ? <Cloud className="w-3 h-3 animate-pulse" />
                                    : <HardDrive className="w-3 h-3" />}
                            {labelGuardado}
                        </span>
                    )}
                </div>
            </div>
            <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                <X className="w-5 h-5" />
            </button>
        </div>
    );
}
