import React, { useEffect, useState } from 'react';
import { Eye, History } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import {
    formatearFechaNegocio,
    badgeEstatusPedido,
    badgeRetrasoGuia,
    badgesRetrasoSla,
    tieneRetrasoEmpaqueActivo,
    tieneRetrasoRecoleccionActivo,
    badgeResguardoSemantico,
    badgeCorregirGuia,
    tieneErrorGuiaReportado,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import BloqueVendedorPedido from '../../Partials/BloqueVendedorPedido';
import BotonAccionCubico from '../../Partials/BotonAccionCubico';
import ModalDetalleDelegado from './ModalDetalleDelegado';
import ModalReportarErrorDatos from '../../Partials/ModalReportarErrorDatos';
import ModalBitacoraPedido from '../../Partials/ModalBitacoraPedido';

function BadgesPedido({ pedido }) {
    const badge = badgeEstatusPedido(pedido.estatus, { esResguardo: pedido.es_resguardo });
    const retraso = pedido.guia_retraso ? badgeRetrasoGuia() : null;
    const badgesSla = badgesRetrasoSla(pedido);
    const resguardo = pedido.es_resguardo ? badgeResguardoSemantico() : null;
    const errorGuia = tieneErrorGuiaReportado(pedido) ? badgeCorregirGuia() : null;

    return (
        <div className="flex flex-wrap gap-1.5 justify-end">
            <span className={badge.className} style={badge.style}>{badge.label}</span>
            {errorGuia && <span className={errorGuia.className} style={errorGuia.style}>{errorGuia.label}</span>}
            {retraso && <span className={retraso.className} style={retraso.style}>{retraso.label}</span>}
            {badgesSla.map((b) => (
                <span key={b.label} className={b.className} style={b.style}>{b.label}</span>
            ))}
            {resguardo && !pedido.estatus?.fase_ciclo && (
                <span className={resguardo.className} style={resguardo.style}>{resguardo.label}</span>
            )}
        </div>
    );
}

function CardPedidoDelegado({ pedido, onAbrir, onBitacora }) {
    return (
        <div
            className={`${geliaCardClass()} p-4 space-y-3 text-left w-full ${pedido.es_resguardo ? 'ring-2 ring-blue-500/40 bg-blue-500/5' : ''} ${pedido.guia_retraso || tieneErrorGuiaReportado(pedido) || tieneRetrasoEmpaqueActivo(pedido) || tieneRetrasoRecoleccionActivo(pedido) ? 'ring-2 ring-amber-500/30' : ''}`}
        >
            <button type="button" onClick={() => onAbrir(pedido)} className="w-full text-left space-y-3 outline-none">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <EncabezadoFolioPedido pedido={pedido} size="sm" />
                        <BloqueVendedorPedido pedido={pedido} variante="nombre" />
                    </div>
                    <div className="flex flex-col items-end gap-1.5 shrink-0 max-w-[50%]">
                        <BloqueVendedorPedido pedido={pedido} variante="etiquetas" className="mt-0 justify-end" />
                        <BadgesPedido pedido={pedido} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-2 text-[10px] font-bold theme-text-muted">
                    <span>ID: {pedido.id}</span>
                    <span>{formatearFechaNegocio(pedido.fecha)}</span>
                    <span className="col-span-2 normal-case">{pedido.cliente?.nombre || '—'}</span>
                    <span className="uppercase col-span-2">{pedido.paqueteria?.nombre || '—'}</span>
                    {pedido.numero_rastreo && (
                        <span className="col-span-2 font-mono theme-text-main">{pedido.numero_rastreo}</span>
                    )}
                </div>
            </button>
            <div className={`grid gap-2 ${onBitacora ? 'grid-cols-2' : 'grid-cols-1'}`}>
                <BotonAccionCubico icon={Eye} label="Ver / guía" onClick={() => onAbrir(pedido)} conLabel />
                {onBitacora && (
                    <BotonAccionCubico icon={History} label="Bitácora" onClick={() => onBitacora(pedido)} tone="purple" conLabel />
                )}
            </div>
        </div>
    );
}

export default function TablaDelegado({ pedidos, tabActiva = 'PENDIENTES_GUIA', onModalAbierto }) {
    const [pedidoDetalle, setPedidoDetalle] = useState(null);
    const [pedidoError, setPedidoError] = useState(null);
    const [pedidoBitacora, setPedidoBitacora] = useState(null);
    const items = pedidos?.data || [];

    const modalAbierto = Boolean(pedidoDetalle || pedidoError || pedidoBitacora);

    useEffect(() => {
        onModalAbierto?.(modalAbierto);
    }, [modalAbierto, onModalAbierto]);

    const vacio = {
        TODOS: 'No hay pedidos en la bandeja de guías_',
        PENDIENTES_GUIA: 'No hay pedidos pendientes de guía_',
        PENDIENTES_ENVIO: 'No hay pedidos pendientes de envío_',
        ENVIADOS: 'No hay pedidos enviados_',
    }[tabActiva] || 'No hay pedidos_';

    const cerrarDetalle = () => setPedidoDetalle(null);

    const alReportarExito = () => {
        setPedidoError(null);
        setPedidoDetalle(null);
    };

    return (
        <div className="space-y-4">
            {items.length === 0 ? (
                <div className={`${geliaCardClass()} p-16 text-center text-sm theme-text-muted font-bold uppercase tracking-widest`}>
                    {vacio}
                </div>
            ) : (
                <>
                    <div className="md:hidden space-y-3">
                        {items.map((pedido) => (
                            <CardPedidoDelegado
                                key={pedido.id}
                                pedido={pedido}
                                onAbrir={setPedidoDetalle}
                                onBitacora={setPedidoBitacora}
                            />
                        ))}
                    </div>

                    <div className={`${geliaCardClass()} overflow-x-auto overflow-y-visible hidden md:block`}>
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="border-b-2 border-[var(--color-primario)]/30">
                                    <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">ID</th>
                                    <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Folio</th>
                                    <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Vendedor</th>
                                    <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Cliente</th>
                                    <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Paquetería</th>
                                    <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Estatus</th>
                                    <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Guía</th>
                                    <th className="px-5 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Fecha</th>
                                    <th className="px-5 py-4 text-right text-[9px] font-black theme-text-muted uppercase tracking-widest">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((pedido) => (
                                    <tr
                                        key={pedido.id}
                                        className={`border-b theme-border last:border-0 align-middle hover:bg-black/[0.02] ${pedido.guia_retraso || tieneRetrasoEmpaqueActivo(pedido) || tieneRetrasoRecoleccionActivo(pedido) ? 'bg-amber-500/5' : ''} ${pedido.es_resguardo ? 'bg-blue-500/5' : ''}`}
                                    >
                                        <td className="px-5 py-4 text-sm font-black theme-text-main font-mono cursor-pointer" onClick={() => setPedidoDetalle(pedido)}>{pedido.id}</td>
                                        <td className="px-5 py-4 cursor-pointer" onClick={() => setPedidoDetalle(pedido)}>
                                            <EncabezadoFolioPedido pedido={pedido} size="sm" />
                                        </td>
                                        <td className="px-5 py-4">
                                            <BloqueVendedorPedido pedido={pedido} variante="completo" className="mt-0" />
                                        </td>
                                        <td className="px-5 py-4 text-xs font-bold theme-text-main cursor-pointer" onClick={() => setPedidoDetalle(pedido)}>{pedido.cliente?.nombre || '—'}</td>
                                        <td className="px-5 py-4 text-xs font-bold theme-text-muted uppercase">{pedido.paqueteria?.nombre || '—'}</td>
                                        <td className="px-5 py-4"><BadgesPedido pedido={pedido} /></td>
                                        <td className="px-5 py-4 text-xs font-mono font-bold theme-text-main">{pedido.numero_rastreo || '—'}</td>
                                        <td className="px-5 py-4 text-[10px] font-bold theme-text-muted">{formatearFechaNegocio(pedido.fecha)}</td>
                                        <td className="px-5 py-4 text-right overflow-visible">
                                            <div className="inline-flex justify-end gap-1.5 relative">
                                                <BotonAccionCubico icon={Eye} label="Abrir" onClick={() => setPedidoDetalle(pedido)} />
                                                <BotonAccionCubico icon={History} label="Bitácora" onClick={() => setPedidoBitacora(pedido)} tone="purple" />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            <ModalDetalleDelegado
                abierto={Boolean(pedidoDetalle)}
                pedido={pedidoDetalle}
                onClose={cerrarDetalle}
                onReportarError={(p) => setPedidoError(p)}
            />
            <ModalBitacoraPedido
                abierto={Boolean(pedidoBitacora)}
                pedido={pedidoBitacora}
                onClose={() => setPedidoBitacora(null)}
            />
            <ModalReportarErrorDatos
                abierto={Boolean(pedidoError)}
                pedido={pedidoError}
                origen="delegado"
                onClose={() => setPedidoError(null)}
                onSuccess={alReportarExito}
            />
        </div>
    );
}
