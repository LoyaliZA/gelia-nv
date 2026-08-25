import React from 'react';
import { Eye, AlertTriangle, History, PackagePlus } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import {
    badgeAuditoriaSemantico,
    badgeAuditoriaRevision,
    badgeEstatusEnvio,
    badgeCorregirRemision,
    badgePendienteReRevision,
    badgeHitoAuditoria,
    formatearMoneda,
    formatearFechaNegocio,
    tieneErrorRemision,
    esPendienteReRevision,
    textoFuentesPagoCompacto,
    BTN_SECONDARY,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import BloqueVendedorPedido from '../../Partials/BloqueVendedorPedido';
import BotonAccionCubico from '../../Partials/BotonAccionCubico';

const MENSAJES_VACIO = {
    PENDIENTES: 'No hay pedidos pendientes de revisión',
    CORREGIDOS: 'No hay pedidos corregidos por Ventas',
    RECHAZADOS: 'No hay pedidos con observación',
    APROBADOS: 'No hay pedidos en Registro / posteriores',
    TODAS: 'Sin pedidos en esta vista',
};

function CoberturaCompacta({ pedido, className = '' }) {
    const total = pedido.total_a_cobrar;
    const pagado = pedido.pagado_valido;
    const diferencia = pedido.diferencia_cobertura;
    if (pagado == null && diferencia == null) {
        return (
            <p className={`text-lg font-black m-0 tabular-nums ${className}`} style={{ color: 'var(--color-primario)' }}>
                {formatearMoneda(total)}
            </p>
        );
    }
    return (
        <div className={`space-y-0.5 ${className}`}>
            <p className="text-[9px] font-black uppercase theme-text-muted m-0">
                Total {formatearMoneda(total)}
            </p>
            <p className="text-sm font-black m-0 tabular-nums" style={{ color: 'var(--color-primario)' }}>
                Pagado {formatearMoneda(pagado)}
            </p>
            <p className={`text-[10px] font-bold m-0 tabular-nums ${Number(diferencia) > 0.01 ? 'text-amber-600' : Number(diferencia) < -0.01 ? 'text-sky-600' : 'theme-text-muted'}`}>
                Dif. {formatearMoneda(diferencia)}
            </p>
        </div>
    );
}

function FiltroCelda({ title, onClick, children, className = '' }) {
    if (!onClick) {
        return <div className={className}>{children}</div>;
    }
    return (
        <button
            type="button"
            title={title}
            onClick={(e) => {
                e.stopPropagation();
                onClick();
            }}
            className={`text-left outline-none hover:underline decoration-[var(--color-primario)] underline-offset-2 ${className}`}
        >
            {children}
        </button>
    );
}

function tabDesdePedido(pedido) {
    if (esPendienteReRevision(pedido)) return 'CORREGIDOS';
    const fase = pedido.estatus?.fase_ciclo;
    if (fase === 'PENDIENTE_AUXILIAR') return 'PENDIENTES';
    if (fase === 'RECHAZADO_VENDEDORA') return 'RECHAZADOS';
    if (['EN_CEDIS', 'INCIDENCIA_CEDIS', 'PENDIENTE_DE_GUIA', 'PENDIENTE_GUIA_CLIENTE', 'PENDIENTE_DE_ENVIO', 'ENTREGADO', 'ENVIADO'].includes(fase)) {
        return 'APROBADOS';
    }
    return null;
}

function CardAuditoria({
    pedido,
    badge,
    badgeEnvio,
    badgeRemision,
    badgeReRevision,
    badgeHito,
    esRechazado,
    esIncidenciaCedis,
    onRevisar,
    onBitacora,
    onFiltrarBusqueda,
    onFiltrarPaqueteria,
    onFiltrarTab,
}) {
    const reRevision = Boolean(badgeReRevision);
    const tabEstado = tabDesdePedido(pedido);

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
                    <FiltroCelda
                        title="Filtrar por folio"
                        onClick={pedido.folio ? () => onFiltrarBusqueda?.(pedido.folio) : undefined}
                    >
                        <EncabezadoFolioPedido pedido={pedido} size="sm" />
                    </FiltroCelda>
                    <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                        {formatearFechaNegocio(pedido.fecha)}
                    </p>
                    <FiltroCelda
                        title="Filtrar por vendedor"
                        onClick={pedido.vendedor?.name ? () => onFiltrarBusqueda?.(pedido.vendedor.name) : undefined}
                    >
                        <BloqueVendedorPedido pedido={pedido} variante="nombre" />
                    </FiltroCelda>
                    {pedido.origen?.nombre && (
                        <p className="text-[9px] font-black uppercase text-blue-600 mt-1 m-0">Tipo: {pedido.origen.nombre}</p>
                    )}
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0 max-w-[50%]">
                    <BloqueVendedorPedido pedido={pedido} variante="etiquetas" className="mt-0 justify-end" />
                    <FiltroCelda
                        title="Filtrar por estado"
                        onClick={tabEstado ? () => onFiltrarTab?.(tabEstado) : undefined}
                    >
                        <span className={badge.className} style={badge.style}>{badge.label}</span>
                    </FiltroCelda>
                    {badgeHito && (
                        <span className={badgeHito.className} style={badgeHito.style}>{badgeHito.label}</span>
                    )}
                    {badgeRemision && (
                        <span className={badgeRemision.className} style={badgeRemision.style}>{badgeRemision.label}</span>
                    )}
                    {badgeEnvio && (
                        <span className={badgeEnvio.className} style={badgeEnvio.style}>{badgeEnvio.label}</span>
                    )}
                </div>
            </div>
            <FiltroCelda
                title="Filtrar por cliente"
                onClick={
                    (pedido.cliente?.numero_cliente || pedido.cliente?.nombre)
                        ? () => onFiltrarBusqueda?.(pedido.cliente.numero_cliente || pedido.cliente.nombre)
                        : undefined
                }
            >
                <p className="text-xs font-black theme-text-main uppercase m-0">{pedido.cliente?.nombre || '—'}</p>
                <p className="text-[9px] theme-text-muted m-0">{pedido.cliente?.numero_cliente}</p>
            </FiltroCelda>
            <div className="flex flex-wrap gap-2 text-[10px] font-bold theme-text-muted uppercase">
                <span title={textoFuentesPagoCompacto(pedido.fuentes_pago).completo || undefined}>
                    {textoFuentesPagoCompacto(pedido.fuentes_pago).texto}
                </span>
                <span>·</span>
                <FiltroCelda
                    title="Filtrar por paquetería / transporte"
                    onClick={pedido.paqueteria?.id ? () => onFiltrarPaqueteria?.(String(pedido.paqueteria.id)) : undefined}
                    className="uppercase"
                >
                    {pedido.paqueteria?.nombre || '—'}
                </FiltroCelda>
            </div>
            <CoberturaCompacta pedido={pedido} />
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
            {pedido.tiene_alerta_saf && (
                <p className="text-[10px] text-amber-700 font-bold m-0 flex items-start gap-1">
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" /> Alerta saldo a favor (no bloquea)
                </p>
            )}
            {badgeRemision && (
                <p className="text-[10px] text-orange-600 font-bold m-0 flex items-start gap-1">
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" /> Corregir remisión
                </p>
            )}
            <div className={`grid gap-2 ${onBitacora ? 'grid-cols-2' : 'grid-cols-1'}`}>
                <BotonAccionCubico icon={Eye} label="Revisar pedido" onClick={() => onRevisar(pedido)} conLabel />
                {onBitacora && (
                    <BotonAccionCubico icon={History} label="Bitácora" onClick={() => onBitacora(pedido)} tone="purple" conLabel />
                )}
            </div>
        </div>
    );
}

