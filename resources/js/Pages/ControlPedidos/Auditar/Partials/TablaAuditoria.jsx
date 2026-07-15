import React from 'react';
import { Eye, AlertTriangle } from 'lucide-react';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { badgeAuditoriaSemantico, formatearMoneda, etiquetaAlmacen, formatearFechaNegocio } from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';

function CardAuditoria({ pedido, badge, esRechazado, esIncidenciaCedis, onRevisar }) {
    return (
        <div className={`${geliaCardClass()} p-4 space-y-3 ${esRechazado ? 'ring-1 ring-red-500/30' : ''} ${esIncidenciaCedis ? 'ring-1 ring-orange-500/30' : ''} ${pedido.es_resguardo ? 'ring-2 ring-blue-500/40 bg-blue-500/5' : ''}`}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <EncabezadoFolioPedido pedido={pedido} size="sm" />
                    <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                        {formatearFechaNegocio(pedido.fecha)}
                    </p>
                    {pedido.vendedor?.name && (
                        <p className="text-[9px] theme-text-muted font-bold mt-1 m-0">
                            Capturado por: {pedido.vendedor.name}
                        </p>
                    )}
                    {pedido.origen?.nombre && (
                        <p className="text-[9px] font-black uppercase text-blue-600 mt-1 m-0">Origen: {pedido.origen.nombre}</p>
                    )}
                </div>
                <span className={badge.className} style={badge.style}>{badge.label}</span>
            </div>
            <div>
                <p className="text-xs font-black theme-text-main uppercase m-0">{pedido.cliente?.nombre || '—'}</p>
                <p className="text-[9px] theme-text-muted m-0">{pedido.cliente?.numero_cliente}</p>
            </div>
            <div className="flex flex-wrap gap-2 text-[10px] font-bold theme-text-muted uppercase">
                <span>{pedido.banco?.nombre || '—'}</span>
                <span>·</span>
                <span>{pedido.paqueteria?.nombre || '—'}</span>
            </div>
            <p className="text-lg font-black m-0" style={{ color: 'var(--color-primario)' }}>{formatearMoneda(pedido.total_a_cobrar)}</p>
            {esRechazado && pedido.motivo_rechazo && (
                <p className="text-[10px] text-red-500 font-bold m-0 flex items-start gap-1">
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" /> {pedido.motivo_rechazo}
                </p>
            )}
            {esIncidenciaCedis && pedido.detalle_incidencia_empaque && (
                <p className="text-[10px] text-orange-600 font-bold m-0 flex items-start gap-1">
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" /> {pedido.detalle_incidencia_empaque}
                </p>
            )}
            <button type="button" onClick={() => onRevisar(pedido)} className="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl border theme-border theme-element text-xs font-black uppercase outline-none hover:border-[var(--color-primario)]">
                <Eye className="w-4 h-4" /> Revisar
            </button>
        </div>
    );
}

export default function TablaAuditoria({ pedidos, onRevisar }) {
    const items = pedidos?.data || [];

    if (items.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-16 text-center text-sm theme-text-muted font-bold uppercase tracking-widest`}>
                Sin solicitudes en esta vista_
            </div>
        );
    }

    return (
        <div className={`${geliaCardClass()} overflow-hidden`}>
            <div className="md:hidden p-4 space-y-3">
                {items.map((pedido) => {
                    const badge = badgeAuditoriaSemantico(pedido.estatus?.fase_ciclo, pedido.es_resguardo);
                    const fase = pedido.estatus?.fase_ciclo;
                    return (
                        <CardAuditoria
                            key={pedido.id}
                            pedido={pedido}
                            badge={badge}
                            esRechazado={fase === 'RECHAZADO_VENDEDORA'}
                            esIncidenciaCedis={fase === 'INCIDENCIA_CEDIS' || Boolean(pedido.detalle_incidencia_empaque)}
                            onRevisar={onRevisar}
                        />
                    );
                })}
            </div>

            <div className="hidden md:block overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Folio_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Fecha_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Banco_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Envío_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Total_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Estado_</th>
                            <th className="px-5 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Acción_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((pedido) => {
                            const badge = badgeAuditoriaSemantico(pedido.estatus?.fase_ciclo, pedido.es_resguardo);
                            const fase = pedido.estatus?.fase_ciclo;
                            const esRechazado = fase === 'RECHAZADO_VENDEDORA';
                            const esIncidenciaCedis = fase === 'INCIDENCIA_CEDIS' || Boolean(pedido.detalle_incidencia_empaque);
                            return (
                                <tr key={pedido.id} className={`border-b theme-border last:border-0 hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/20 transition-all ${esRechazado ? 'bg-red-500/5' : ''} ${esIncidenciaCedis ? 'bg-orange-500/5' : ''} ${pedido.es_resguardo ? 'bg-blue-500/5' : ''}`}>
                                    <td className="px-5 py-4">
                                        <EncabezadoFolioPedido pedido={pedido} size="sm" />
                                        {pedido.origen?.nombre && (
                                            <p className="text-[9px] font-black uppercase text-blue-600 mt-1 m-0">Origen: {pedido.origen.nombre}</p>
                                        )}
                                        {esRechazado && pedido.motivo_rechazo && (
                                            <p className="text-[9px] text-red-500 font-bold mt-1 flex items-center gap-1">
                                                <AlertTriangle className="w-3 h-3" /> {pedido.motivo_rechazo}
                                            </p>
                                        )}
                                        {esIncidenciaCedis && pedido.detalle_incidencia_empaque && (
                                            <p className="text-[9px] text-orange-600 font-bold mt-1 flex items-center gap-1">
                                                <AlertTriangle className="w-3 h-3" /> {pedido.detalle_incidencia_empaque}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted">
                                        {formatearFechaNegocio(pedido.fecha)}
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-xs font-black theme-text-main uppercase m-0">{pedido.cliente?.nombre}</p>
                                        <p className="text-[9px] theme-text-muted m-0">{pedido.cliente?.numero_cliente}</p>
                                    </td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted uppercase">{pedido.banco?.nombre || '—'}</td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted uppercase">{pedido.paqueteria?.nombre || '—'}</td>
                                    <td className="px-5 py-4 text-sm font-black" style={{ color: 'var(--color-primario)' }}>
                                        {formatearMoneda(pedido.total_a_cobrar)}
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={badge.className} style={badge.style}>{badge.label}</span>
                                    </td>
                                    <td className="px-5 py-4 text-right">
                                        <button type="button" onClick={() => onRevisar(pedido)} className="inline-flex items-center gap-2 px-3 py-2 rounded-xl border theme-border theme-element text-[10px] font-black uppercase outline-none hover:border-[var(--color-primario)]">
                                            <Eye className="w-4 h-4" /> Revisar
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            {pedidos?.links && <GeliaPaginacion paginacion={pedidos} />}
        </div>
    );
}
