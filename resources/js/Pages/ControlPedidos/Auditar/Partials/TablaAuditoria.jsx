import React from 'react';
import { Eye, AlertTriangle, History, PackagePlus } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import {
    badgeAuditoriaSemantico,
    badgeEstatusEnvio,
    badgeCorregirRemision,
    badgePendienteReRevision,
    formatearMoneda,
    formatearFechaNegocio,
    tieneErrorRemision,
    esPendienteReRevision,
    textoFuentesPagoCompacto,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import BloqueVendedorPedido from '../../Partials/BloqueVendedorPedido';
import BotonAccionCubico from '../../Partials/BotonAccionCubico';

function CardAuditoria({
    pedido,
    badge,
    badgeEnvio,
    badgeRemision,
    badgeReRevision,
    esRechazado,
    esIncidenciaCedis,
    onRevisar,
    onBitacora,
}) {
    const reRevision = Boolean(badgeReRevision);

    return (
        <div
            className={`${geliaCardClass()} p-4 space-y-3 ${
                reRevision
                    ? 'pedido-re-revision-glow'
                    : esRechazado
                        ? 'ring-1 ring-red-500/30'
                        : esIncidenciaCedis || badgeRemision
                            ? 'ring-1 ring-orange-500/30'
                            : ''
            } ${pedido.es_resguardo && !reRevision ? 'ring-2 ring-blue-500/40 bg-blue-500/5' : ''}`}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <EncabezadoFolioPedido pedido={pedido} size="sm" />
                    <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                        {formatearFechaNegocio(pedido.fecha)}
                    </p>
                    <BloqueVendedorPedido pedido={pedido} variante="nombre" />
                    {pedido.origen?.nombre && (
                        <p className="text-[9px] font-black uppercase text-blue-600 mt-1 m-0">Tipo: {pedido.origen.nombre}</p>
                    )}
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0 max-w-[50%]">
                    <BloqueVendedorPedido pedido={pedido} variante="etiquetas" className="mt-0 justify-end" />
                    <span className={badge.className} style={badge.style}>{badge.label}</span>
                    {badgeReRevision && (
                        <span className={badgeReRevision.className} style={badgeReRevision.style}>{badgeReRevision.label}</span>
                    )}
                    {badgeRemision && (
                        <span className={badgeRemision.className} style={badgeRemision.style}>{badgeRemision.label}</span>
                    )}
                    {badgeEnvio && (
                        <span className={badgeEnvio.className} style={badgeEnvio.style}>{badgeEnvio.label}</span>
                    )}
                </div>
            </div>
            <div>
                <p className="text-xs font-black theme-text-main uppercase m-0">{pedido.cliente?.nombre || '—'}</p>
                <p className="text-[9px] theme-text-muted m-0">{pedido.cliente?.numero_cliente}</p>
            </div>
            <div className="flex flex-wrap gap-2 text-[10px] font-bold theme-text-muted uppercase">
                <span title={textoFuentesPagoCompacto(pedido.fuentes_pago).completo || undefined}>
                    {textoFuentesPagoCompacto(pedido.fuentes_pago).texto}
                </span>
                <span>·</span>
                <span>{pedido.paqueteria?.nombre || '—'}</span>
            </div>
            <p className="text-lg font-black m-0" style={{ color: 'var(--color-primario)' }}>{formatearMoneda(pedido.total_a_cobrar)}</p>
            {reRevision && (
                <p className="text-[10px] text-emerald-600 font-bold m-0">
                    Corregido y reenviado — dar luz verde si todo está en orden
                </p>
            )}
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
            {badgeRemision && (
                <p className="text-[10px] text-orange-600 font-bold m-0 flex items-start gap-1">
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" /> Corregir remisión
                </p>
            )}
            <div className={`grid gap-2 ${onBitacora ? 'grid-cols-2' : 'grid-cols-1'}`}>
                <BotonAccionCubico icon={Eye} label="Revisar" onClick={() => onRevisar(pedido)} conLabel />
                {onBitacora && (
                    <BotonAccionCubico icon={History} label="Bitácora" onClick={() => onBitacora(pedido)} tone="purple" conLabel />
                )}
            </div>
        </div>
    );
}

export default function TablaAuditoria({ pedidos, onRevisar, onAnexarEnvio, onBitacora }) {
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
                    const badgeEnvio = badgeEstatusEnvio(pedido.estatus_envio, {
                        faseCiclo: pedido.estatus?.fase_ciclo,
                    });
                    const fase = pedido.estatus?.fase_ciclo;
                    return (
                        <CardAuditoria
                            key={pedido.id}
                            pedido={pedido}
                            badge={badge}
                            badgeEnvio={badgeEnvio}
                            badgeRemision={tieneErrorRemision(pedido) ? badgeCorregirRemision() : null}
                            badgeReRevision={esPendienteReRevision(pedido) ? badgePendienteReRevision() : null}
                            esRechazado={fase === 'RECHAZADO_VENDEDORA'}
                            esIncidenciaCedis={fase === 'INCIDENCIA_CEDIS' || Boolean(pedido.detalle_incidencia_empaque)}
                            onRevisar={onRevisar}
                            onBitacora={onBitacora}
                        />
                    );
                })}
            </div>

            <div className="hidden md:block overflow-x-auto overflow-y-visible">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Folio_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Vendedor_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Fecha_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Pagos_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Envío_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Total_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Estado_</th>
                            <th className="px-5 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Acción_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((pedido) => {
                            const badge = badgeAuditoriaSemantico(pedido.estatus?.fase_ciclo, pedido.es_resguardo);
                            const badgeEnvio = badgeEstatusEnvio(pedido.estatus_envio, {
                                faseCiclo: pedido.estatus?.fase_ciclo,
                            });
                            const fase = pedido.estatus?.fase_ciclo;
                            const esRechazado = fase === 'RECHAZADO_VENDEDORA';
                            const esIncidenciaCedis = fase === 'INCIDENCIA_CEDIS' || Boolean(pedido.detalle_incidencia_empaque);
                            const badgeRemision = tieneErrorRemision(pedido) ? badgeCorregirRemision() : null;
                            const badgeReRevision = esPendienteReRevision(pedido) ? badgePendienteReRevision() : null;
                            const puedeAnexar = ['pendiente_regularizacion', 'anexo_rechazado'].includes(pedido.estatus_envio);
                            return (
                                <tr
                                    key={pedido.id}
                                    className={`border-b theme-border last:border-0 hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/20 transition-all ${
                                        badgeReRevision
                                            ? 'pedido-re-revision-row'
                                            : esRechazado
                                                ? 'bg-red-500/5'
                                                : esIncidenciaCedis || badgeRemision
                                                    ? 'bg-orange-500/5'
                                                    : ''
                                    } ${pedido.es_resguardo && !badgeReRevision ? 'bg-blue-500/5' : ''}`}
                                >
                                    <td className="px-5 py-4">
                                        <EncabezadoFolioPedido pedido={pedido} size="sm" />
                                        {pedido.origen?.nombre && (
                                            <p className="text-[9px] font-black uppercase text-blue-600 mt-1 m-0">Tipo: {pedido.origen.nombre}</p>
                                        )}
                                        {badgeReRevision && (
                                            <p className="text-[9px] text-emerald-600 font-bold mt-1 m-0">Corregido — dar luz verde</p>
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
                                        {badgeRemision && (
                                            <p className="text-[9px] text-orange-600 font-bold mt-1 flex items-center gap-1">
                                                <AlertTriangle className="w-3 h-3" /> Corregir remisión
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-4">
                                        <BloqueVendedorPedido pedido={pedido} variante="completo" className="mt-0" />
                                    </td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted">
                                        {formatearFechaNegocio(pedido.fecha)}
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-xs font-black theme-text-main uppercase m-0">{pedido.cliente?.nombre}</p>
                                        <p className="text-[9px] theme-text-muted m-0">{pedido.cliente?.numero_cliente}</p>
                                    </td>
                                    <td
                                        className="px-5 py-4 text-xs font-bold theme-text-muted uppercase"
                                        title={textoFuentesPagoCompacto(pedido.fuentes_pago).completo || undefined}
                                    >
                                        {textoFuentesPagoCompacto(pedido.fuentes_pago).texto}
                                    </td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted uppercase">{pedido.paqueteria?.nombre || '—'}</td>
                                    <td className="px-5 py-4 text-sm font-black" style={{ color: 'var(--color-primario)' }}>
                                        {formatearMoneda(pedido.total_a_cobrar)}
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={badge.className} style={badge.style}>{badge.label}</span>
                                        {badgeReRevision && (
                                            <span className={`${badgeReRevision.className} mt-1.5 block w-fit`} style={badgeReRevision.style}>{badgeReRevision.label}</span>
                                        )}
                                        {badgeRemision && (
                                            <span className={`${badgeRemision.className} mt-1.5 block w-fit`} style={badgeRemision.style}>{badgeRemision.label}</span>
                                        )}
                                        {badgeEnvio && (
                                            <span className={`${badgeEnvio.className} mt-1.5 block w-fit`} style={badgeEnvio.style}>{badgeEnvio.label}</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-right overflow-visible">
                                        <div className="inline-flex justify-end gap-1.5 relative">
                                            {puedeAnexar && onAnexarEnvio && (
                                                <BotonAccionCubico
                                                    icon={PackagePlus}
                                                    label="Anexar envío"
                                                    onClick={() => onAnexarEnvio(pedido)}
                                                    tone="warn"
                                                />
                                            )}
                                            <BotonAccionCubico icon={Eye} label="Revisar" onClick={() => onRevisar(pedido)} />
                                            {onBitacora && (
                                                <BotonAccionCubico icon={History} label="Bitácora" onClick={() => onBitacora(pedido)} tone="purple" />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