export default function TablaAuditoria({
    pedidos,
    tabActiva = 'PENDIENTES',
    hayFiltrosActivos = false,
    onLimpiarFiltros,
    onRevisar,
    onAnexarEnvio,
    onBitacora,
    onFiltrarBusqueda,
    onFiltrarPaqueteria,
    onFiltrarTab,
}) {
    const items = pedidos?.data || [];

    if (items.length === 0) {
        const msg = MENSAJES_VACIO[tabActiva] || 'Sin solicitudes en esta vista';
        return (
            <div className={`${geliaCardClass()} p-10 md:p-16 text-center space-y-3`}>
                <p className="text-sm theme-text-muted font-bold uppercase tracking-widest m-0">{msg}</p>
                {hayFiltrosActivos && onLimpiarFiltros && (
                    <button type="button" onClick={onLimpiarFiltros} className={`${BTN_SECONDARY} text-xs outline-none`}>
                        Limpiar filtros
                    </button>
                )}
            </div>
        );
    }

    return (
        <div className={`${geliaCardClass()} overflow-hidden`}>
            <div className="md:hidden p-4 space-y-3">
                {items.map((pedido) => {
                    const badge = badgeAuditoriaRevision(pedido) || badgeAuditoriaSemantico(pedido.estatus?.fase_ciclo, pedido.es_resguardo);
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
                            badgeHito={badgeHitoAuditoria(pedido.hito_auditoria)}
                            esRechazado={fase === 'RECHAZADO_VENDEDORA'}
                            esIncidenciaCedis={fase === 'INCIDENCIA_CEDIS' || Boolean(pedido.detalle_incidencia_empaque)}
                            onRevisar={onRevisar}
                            onBitacora={onBitacora}
                            onFiltrarBusqueda={onFiltrarBusqueda}
                            onFiltrarPaqueteria={onFiltrarPaqueteria}
                            onFiltrarTab={onFiltrarTab}
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
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Paquetería / transporte_</th>
                            <th className="px-5 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Total_</th>
                            <th className="px-5 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Pagado_</th>
                            <th className="px-5 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Dif._</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Estado_</th>
                            <th className="px-5 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Acción_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((pedido) => {
                            const badge = badgeAuditoriaRevision(pedido) || badgeAuditoriaSemantico(pedido.estatus?.fase_ciclo, pedido.es_resguardo);
                            const badgeEnvio = badgeEstatusEnvio(pedido.estatus_envio, {
                                faseCiclo: pedido.estatus?.fase_ciclo,
                            });
                            const fase = pedido.estatus?.fase_ciclo;
                            const esRechazado = fase === 'RECHAZADO_VENDEDORA';
                            const esIncidenciaCedis = fase === 'INCIDENCIA_CEDIS' || Boolean(pedido.detalle_incidencia_empaque);
                            const badgeRemision = tieneErrorRemision(pedido) ? badgeCorregirRemision() : null;
                            const badgeReRevision = esPendienteReRevision(pedido) ? badgePendienteReRevision() : null;
                            const badgeHito = badgeHitoAuditoria(pedido.hito_auditoria);
                            const puedeAnexar = ['pendiente_regularizacion', 'anexo_rechazado'].includes(pedido.estatus_envio);
                            const dif = Number(pedido.diferencia_cobertura);
                            const tabEstado = tabDesdePedido(pedido);
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
                                        <FiltroCelda
                                            title="Filtrar por folio"
                                            onClick={pedido.folio ? () => onFiltrarBusqueda?.(pedido.folio) : undefined}
                                        >
                                            <EncabezadoFolioPedido pedido={pedido} size="sm" />
                                        </FiltroCelda>
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
                                        {pedido.tiene_alerta_saf && (
                                            <p className="text-[9px] text-amber-700 font-bold mt-1 flex items-center gap-1">
                                                <AlertTriangle className="w-3 h-3" /> Alerta saldo a favor
                                            </p>
                                        )}
                                        {badgeRemision && (
                                            <p className="text-[9px] text-orange-600 font-bold mt-1 flex items-center gap-1">
                                                <AlertTriangle className="w-3 h-3" /> Corregir remisión
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-4">
                                        <FiltroCelda
                                            title="Filtrar por vendedor"
                                            onClick={pedido.vendedor?.name ? () => onFiltrarBusqueda?.(pedido.vendedor.name) : undefined}
                                        >
                                            <BloqueVendedorPedido pedido={pedido} variante="completo" className="mt-0" />
                                        </FiltroCelda>
                                    </td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted">
                                        {formatearFechaNegocio(pedido.fecha)}
                                    </td>
                                    <td className="px-5 py-4">
                                        <FiltroCelda
                                            title="Filtrar por cliente"
                                            onClick={
                                                (pedido.cliente?.numero_cliente || pedido.cliente?.nombre)
                                                    ? () => onFiltrarBusqueda?.(pedido.cliente.numero_cliente || pedido.cliente.nombre)
                                                    : undefined
                                            }
                                        >
                                            <p className="text-xs font-black theme-text-main uppercase m-0">{pedido.cliente?.nombre}</p>
                                            <p className="text-[9px] theme-text-muted m-0">{pedido.cliente?.numero_cliente}</p>
                                        </FiltroCelda>
                                    </td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted uppercase">
                                        <FiltroCelda
                                            title="Filtrar por paquetería / transporte"
                                            onClick={pedido.paqueteria?.id ? () => onFiltrarPaqueteria?.(String(pedido.paqueteria.id)) : undefined}
                                            className="uppercase font-bold"
                                        >
                                            {pedido.paqueteria?.nombre || '—'}
                                        </FiltroCelda>
                                    </td>
                                    <td className="px-5 py-4 text-sm font-black text-right tabular-nums" style={{ color: 'var(--color-primario)' }}>
                                        {formatearMoneda(pedido.total_a_cobrar)}
                                    </td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-main text-right tabular-nums">
                                        {pedido.pagado_valido != null ? formatearMoneda(pedido.pagado_valido) : '—'}
                                    </td>
                                    <td className={`px-5 py-4 text-xs font-bold text-right tabular-nums ${
                                        dif > 0.01 ? 'text-amber-600' : dif < -0.01 ? 'text-sky-600' : 'theme-text-muted'
                                    }`}
                                    >
                                        {pedido.diferencia_cobertura != null ? formatearMoneda(pedido.diferencia_cobertura) : '—'}
                                    </td>
                                    <td className="px-5 py-4">
                                        <FiltroCelda
                                            title="Filtrar por estado"
                                            onClick={tabEstado ? () => onFiltrarTab?.(tabEstado) : undefined}
                                        >
                                            <span className={badge.className} style={badge.style}>{badge.label}</span>
                                        </FiltroCelda>
                                        {badgeHito && (
                                            <span className={`${badgeHito.className} mt-1.5 block w-fit`} style={badgeHito.style}>{badgeHito.label}</span>
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
