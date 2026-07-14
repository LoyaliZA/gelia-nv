import React, { useState } from 'react';
import { Eye, Edit2, Trash2, AlertTriangle, History } from 'lucide-react';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { badgeEstatusPedido, formatearMoneda, etiquetaAlmacen, formatearFechaNegocio, tieneGuiaLista, badgeGuiaLista, tieneGuiaPdfDisponible } from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';
import BotonGuiaPdf from './BotonGuiaPdf';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';

function AccionesPedido({ pedido, can, onVer, onEditar, onEliminar, onBitacora, onVerGuia, puedeEditar, puedeEliminar, compact = false }) {
    const btnClass = compact ? 'p-2.5' : 'p-2';
    return (
        <div className={`flex ${compact ? 'flex-wrap' : 'justify-end'} gap-1.5`}>
            {tieneGuiaPdfDisponible(pedido) && onVerGuia && (
                <BotonGuiaPdf pedido={pedido} onVerPdf={onVerGuia} compact className="!px-3 !py-2" />
            )}
            {can('control_pedidos.ver_detalle') && (
                <button type="button" onClick={() => onVer(pedido)} className={`${btnClass} theme-element border theme-border rounded-xl outline-none hover:border-[var(--color-primario)]`} title="Ver">
                    <Eye className="w-4 h-4 theme-text-main" />
                </button>
            )}
            {onBitacora && (
                <button type="button" onClick={() => onBitacora(pedido)} className={`${btnClass} theme-element border theme-border rounded-xl outline-none hover:border-purple-500`} title="Bitácora">
                    <History className="w-4 h-4 theme-text-main" />
                </button>
            )}
            {puedeEditar(pedido) && (
                <button type="button" onClick={() => onEditar(pedido)} className={`${btnClass} theme-element border theme-border rounded-xl outline-none hover:border-[var(--color-primario)]`} title="Editar">
                    <Edit2 className="w-4 h-4 theme-text-main" />
                </button>
            )}
            {puedeEliminar(pedido) && (
                <button type="button" onClick={() => onEliminar(pedido)} className={`${btnClass} theme-element border theme-border rounded-xl outline-none hover:bg-red-500/10 hover:border-red-500`} title="Eliminar">
                    <Trash2 className="w-4 h-4 text-red-500" />
                </button>
            )}
        </div>
    );
}

function CardPedido({ pedido, badge, esRechazado, can, onVer, onEditar, onEliminar, onBitacora, onVerGuia, puedeEditar, puedeEliminar }) {
    const guiaLista = tieneGuiaLista(pedido);
    const badgeGuia = badgeGuiaLista();

    return (
        <div className={`${geliaCardClass()} p-4 space-y-3 ${esRechazado ? 'ring-1 ring-red-500/30' : ''}`}>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <EncabezadoFolioPedido pedido={pedido} size="sm" />
                    <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                        {formatearFechaNegocio(pedido.fecha)}
                    </p>
                    {pedido.vendedor?.name && (
                        <p className="text-[9px] theme-text-muted font-bold mt-1 m-0">{pedido.vendedor.name}</p>
                    )}
                </div>
                <div className="flex flex-col items-end gap-1.5 shrink-0">
                    <span className={badge.className} style={badge.style}>{badge.label}</span>
                    {guiaLista && (
                        <span className={badgeGuia.className}>{badgeGuia.label}</span>
                    )}
                </div>
            </div>
            <div>
                <p className="text-xs font-black theme-text-main uppercase m-0">{pedido.cliente?.nombre || '—'}</p>
                <p className="text-[9px] theme-text-muted m-0">{pedido.cliente?.numero_cliente}</p>
            </div>
            <div className="flex flex-wrap gap-2 text-[10px] font-bold theme-text-muted uppercase">
                <span>{etiquetaAlmacen(pedido.almacen)}</span>
                <span>·</span>
                <span>{pedido.banco?.nombre || '—'}</span>
            </div>
            <p className="text-lg font-black m-0" style={{ color: 'var(--color-primario)' }}>{formatearMoneda(pedido.total_a_cobrar)}</p>
            {guiaLista && pedido.numero_rastreo && (
                <p className="text-xs font-black font-mono theme-text-main m-0">
                    Guía: {pedido.numero_rastreo}
                </p>
            )}
            {esRechazado && pedido.motivo_rechazo && (
                <p className="text-[10px] text-red-500 font-bold m-0 flex items-start gap-1">
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" /> {pedido.motivo_rechazo}
                </p>
            )}
            <AccionesPedido
                pedido={pedido}
                can={can}
                onVer={onVer}
                onEditar={onEditar}
                onEliminar={onEliminar}
                onBitacora={onBitacora}
                onVerGuia={onVerGuia}
                puedeEditar={puedeEditar}
                puedeEliminar={puedeEliminar}
                compact
            />
        </div>
    );
}

