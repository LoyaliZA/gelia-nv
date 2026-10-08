import React, { useEffect, useMemo, useState } from 'react';
import { History } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_ICON } from '../../../../utils/geliaTheme';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';
import {
    badgeEstatusPedido,
    badgeRetrasoGuia,
    badgesRetrasoSla,
    badgeResguardoSemantico,
    badgeCorregirGuia,
    tieneErrorGuiaReportado,
    LABELS_ESTATUS_POR_FASE,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import BloqueVendedorPedido from '../../Partials/BloqueVendedorPedido';
import BotonAccionCubico from '../../Partials/BotonAccionCubico';
import ModalDetalleDelegado from './ModalDetalleDelegado';
import ModalReportarErrorDatos from '../../Partials/ModalReportarErrorDatos';
import ModalBitacoraPedido from '../../Partials/ModalBitacoraPedido';
import { etiquetaCtaListadoDelegado, pedidoConSenialOperativa } from './delegadoUi';

const MAX_BADGES_VISIBLE = 2;

function badgesLista(pedido) {
    const badge = badgeEstatusPedido(pedido.estatus, { esResguardo: pedido.es_resguardo });
    const items = [{ key: 'estatus', className: badge.className, style: badge.style, label: badge.label }];
    const errorGuia = tieneErrorGuiaReportado(pedido) ? badgeCorregirGuia() : null;
    if (errorGuia) items.push({ key: 'error', ...errorGuia });
    if (pedido.guia_retraso) {
        const retraso = badgeRetrasoGuia();
        items.push({ key: 'retraso', ...retraso });
    }
    badgesRetrasoSla(pedido).forEach((b) => items.push({ key: b.label, ...b }));
    if (pedido.es_resguardo && !pedido.estatus?.fase_ciclo) {
        const resguardo = badgeResguardoSemantico();
        items.push({ key: 'resguardo', ...resguardo });
    }
    return items;
}

function BadgesPedido({ pedido, className = 'justify-end' }) {
    const items = useMemo(() => badgesLista(pedido), [pedido]);
    const visibles = items.slice(0, MAX_BADGES_VISIBLE);
    const extra = items.length - visibles.length;

    return (
        <div className={`flex flex-wrap gap-1.5 items-center ${className}`}>
            {visibles.map((b) => (
                <span key={b.key} className={b.className} style={b.style}>{b.label}</span>
            ))}
            {extra > 0 && (
                <span className="text-xs font-bold theme-text-muted tabular-nums" title={`${extra} señal(es) más`}>
                    +{extra}
                </span>
            )}
        </div>
    );
}

function claseFilaPedido(pedido) {
    if (pedido.es_resguardo) {
        return 'bg-[color-mix(in_srgb,var(--color-info)_6%,transparent)]';
    }
    if (pedidoConSenialOperativa(pedido)) {
        return 'bg-[color-mix(in_srgb,var(--color-aviso)_6%,transparent)]';
    }
    return '';
}

function CardPedidoDelegado({ pedido, onAbrir, onBitacora }) {
    const senial = pedidoConSenialOperativa(pedido);
    const cta = etiquetaCtaListadoDelegado(pedido);

    return (
        <article
            className={`${geliaCardClass()} overflow-hidden w-full ${
                pedido.es_resguardo
                    ? 'ring-2 ring-[color-mix(in_srgb,var(--color-info)_40%,transparent)]'
                    : ''
            } ${senial ? 'ring-2 ring-[color-mix(in_srgb,var(--color-aviso)_35%,transparent)]' : ''}`}
        >
            {(senial || pedido.es_resguardo) && (
                <p
                    className={`m-0 px-3 py-1.5 text-xs font-bold ${
                        senial
                            ? 'gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--aviso'
                            : 'gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--info'
                    }`}
                >
                    {senial ? 'Retraso o incidencia de guía' : 'Resguardo'}
                </p>
            )}
            <div className="p-4 space-y-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <EncabezadoFolioPedido pedido={pedido} size="sm" />
                        <p className="text-sm font-bold theme-text-main m-0 mt-1 truncate">{pedido.cliente?.nombre || '—'}</p>
                        <BloqueVendedorPedido pedido={pedido} variante="nombre" className="mt-1" />
                    </div>
                    <BadgesPedido pedido={pedido} className="justify-end max-w-[45%]" />
                </div>
                <div className="text-xs font-semibold theme-text-muted uppercase">
                    {pedido.paqueteria?.nombre || '—'}
                </div>
                {pedido.numero_rastreo ? (
                    <p className="m-0 px-3 py-2 rounded-xl theme-element border theme-border font-mono text-sm font-bold theme-text-main break-all">
                        {pedido.numero_rastreo}
                    </p>
                ) : (
                    <p className="text-sm font-semibold theme-text-muted m-0">Sin guía capturada</p>
                )}
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => onAbrir(pedido)}
                        className={`${THEME_BTN_PRIMARY} flex-1 min-h-[44px] text-xs`}
                    >
                        {cta === 'Capturar' ? 'Capturar guía' : 'Ver detalle'}
                    </button>
                    <button
                        type="button"
                        onClick={() => onBitacora(pedido)}
                        className={`${THEME_BTN_ICON} min-h-[44px] min-w-[44px] shrink-0`}
                        aria-label="Bitácora"
                    >
                        <History className="w-4 h-4" />
                    </button>
                </div>
            </div>
        </article>
    );
}

