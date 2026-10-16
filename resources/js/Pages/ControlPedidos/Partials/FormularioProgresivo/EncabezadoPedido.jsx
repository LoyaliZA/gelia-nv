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
    requiereLogistica = true,
    estadoGuardado = null, // 'guardando' | 'guardado' | 'pendiente' | 'error' | 'conflicto' | null
    onClose,
}) {
    const labelGuardado = estadoGuardado === 'guardando'
        ? 'Guardando…'
        : estadoGuardado === 'guardado'
            ? 'Guardado'
            : estadoGuardado === 'pendiente'
                ? 'Cambios pendientes'
                : estadoGuardado === 'conflicto'
                    ? 'Cambio en otra sesión'
                    : estadoGuardado === 'error'
                        ? 'Error al guardar'
                        : null;

    return (
        <div className="gelia-pedidos-captura-cabecera border-b theme-border">
            <div className="min-w-0 space-y-1">
                <h2 className="text-base md:text-lg font-bold theme-text-main m-0 break-words">
                    {titulo}{folio ? ` · ${folio}` : ''}
                    {esResguardo ? ' · Resguardo' : ''}
                </h2>
                {clienteNombre && (
                    <p className="text-sm font-medium theme-text-main m-0 break-words">{clienteNombre}</p>
                )}
                <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs font-medium theme-text-muted">
                    {estatus && (
                        <span aria-label={`Estado: ${etiquetaEstatusPedido(estatus, { esResguardo, requiereLogistica })}`}>
                            {etiquetaEstatusPedido(estatus, { esResguardo, requiereLogistica })}
                        </span>
                    )}
                    {labelGuardado && (
                        <span
                            className="inline-flex items-center gap-1"
                            role="status"
                            aria-live="polite"
                        >
                            {estadoGuardado === 'error' || estadoGuardado === 'conflicto'
                                ? <AlertTriangle className="w-3 h-3" />
                                : estadoGuardado === 'guardando'
                                    ? <Cloud className="w-3 h-3 animate-pulse" />
                                    : <HardDrive className="w-3 h-3" />}
                            {labelGuardado}
                        </span>
                    )}
                </div>
            </div>
            <button type="button" onClick={onClose} className="p-2 min-h-[44px] min-w-[44px] rounded-full theme-text-muted hover:theme-text-main shrink-0" aria-label="Cerrar">
                <X className="w-5 h-5" />
            </button>
        </div>
    );
}
