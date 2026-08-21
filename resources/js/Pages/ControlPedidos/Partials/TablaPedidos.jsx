import React, { useState } from 'react';
import { Eye, Edit2, Trash2, AlertTriangle, History, Receipt, PackageCheck, Truck, Ban, Download, RotateCcw, FileSearch } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import {
    badgeEstatusPedido,
    badgeEstatusEnvio,
    formatearMoneda,
    etiquetaAlmacen,
    formatearFechaNegocio,
    tieneGuiaLista,
    badgeGuiaLista,
    badgeObservacionesCedis,
    badgeSinExistencias,
    mostrarBadgeSinExistencias,
    tieneGuiaPdfDisponible,
    puedeAnexarPagoEnvio,
    puedeCompletarEnvioResguardo,
    puedeCargarGuiaCliente,
    textoFuentesPagoCompacto,
    guiaPdfDe,
    esFasePreVenta,
    FASES_PRE_VENTA,
    badgesRetrasoSla,
    badgeAuditoriaRevision,
    etiquetaOrigenGuia,
} from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';
import BotonAccionCubico from './BotonAccionCubico';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';

function AccionesPedido({
    pedido, can, onVer, onEditar, onEliminar, onEliminarRegistro, onRestaurar, onVerAuditoria, onCancelar, onBitacora, onVerGuia, onAnexarEnvio, onCompletarEnvio, onCargarGuia,
    puedeEditar, puedeEliminarBorrador, puedeEliminarRegistro, puedeCancelar, esPapelera = false, compact = false,
}) {
    if (esPapelera) {
        const itemsPapelera = [];
        if (can('control_pedidos.ver_detalle')) {
            itemsPapelera.push(
                <BotonAccionCubico key="ver" icon={Eye} label="Ver" onClick={() => onVer(pedido)} conLabel={compact} />
            );
        }
        if (can('control_pedidos.eliminados') && onRestaurar) {
            itemsPapelera.push(
                <BotonAccionCubico key="restaurar" icon={RotateCcw} label="Restaurar" onClick={() => onRestaurar(pedido)} tone="teal" conLabel={compact} />
            );
        }
        if (onVerAuditoria) {
            itemsPapelera.push(
                <BotonAccionCubico key="auditoria" icon={FileSearch} label="Auditoría" onClick={() => onVerAuditoria(pedido)} tone="purple" conLabel={compact} />
            );
        }
        if (itemsPapelera.length === 0) return null;
        return (
            <div className={compact ? 'grid grid-cols-2 gap-2' : 'flex justify-end gap-1.5 overflow-visible'}>
                {itemsPapelera}
            </div>
        );
    }

    const puedeMutar = Boolean(pedido.puede_editar ?? pedido.puede_mutar);
    const puedeAnexar = puedeMutar
        && puedeAnexarPagoEnvio(pedido)
        && (can('control_pedidos.crear') || can('control_pedidos.auditar'));
    const puedeCompletar = puedeMutar
        && puedeCompletarEnvioResguardo(pedido)
        && (can('control_pedidos.crear') || can('control_pedidos.editar'))
        && onCompletarEnvio;
    const puedeCargar = puedeMutar
        && puedeCargarGuiaCliente(pedido)
        && (can('control_pedidos.crear') || can('control_pedidos.editar'))
        && onCargarGuia;
    const guiaPdf = tieneGuiaPdfDisponible(pedido) && onVerGuia ? guiaPdfDe(pedido) : null;

    const items = [];
    if (guiaPdf) {
        items.push(
            <BotonAccionCubico key="guia" icon={Download} label="Guía PDF" onClick={() => onVerGuia(guiaPdf)} conLabel={compact} />
        );
    }
    if (puedeCargar) {
        items.push(
            <BotonAccionCubico key="cargar" icon={Truck} label="Cargar guía" onClick={() => onCargarGuia(pedido)} tone="fuchsia" conLabel={compact} />
        );
    }
    if (puedeCompletar) {
        items.push(
            <BotonAccionCubico key="completar" icon={PackageCheck} label="Completar envío" onClick={() => onCompletarEnvio(pedido)} tone="teal" conLabel={compact} />
        );
    }
    if (puedeAnexar && onAnexarEnvio) {
        items.push(
            <BotonAccionCubico key="anexar" icon={Receipt} label="Anexar pago" onClick={() => onAnexarEnvio(pedido)} tone="amber" conLabel={compact} />
        );
    }
    if (can('control_pedidos.ver_detalle')) {
        items.push(
            <BotonAccionCubico key="ver" icon={Eye} label="Ver" onClick={() => onVer(pedido)} conLabel={compact} />
        );
    }
    if (onBitacora) {
        items.push(
            <BotonAccionCubico key="bitacora" icon={History} label="Bitácora" onClick={() => onBitacora(pedido)} tone="purple" conLabel={compact} />
        );
    }
    if (puedeEditar(pedido)) {
        items.push(
            <BotonAccionCubico key="editar" icon={Edit2} label="Editar" onClick={() => onEditar(pedido)} conLabel={compact} />
        );
    }
    if (puedeCancelar?.(pedido) && onCancelar) {
        items.push(
            <BotonAccionCubico key="cancelar" icon={Ban} label="Cancelar" onClick={() => onCancelar(pedido)} tone="warn" conLabel={compact} />
        );
    }
    if (puedeEliminarBorrador?.(pedido)) {
        items.push(
            <BotonAccionCubico key="eliminar-borrador" icon={Trash2} label="Eliminar borrador" onClick={() => onEliminar(pedido)} tone="danger" conLabel={compact} />
        );
    }
    if (puedeEliminarRegistro?.(pedido) && onEliminarRegistro) {
        items.push(
            <BotonAccionCubico key="eliminar-registro" icon={Trash2} label="Eliminar registro" onClick={() => onEliminarRegistro(pedido)} tone="danger" conLabel={compact} />
        );
    }

    if (items.length === 0) return null;

    return (
        <div className={compact
            ? 'grid grid-cols-2 gap-2'
            : 'flex justify-end gap-1.5 overflow-visible'
        }
        >
            {items}
        </div>
    );
}

