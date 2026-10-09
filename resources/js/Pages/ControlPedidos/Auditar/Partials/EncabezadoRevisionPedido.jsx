import React from 'react';
import { X, AlertTriangle, CheckCircle2 } from 'lucide-react';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import { formatearFechaHoraAuditoria, BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';

export function indicacionRevisionPedido({ reRevision, esRechazado, pagoValidado, badgeRemision }) {
    if (reRevision) return 'Ventas sustituyó un comprobante; vuelve a validar la cobertura.';
    if (esRechazado) return 'Pedido con observación: Ventas debe sustituir comprobantes.';
    if (pagoValidado) return 'Pago validado. Continúa con remisión y aprobación si corresponde.';
    if (badgeRemision) return 'Corrige la remisión reportada y vuelve a aprobar.';
    return 'Revisa que los comprobantes vigentes cubran el total del pedido.';
}

export default function EncabezadoRevisionPedido({ pedido, badge, badgeHito, badgeRemision, pagoValidado, procesando, onClose, acciones }) {
    return (
        <header className="gelia-pedidos-detalle-cabecera border-b theme-border">
            <div className="gelia-pedidos-detalle-identidad min-w-0">
                <h2 id="auditoria-revision-titulo" className="text-xs font-semibold theme-text-muted m-0 mb-1">Revisión de pedido</h2>
                <EncabezadoFolioPedido pedido={pedido} size="lg" />
            </div>
            <div className="gelia-pedidos-detalle-cliente min-w-0">
                <p className="text-sm font-semibold theme-text-main m-0 break-words">{pedido.cliente?.nombre || 'Sin cliente'}</p>
                <p className="text-xs theme-text-muted m-0 mt-1">{pedido.cliente?.numero_cliente ? `Cliente ${pedido.cliente.numero_cliente}` : 'Sin número de cliente'}{pedido.origen?.nombre ? ` · ${pedido.origen.nombre}` : ''}</p>
                {pedido.es_resguardo && <p className="text-xs theme-text-info m-0 mt-1">En resguardo · mercancía bloqueada en almacén</p>}
                <div className="gelia-pedidos-detalle-estados min-w-0 space-y-1.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className={badge.className} style={badge.style}>{badge.label}</span>
                        {badgeHito && <span className={badgeHito.className} style={badgeHito.style}>{badgeHito.label}</span>}
                        {badgeRemision && <span className={badgeRemision.className} style={badgeRemision.style}>{badgeRemision.label}</span>}
                    </div>
                    {pagoValidado && <p className="text-xs theme-text-muted m-0 flex items-start gap-1.5"><CheckCircle2 className="w-3.5 h-3.5 mt-0.5 shrink-0 theme-text-exito" aria-hidden="true" /><span>Pago validado el {formatearFechaHoraAuditoria(pedido.pago_validado_at)}{(pedido.pago_validado_por?.name || pedido.pagoValidadoPor?.name) && ` por ${pedido.pago_validado_por?.name || pedido.pagoValidadoPor?.name}`}</span></p>}
                </div>
            </div>
            {acciones}
            <button type="button" disabled={procesando} onClick={onClose} className="gelia-pedidos-detalle-cerrar p-2 rounded-full theme-text-muted hover:theme-text-main outline-none inline-flex items-center justify-center" aria-label="Cerrar revisión"><X className="w-5 h-5" aria-hidden="true" /></button>
        </header>
    );
}

/** Avisos dentro de la consulta: siguen visibles sin restar altura fija al modal. */
export function AvisosRevisionPedido({ pedido, badgeRemision, esRechazado, procesando, can, onResolverSaf }) {
    const fase = pedido.estatus?.fase_ciclo;
    const erroresAbiertos = (pedido.errores || []).filter((e) => e.estatus === 'abierto');
    const hayAvisos = badgeRemision || (esRechazado && pedido.motivo_rechazo)
        || fase === 'INCIDENCIA_CEDIS' || pedido.detalle_incidencia_empaque || erroresAbiertos.length
        || (can('control_pedidos.auditar') && (pedido.saf_incidencias_abiertas || []).length);
    if (!hayAvisos) return null;
    return (
            <div className={`p-4 rounded-xl border theme-border ${
                esRechazado ? 'gelia-pedidos-auditoria-aviso-peligro'
                    : pedido.es_resguardo ? 'gelia-pedidos-auditoria-aviso-info'
                        : 'theme-element'
            }`}
            >
                {badgeRemision && (
                    <div className="mt-3 p-3 rounded-xl border gelia-pedidos-auditoria-aviso space-y-1">
                        <p className="text-sm font-bold theme-text-aviso m-0 flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4" /> Corregir remisión
                        </p>
                        <p className="text-sm font-bold theme-text-main m-0">
                            {pedido.detalle_error_datos || pedido.motivo_rechazo || 'CEDIS/Delegado reportó error en la remisión. Suba la remisión correcta y apruebe.'}
                        </p>
                    </div>
                )}
                {esRechazado && pedido.motivo_rechazo && (
                    <p className="text-sm theme-text-peligro font-bold mt-2 m-0 flex items-start gap-2">
                        <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
                        Motivo: {pedido.motivo_rechazo}
                    </p>
                )}
                {(fase === 'INCIDENCIA_CEDIS' || pedido.detalle_incidencia_empaque) && (
                    <div className="mt-3 p-3 rounded-xl border gelia-pedidos-auditoria-aviso space-y-1">
                        <p className="text-sm font-bold theme-text-aviso m-0 flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4" /> Error reportado en CEDIS
                        </p>
                        <p className="text-sm font-bold theme-text-main m-0">{pedido.detalle_incidencia_empaque}</p>
                        {pedido.incidencia_empaque_at && (
                            <p className="text-xs theme-text-muted font-bold m-0 ">
                                Reportado por {(pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name) || '—'} el {formatearFechaHoraAuditoria(pedido.incidencia_empaque_at)}
                            </p>
                        )}
                    </div>
                )}
                {erroresAbiertos.length > 0 && (
                    <p className="text-xs font-bold theme-text-aviso mt-2 m-0 flex items-start gap-1">
                        <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                        Hay {erroresAbiertos.length} error(es) de datos abierto(s). Consulta el detalle en Bitácora.
                    </p>
                )}
                {can('control_pedidos.auditar') && (pedido.saf_incidencias_abiertas || []).length > 0 && (
                    <div className="mt-3 p-3 rounded-xl border gelia-pedidos-auditoria-aviso space-y-2">
                        <p className="text-sm font-bold theme-text-aviso m-0 flex items-center gap-2">
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

            </div>
    );
}
