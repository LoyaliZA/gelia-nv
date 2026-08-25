import React from 'react';
import { X, AlertTriangle, CheckCircle2 } from 'lucide-react';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import { formatearFechaHoraAuditoria, BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';

/**
 * Encabezado + orientación de la tarea de revisión (sin códigos técnicos crudos).
 * Historial de errores/cambios: consultar en Bitácora del listado, no aquí.
 */
export default function EncabezadoRevisionPedido({
    pedido,
    badge,
    badgeHito,
    badgeRemision,
    reRevision,
    esRechazado,
    pagoValidado,
    procesando,
    can,
    onClose,
    onResolverSaf,
}) {
    const fase = pedido.estatus?.fase_ciclo;
    let indicacion = 'Revisa que los comprobantes vigentes cubran el total del pedido.';
    if (reRevision) {
        indicacion = 'Ventas sustituyó un comprobante; vuelve a validar la cobertura.';
    } else if (esRechazado) {
        indicacion = 'Pedido con observación: Ventas debe sustituir comprobantes.';
    } else if (pagoValidado) {
        indicacion = 'Pago validado. Continúa con remisión y aprobación si corresponde.';
    } else if (badgeRemision) {
        indicacion = 'Corrige la remisión reportada y vuelve a aprobar.';
    }

    const erroresAbiertos = (pedido.errores || []).filter((e) => e.estatus === 'abierto');

    return (
        <>
            <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                <div className="min-w-0">
                    <p className="text-[10px] font-black uppercase theme-text-muted m-0 mb-1">Revisión de pedido</p>
                    <EncabezadoFolioPedido pedido={pedido} size="lg" />
                    {pedido.cliente?.nombre && (
                        <p className="text-sm font-bold theme-text-main m-0 mt-1 truncate">
                            {pedido.cliente.nombre}
                            {pedido.cliente.numero_cliente ? ` · ${pedido.cliente.numero_cliente}` : ''}
                        </p>
                    )}
                    <div className="flex flex-wrap gap-2 mt-2">
                        <span className={`${badge.className} inline-flex`} style={badge.style}>{badge.label}</span>
                        {badgeHito && (
                            <span className={`${badgeHito.className} inline-flex`} style={badgeHito.style}>{badgeHito.label}</span>
                        )}
                        {badgeRemision && (
                            <span className={`${badgeRemision.className} inline-flex`} style={badgeRemision.style}>{badgeRemision.label}</span>
                        )}
                    </div>
                </div>
                <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                    <X className="w-5 h-5" />
                </button>
            </div>

            <div className={`mx-5 md:mx-6 mt-4 p-4 rounded-xl border theme-border ${
                esRechazado ? 'bg-red-500/10 border-red-500/30'
                    : pedido.es_resguardo ? 'bg-blue-500/10 border-blue-500/30'
                        : 'theme-element'
            }`}
            >
                <p className="text-sm font-bold theme-text-main m-0">{indicacion}</p>
                {pedido.origen?.nombre && (
                    <p className="text-xs font-black uppercase text-blue-600 mt-2 m-0">Tipo: {pedido.origen.nombre}</p>
                )}
                {pedido.es_resguardo && (
                    <p className="text-xs font-black uppercase text-blue-600 mt-1 m-0">En resguardo — mercancía bloqueada en almacén</p>
                )}
                {badgeRemision && (
                    <div className="mt-3 p-3 rounded-xl bg-orange-500/10 border border-orange-500/30 space-y-1">
                        <p className="text-sm font-bold text-orange-600 m-0 flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4" /> Corregir remisión
                        </p>
                        <p className="text-sm font-bold theme-text-main m-0">
                            {pedido.detalle_error_datos || pedido.motivo_rechazo || 'CEDIS/Delegado reportó error en la remisión. Suba la remisión correcta y apruebe.'}
                        </p>
                    </div>
                )}
                {esRechazado && pedido.motivo_rechazo && (
                    <p className="text-sm text-red-500 font-bold mt-2 m-0 flex items-start gap-2">
                        <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
                        Motivo: {pedido.motivo_rechazo}
                    </p>
                )}
                {(fase === 'INCIDENCIA_CEDIS' || pedido.detalle_incidencia_empaque) && (
                    <div className="mt-3 p-3 rounded-xl bg-orange-500/10 border border-orange-500/30 space-y-1">
                        <p className="text-sm font-bold text-orange-600 m-0 flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4" /> Error reportado en CEDIS
                        </p>
                        <p className="text-sm font-bold theme-text-main m-0">{pedido.detalle_incidencia_empaque}</p>
                        {pedido.incidencia_empaque_at && (
                            <p className="text-xs theme-text-muted font-bold m-0 font-mono">
                                Reportado por {(pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name) || '—'} el {formatearFechaHoraAuditoria(pedido.incidencia_empaque_at)}
                            </p>
                        )}
                    </div>
                )}
                {erroresAbiertos.length > 0 && (
                    <p className="text-xs font-bold text-amber-700 mt-2 m-0 flex items-start gap-1">
                        <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                        Hay {erroresAbiertos.length} error(es) de datos abierto(s). Consulta el detalle en Bitácora.
                    </p>
                )}
                {can('control_pedidos.auditar') && (pedido.saf_incidencias_abiertas || []).length > 0 && (
                    <div className="mt-3 p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 space-y-2">
                        <p className="text-sm font-bold text-amber-700 m-0 flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4" /> Alerta de saldos a favor (no detiene el pedido)
                        </p>
                        {(pedido.saf_incidencias_abiertas || []).map((inc) => (
                            <div key={inc.id} className="space-y-2">
                                <p className="text-sm font-bold theme-text-main m-0">{inc.descripcion}</p>
                                <button
                                    type="button"
                                    className={`${BTN_SECONDARY} text-xs`}
                                    disabled={procesando}
                                    onClick={() => onResolverSaf(inc)}
                                >
                                    Marcar revisado y continuar
                                </button>
                            </div>
                        ))}
                    </div>
                )}
                {pagoValidado && (
                    <p className="text-xs text-emerald-600 font-bold mt-2 m-0 flex items-center gap-1">
                        <CheckCircle2 className="w-4 h-4" />
                        Pago validado el {formatearFechaHoraAuditoria(pedido.pago_validado_at)}
                        {(pedido.pago_validado_por?.name || pedido.pagoValidadoPor?.name) && ` por ${pedido.pago_validado_por?.name || pedido.pagoValidadoPor?.name}`}
                    </p>
                )}
            </div>
        </>
    );
}