export default function TablaPedidos({
    pedidos,
    can,
    onVer,
    onEditar,
    onEliminar,
    onBitacora,
}) {
    const [docPreview, setDocPreview] = useState(null);
    const items = pedidos?.data || [];

    const puedeEditar = (pedido) => {
        const fase = pedido.estatus?.fase_ciclo;
        return can('control_pedidos.editar') && ['BORRADOR', 'RECHAZADO_VENDEDORA'].includes(fase);
    };

    const puedeEliminar = (pedido) => can('control_pedidos.eliminar') && pedido.estatus?.fase_ciclo === 'BORRADOR';

    if (items.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-16 text-center text-sm theme-text-muted font-bold uppercase tracking-widest`}>
                Sin pedidos en esta vista_
            </div>
        );
    }

    return (
        <div className={`${geliaCardClass()} overflow-hidden`}>
            {/* Vista móvil: cards */}
            <div className="md:hidden p-4 space-y-3">
                {items.map((pedido) => (
                    <CardPedido
                        key={pedido.id}
                        pedido={pedido}
                        badge={badgeEstatusPedido(pedido.estatus, { esResguardo: pedido.es_resguardo })}
                        esRechazado={pedido.estatus?.fase_ciclo === 'RECHAZADO_VENDEDORA'}
                        can={can}
                        onVer={onVer}
                        onEditar={onEditar}
                        onEliminar={onEliminar}
                        onBitacora={onBitacora}
                        onVerGuia={setDocPreview}
                        puedeEditar={puedeEditar}
                        puedeEliminar={puedeEliminar}
                    />
                ))}
            </div>

            {/* Vista desktop: tabla */}
            <div className="hidden md:block overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Folio_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Fecha_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Almacén_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Banco_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Total_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Estado_</th>
                            <th className="px-5 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((pedido) => {
                            const badge = badgeEstatusPedido(pedido.estatus, { esResguardo: pedido.es_resguardo });
                            const esRechazado = pedido.estatus?.fase_ciclo === 'RECHAZADO_VENDEDORA';
                            const guiaLista = tieneGuiaLista(pedido);
                            const badgeGuia = badgeGuiaLista();
                            return (
                                <tr key={pedido.id} className={`border-b theme-border last:border-0 hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/20 transition-all ${esRechazado ? 'bg-red-500/5' : ''}`}>
                                    <td className="px-5 py-4">
                                        <EncabezadoFolioPedido pedido={pedido} size="sm" />
                                        {esRechazado && pedido.motivo_rechazo && (
                                            <p className="text-[9px] text-red-500 font-bold mt-1 flex items-center gap-1">
                                                <AlertTriangle className="w-3 h-3" /> {pedido.motivo_rechazo}
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
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted uppercase">{etiquetaAlmacen(pedido.almacen)}</td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted uppercase">{pedido.banco?.nombre || '—'}</td>
                                    <td className="px-5 py-4 text-sm font-black" style={{ color: 'var(--color-primario)' }}>
                                        {formatearMoneda(pedido.total_a_cobrar)}
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={badge.className} style={badge.style}>
                                            {badge.label}
                                        </span>
                                        {guiaLista && (
                                            <span className={`${badgeGuia.className} mt-1.5 block w-fit`}>{badgeGuia.label}</span>
                                        )}
                                        {guiaLista && pedido.numero_rastreo && (
                                            <p className="text-[9px] font-bold font-mono theme-text-main mt-1 m-0">
                                                Guía: {pedido.numero_rastreo}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-4">
                                        <AccionesPedido
                                            pedido={pedido}
                                            can={can}
                                            onVer={onVer}
                                            onEditar={onEditar}
                                            onEliminar={onEliminar}
                                            onBitacora={onBitacora}
                                            onVerGuia={setDocPreview}
                                            puedeEditar={puedeEditar}
                                            puedeEliminar={puedeEliminar}
                                        />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            {pedidos?.links && <GeliaPaginacion paginacion={pedidos} />}
            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview} onClose={() => setDocPreview(null)} />
        </div>
    );
}