export default function TablaDelegado({
    pedidos,
    tabActiva = 'PENDIENTES_GUIA',
    tabLabel = '',
    busqueda = '',
    cargando = false,
    onModalAbierto,
    onIrAPagina,
    onLimpiarBusqueda,
    mensajeFiltroVacio = null,
    onLimpiarFiltros,
}) {
    const [pedidoDetalle, setPedidoDetalle] = useState(null);
    const [pedidoError, setPedidoError] = useState(null);
    const [pedidoBitacora, setPedidoBitacora] = useState(null);
    const items = pedidos?.data || [];
    const total = pedidos?.total ?? items.length;

    const modalAbierto = Boolean(pedidoDetalle || pedidoError || pedidoBitacora);

    useEffect(() => {
        onModalAbierto?.(modalAbierto);
    }, [modalAbierto, onModalAbierto]);

    const vacio = {
        TODOS: 'No hay pedidos en la bandeja de guías.',
        PENDIENTES_GUIA: `No hay pedidos en ${LABELS_ESTATUS_POR_FASE.PENDIENTE_DE_GUIA}.`,
        EN_CEDIS: `No hay pedidos con guía en ${LABELS_ESTATUS_POR_FASE.EN_CEDIS}.`,
        PENDIENTES_ENVIO: `No hay pedidos en ${LABELS_ESTATUS_POR_FASE.PENDIENTE_DE_ENVIO}.`,
        ENVIADOS: `No hay pedidos en ${LABELS_ESTATUS_POR_FASE.ENVIADO}.`,
    }[tabActiva] || 'No hay pedidos en esta bandeja.';

    const cerrarDetalle = () => setPedidoDetalle(null);

    const alReportarExito = () => {
        setPedidoError(null);
        setPedidoDetalle(null);
    };

    const vacioContenido = (
        <div className={`${geliaCardClass()} p-10 md:p-16 text-center space-y-3`}>
            <p className="text-sm font-bold theme-text-muted m-0">{mensajeFiltroVacio || vacio}</p>
            {mensajeFiltroVacio && onLimpiarFiltros && (
                <button type="button" onClick={onLimpiarFiltros} className="text-sm font-bold theme-text-primario underline min-h-[44px] px-2">
                    Limpiar filtros
                </button>
            )}
            {!mensajeFiltroVacio && busqueda && onLimpiarBusqueda && (
                <button type="button" onClick={onLimpiarBusqueda} className="text-sm font-bold theme-text-primario underline min-h-[44px] px-2">
                    Limpiar búsqueda
                </button>
            )}
        </div>
    );

    return (
        <div className="relative space-y-4">
            {cargando && items.length > 0 && (
                <p className="text-xs theme-text-muted m-0 px-1" aria-live="polite">
                    Actualizando listado…
                </p>
            )}
            {items.length === 0 ? (
                vacioContenido
            ) : (
                <div className={`${geliaCardClass()} gelia-tienda-op-workspace p-4 md:p-5 space-y-4`}>
                    <div className="min-w-0">
                        <p className="text-sm font-bold theme-text-main m-0 tabular-nums">
                            {total} pedido{total === 1 ? '' : 's'}
                            {tabLabel ? <span className="theme-text-muted font-semibold"> · {tabLabel}</span> : null}
                        </p>
                    </div>

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

                    <div className="gelia-tienda-op-table-wrap hidden md:block">
                        <table className="gelia-tienda-op-table">
                            <thead>
                                <tr>
                                    <th scope="col">Folio / Cliente</th>
                                    <th scope="col">Paquetería</th>
                                    <th scope="col">Guía</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col" className="text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((pedido) => {
                                    const cta = etiquetaCtaListadoDelegado(pedido);
                                    const alerta = pedidoConSenialOperativa(pedido);
                                    return (
                                        <tr
                                            key={pedido.id}
                                            className={claseFilaPedido(pedido)}
                                            data-alerta={alerta ? 'true' : undefined}
                                        >
                                            <td>
                                                <button
                                                    type="button"
                                                    onClick={() => setPedidoDetalle(pedido)}
                                                    className="gelia-tienda-op-fila-link text-left w-full max-w-[14rem]"
                                                >
                                                    <EncabezadoFolioPedido pedido={pedido} size="sm" />
                                                    <span className="block text-xs font-bold theme-text-main truncate mt-0.5 normal-case">
                                                        {pedido.cliente?.nombre || '—'}
                                                    </span>
                                                </button>
                                            </td>
                                            <td className="text-xs font-bold theme-text-muted uppercase">
                                                {pedido.paqueteria?.nombre || '—'}
                                            </td>
                                            <td className="font-mono text-xs font-bold theme-text-main">
                                                {pedido.numero_rastreo || '—'}
                                            </td>
                                            <td>
                                                <BadgesPedido pedido={pedido} className="justify-start" />
                                            </td>
                                            <td className="text-right">
                                                <div className="inline-flex items-center justify-end gap-1.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => setPedidoDetalle(pedido)}
                                                        className="text-xs font-bold theme-text-primario hover:underline min-h-[36px] px-2"
                                                    >
                                                        {cta}
                                                    </button>
                                                    <BotonAccionCubico
                                                        icon={History}
                                                        label="Bitácora"
                                                        onClick={() => setPedidoBitacora(pedido)}
                                                        tone="purple"
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {onIrAPagina && pedidos && (
                        <div className="pt-2 border-t theme-border">
                            <GeliaPaginacion
                                paginator={pedidos}
                                onIrAPagina={onIrAPagina}
                                embedded
                                className="!border-0 !p-0"
                            />
                        </div>
                    )}
                </div>
            )}

            <ModalDetalleDelegado
                abierto={Boolean(pedidoDetalle)}
                pedido={pedidoDetalle}
                onClose={cerrarDetalle}
                onPedidoActualizado={setPedidoDetalle}
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