function CardPedido({ pedido, badge, badgeEnvio, esRechazado, can, onVer, onEditar, onEliminar, onEliminarRegistro, onRestaurar, onVerAuditoria, onCancelar, onBitacora, onVerGuia, onAnexarEnvio, onCompletarEnvio, onCargarGuia, puedeEditar, puedeEliminarBorrador, puedeEliminarRegistro, puedeCancelar, esPapelera }) {
    const guiaLista = tieneGuiaLista(pedido);
    const badgeGuia = badgeGuiaLista();
    const fase = pedido.estatus?.fase_ciclo;
    const badgeObs = (pedido.tiene_observaciones_fisicas && esFasePreVenta(fase))
        ? badgeObservacionesCedis()
        : null;
    const badgeSinEx = mostrarBadgeSinExistencias(pedido) ? badgeSinExistencias() : null;
    const badgesSla = badgesRetrasoSla(pedido);
    const badgeRevision = badgeAuditoriaRevision(pedido);

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
                    {badgeRevision && (
                        <span className={badgeRevision.className} style={badgeRevision.style}>{badgeRevision.label}</span>
                    )}
                    {badgeEnvio && (
                        <span className={badgeEnvio.className} style={badgeEnvio.style}>{badgeEnvio.label}</span>
                    )}
                    {badgeObs && (
                        <span className={badgeObs.className}>{badgeObs.label}</span>
                    )}
                    {badgeSinEx && (
                        <span className={badgeSinEx.className}>{badgeSinEx.label}</span>
                    )}
                    {badgesSla.map((b) => (
                        <span key={b.label} className={b.className} style={b.style}>{b.label}</span>
                    ))}
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
                {pedido.paqueteria?.nombre && (
                    <>
                        <span className="normal-case theme-text-main">{pedido.paqueteria.nombre}</span>
                        <span>·</span>
                    </>
                )}
                <span>{etiquetaAlmacen(pedido.almacen)}</span>
                <span>·</span>
                {(() => {
                    const f = textoFuentesPagoCompacto(pedido.fuentes_pago);
                    return <span title={f.completo || undefined}>{f.texto}</span>;
                })()}
                <span>·</span>
                <span className="normal-case">{etiquetaOrigenGuia(pedido)}</span>
            </div>
            <p className="text-lg font-black m-0" style={{ color: 'var(--color-primario)' }}>{formatearMoneda(pedido.total_a_cobrar)}</p>
            {guiaLista && pedido.numero_rastreo && (
                <p className="text-xs font-black font-mono theme-text-main m-0">
                    {etiquetaOrigenGuia(pedido)}: {pedido.numero_rastreo}
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
                onEliminarRegistro={onEliminarRegistro}
                onRestaurar={onRestaurar}
                onVerAuditoria={onVerAuditoria}
                onCancelar={onCancelar}
                onBitacora={onBitacora}
                onVerGuia={onVerGuia}
                onAnexarEnvio={onAnexarEnvio}
                onCompletarEnvio={onCompletarEnvio}
                onCargarGuia={onCargarGuia}
                puedeEditar={puedeEditar}
                puedeEliminarBorrador={puedeEliminarBorrador}
                puedeEliminarRegistro={puedeEliminarRegistro}
                puedeCancelar={puedeCancelar}
                esPapelera={esPapelera}
                compact
            />
        </div>
    );
}

export default function TablaPedidos({
    pedidos,
    can,
    tabActiva = 'TODAS',
    onVer,
    onEditar,
    onEliminar,
    onEliminarRegistro,
    onRestaurar,
    onVerAuditoria,
    onCancelar,
    onBitacora,
    onAnexarEnvio,
    onCompletarEnvio,
    onCargarGuia,
}) {
    const [docPreview, setDocPreview] = useState(null);
    const items = pedidos?.data || [];
    const esPapelera = tabActiva === 'ELIMINADAS';

    const puedeMutarPedido = (pedido) => Boolean(pedido.puede_editar ?? pedido.puede_mutar);

    const fasesEditarUi = FASES_PRE_VENTA;
    const puedeEditar = (pedido) => {
        if (esPapelera) return false;
        const fase = pedido.estatus?.fase_ciclo;
        return puedeMutarPedido(pedido)
            && can('control_pedidos.editar')
            && fasesEditarUi.includes(fase);
    };

    const puedeEliminarBorrador = (pedido) => !esPapelera
        && puedeMutarPedido(pedido)
        && can('control_pedidos.eliminar')
        && ['BORRADOR', 'PESAJE_PENDIENTE'].includes(pedido.estatus?.fase_ciclo);

    const puedeEliminarRegistro = (pedido) => !esPapelera && can('control_pedidos.eliminar_registro');

    const puedeCancelar = (pedido) => !esPapelera
        && Boolean(pedido.puede_cancelar)
        && puedeMutarPedido(pedido)
        && can('control_pedidos.cancelar')
        && pedido.estatus?.fase_ciclo !== 'CANCELADO';

    if (items.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-16 text-center text-sm theme-text-muted font-bold uppercase tracking-widest`}>
                Sin pedidos en esta vista_
            </div>
        );
    }

    return (
        <div className={`${geliaCardClass()} overflow-hidden`}>
            <div className="md:hidden p-4 space-y-3">
                {items.map((pedido) => (
                    <CardPedido
                        key={pedido.id}
                        pedido={pedido}
                        badge={badgeEstatusPedido(pedido.estatus, { esResguardo: pedido.es_resguardo })}
                        badgeEnvio={badgeEstatusEnvio(pedido.estatus_envio, { faseCiclo: pedido.estatus?.fase_ciclo })}
                        esRechazado={pedido.estatus?.fase_ciclo === 'RECHAZADO_VENDEDORA'}
                        can={can}
                        onVer={onVer}
                        onEditar={onEditar}
                        onEliminar={onEliminar}
                        onEliminarRegistro={onEliminarRegistro}
                        onRestaurar={onRestaurar}
                        onVerAuditoria={onVerAuditoria}
                        onCancelar={onCancelar}
                        onBitacora={onBitacora}
                        onVerGuia={setDocPreview}
                        onAnexarEnvio={onAnexarEnvio}
                        onCompletarEnvio={onCompletarEnvio}
                        onCargarGuia={onCargarGuia}
                        puedeEditar={puedeEditar}
                        puedeEliminarBorrador={puedeEliminarBorrador}
                        puedeEliminarRegistro={puedeEliminarRegistro}
                        puedeCancelar={puedeCancelar}
                        esPapelera={esPapelera}
                    />
                ))}
            </div>

            <div className="hidden md:block overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Folio_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Fecha_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                            <th className="px-5 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Paquetería_</th>
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
                            const badgeEnvio = badgeEstatusEnvio(pedido.estatus_envio, {
                                faseCiclo: pedido.estatus?.fase_ciclo,
                            });
                            const esRechazado = pedido.estatus?.fase_ciclo === 'RECHAZADO_VENDEDORA';
                            const guiaLista = tieneGuiaLista(pedido);
                            const badgeGuia = badgeGuiaLista();
                            const badgeObs = (pedido.tiene_observaciones_fisicas && esFasePreVenta(pedido.estatus?.fase_ciclo))
                                ? badgeObservacionesCedis()
                                : null;
                            const badgeSinEx = mostrarBadgeSinExistencias(pedido) ? badgeSinExistencias() : null;
                            const badgesSla = badgesRetrasoSla(pedido);
                            const badgeRevision = badgeAuditoriaRevision(pedido);
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
                                    <td className="px-5 py-4 text-xs font-bold theme-text-main">
                                        {pedido.paqueteria?.nombre || '—'}
                                    </td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted uppercase">{etiquetaAlmacen(pedido.almacen)}</td>
                                    <td className="px-5 py-4 text-xs font-bold theme-text-muted uppercase" title={textoFuentesPagoCompacto(pedido.fuentes_pago).completo || undefined}>
                                        {textoFuentesPagoCompacto(pedido.fuentes_pago).texto}
                                    </td>
                                    <td className="px-5 py-4 text-sm font-black" style={{ color: 'var(--color-primario)' }}>
                                        {formatearMoneda(pedido.total_a_cobrar)}
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className={badge.className} style={badge.style}>
                                            {badge.label}
                                        </span>
                                        {badgeRevision && (
                                            <span className={`${badgeRevision.className} mt-1.5 block w-fit`} style={badgeRevision.style}>{badgeRevision.label}</span>
                                        )}
                                        {badgeEnvio && (
                                            <span className={`${badgeEnvio.className} mt-1.5 block w-fit`} style={badgeEnvio.style}>{badgeEnvio.label}</span>
                                        )}
                                        {badgeObs && (
                                            <span className={`${badgeObs.className} mt-1.5 block w-fit`}>{badgeObs.label}</span>
                                        )}
                                        {badgeSinEx && (
                                            <span className={`${badgeSinEx.className} mt-1.5 block w-fit`}>{badgeSinEx.label}</span>
                                        )}
                                        {badgesSla.map((b) => (
                                            <span key={b.label} className={`${b.className} mt-1.5 block w-fit`} style={b.style}>{b.label}</span>
                                        ))}
                                        {guiaLista && (
                                            <span className={`${badgeGuia.className} mt-1.5 block w-fit`}>{badgeGuia.label}</span>
                                        )}
                                        {guiaLista && pedido.numero_rastreo && (
                                            <p className="text-[9px] font-bold font-mono theme-text-main mt-1 m-0">
                                                {etiquetaOrigenGuia(pedido)}: {pedido.numero_rastreo}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-right overflow-visible">
                                        <AccionesPedido
                                            pedido={pedido}
                                            can={can}
                                            onVer={onVer}
                                            onEditar={onEditar}
                                            onEliminar={onEliminar}
                                            onEliminarRegistro={onEliminarRegistro}
                                            onRestaurar={onRestaurar}
                                            onVerAuditoria={onVerAuditoria}
                                            onCancelar={onCancelar}
                                            onBitacora={onBitacora}
                                            onVerGuia={setDocPreview}
                                            onAnexarEnvio={onAnexarEnvio}
                                            onCompletarEnvio={onCompletarEnvio}
                                            onCargarGuia={onCargarGuia}
                                            puedeEditar={puedeEditar}
                                            puedeEliminarBorrador={puedeEliminarBorrador}
                                            puedeEliminarRegistro={puedeEliminarRegistro}
                                            puedeCancelar={puedeCancelar}
                                            esPapelera={esPapelera}
                                        />
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview} onClose={() => setDocPreview(null)} />
        </div>
    );
}
