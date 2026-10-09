import React, {
    useCallback, useEffect, useId, useLayoutEffect, useMemo, useRef, useState,
} from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle, ChevronDown } from 'lucide-react';
import { router } from '@inertiajs/react';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
} from '../../../utils/geliaTheme';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import {
    formatearMoneda,
    etiquetaAlmacen,
    formatearFechaNegocio,
    etiquetaOrigenGuia,
    etiquetaTransportePedido,
    pedidoRequiereLogistica,
    textoFuentesPagoCompacto,
    FASES_PRE_VENTA,
    TABS_PEDIDOS,
    TABS_PEDIDOS_ADMIN,
} from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';
import BotonAccionCubico from './BotonAccionCubico';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import {
    accionesPedidoLista,
    badgesListaPedido,
    elegirAccionPrimaria,
    MAX_BADGES_LISTADO,
} from './pedidosListadoUi';

const MENU_ACCIONES_Z_INDEX = 60;

function textoTiempoRestante(iso) {
    if (!iso) return null;
    const ms = new Date(iso).getTime() - Date.now();
    if (Number.isNaN(ms)) return null;
    if (ms <= 0) return 'Vencido — pendiente liberación';
    const h = Math.floor(ms / 3600000);
    const d = Math.floor(h / 24);
    if (d > 0) return `Quedan ${d} día(s) y ${h % 24} h`;
    if (h > 0) return `Quedan ${h} hora(s)`;
    return `Quedan ${Math.max(1, Math.floor(ms / 60000))} min`;
}

function useBadgesItems(pedido) {
    return useMemo(() => badgesListaPedido(pedido), [pedido]);
}

function BadgesPedido({ items, className = 'justify-start', max = MAX_BADGES_LISTADO, compact = false }) {
    const visibles = items.slice(0, max);
    const gap = compact ? 'gap-1' : 'gap-1.5';

    return (
        <div className={`flex flex-wrap ${gap} items-center ${className}`}>
            {visibles.map((b) => (
                <span
                    key={b.key}
                    className={`${b.className} ${compact ? 'gelia-pedidos-bma-badge--compact max-w-[11rem] truncate' : ''}`}
                    style={b.style}
                    title={b.label}
                >
                    {b.label}
                </span>
            ))}
            {items.length > visibles.length && (
                <details className="gelia-pedidos-estados-extra">
                    <summary className="text-xs font-semibold theme-text-muted tabular-nums cursor-pointer list-none" aria-label="Ver estados adicionales">+{items.length - visibles.length} estados</summary>
                    <div className="flex flex-wrap gap-1.5 pt-2">{items.slice(max).map((badge) => <span key={badge.key} className={badge.className} style={badge.style}>{badge.label}</span>)}</div>
                </details>
            )}
        </div>
    );
}

function BadgeEstadoPrincipal({ item }) {
    if (!item) return null;
    return (
        <span
            className={`${item.className} max-w-[10.5rem] truncate shrink-0`}
            style={item.style}
            title={item.label}
        >
            {item.label}
        </span>
    );
}

function MenuAccionesPedido({ acciones, primaria, idBase }) {
    const [abierto, setAbierto] = useState(false);
    const triggerRef = useRef(null);
    const menuRef = useRef(null);
    const [pos, setPos] = useState(null);
    const menuId = `${idBase}-mas`;
    const restantes = acciones.filter((a) => a.key !== primaria?.key);

    const actualizarPosicion = useCallback(() => {
        const el = triggerRef.current;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        setPos({
            top: window.innerHeight - rect.bottom > Math.min(restantes.length * 48 + 16, 448)
                ? rect.bottom + 4
                : Math.max(8, rect.top - Math.min(restantes.length * 48 + 16, window.innerHeight - 16)),
            right: Math.max(8, window.innerWidth - rect.right),
        });
    }, [restantes.length]);

    useLayoutEffect(() => {
        if (!abierto) {
            setPos(null);
            return undefined;
        }
        actualizarPosicion();
        window.addEventListener('resize', actualizarPosicion);
        window.addEventListener('scroll', actualizarPosicion, true);
        return () => {
            window.removeEventListener('resize', actualizarPosicion);
            window.removeEventListener('scroll', actualizarPosicion, true);
        };
    }, [abierto, actualizarPosicion]);

    useEffect(() => {
        if (!abierto) return undefined;
        const onDoc = (e) => {
            const t = e.target;
            if (triggerRef.current?.contains(t) || menuRef.current?.contains(t)) return;
            setAbierto(false);
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, [abierto]);

    useEffect(() => {
        if (!abierto || !pos) return;
        menuRef.current?.querySelector('button')?.focus({ preventScroll: true });
    }, [abierto, Boolean(pos)]);

    const tecladoMenu = (event) => {
        const controles = [...menuRef.current.querySelectorAll('button:not([disabled])')];
        const index = controles.indexOf(document.activeElement);
        if (event.key === 'Escape') {
            event.preventDefault(); setAbierto(false); triggerRef.current?.focus();
        } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            const siguiente = event.key === 'Home' ? 0 : event.key === 'End' ? controles.length - 1
                : (index + (event.key === 'ArrowDown' ? 1 : -1) + controles.length) % controles.length;
            controles[siguiente]?.focus();
        } else if (event.key === 'Tab') setAbierto(false);
    };

    if (restantes.length === 0) return null;

    const menu = abierto && pos ? (
        <div
            ref={menuRef}
            role="menu"
            onKeyDown={tecladoMenu}
            aria-labelledby={menuId}
            className="gelia-pedidos-menu fixed min-w-[12rem] max-w-[min(16rem,calc(100vw-1rem))] p-2 rounded-xl border theme-border theme-surface shadow-lg flex flex-col gap-1"
            style={{ top: pos.top, right: pos.right, zIndex: MENU_ACCIONES_Z_INDEX }}
        >
            {restantes.map((accion) => (
                <BotonAccionCubico
                    key={accion.key}
                    role="menuitem"
                    icon={accion.icon}
                    label={accion.label}
                    onClick={() => {
                        setAbierto(false);
                        triggerRef.current?.focus();
                        accion.onClick();
                    }}
                    tone={accion.tone || 'default'}
                    conLabel
                    className="!w-full"
                />
            ))}
        </div>
    ) : null;

    return (
        <>
            <button
                ref={triggerRef}
                type="button"
                id={menuId}
                aria-expanded={abierto}
                aria-haspopup="menu"
                onClick={() => setAbierto((v) => !v)}
                className={`${THEME_BTN_SECONDARY} theme-btn-primary--compact min-h-[44px] px-3 text-xs font-bold inline-flex items-center gap-1`}
            >
                Más
                <ChevronDown className={`w-3.5 h-3.5 transition-transform ${abierto ? 'rotate-180' : ''}`} aria-hidden />
            </button>
            {typeof document !== 'undefined' && menu ? createPortal(menu, document.body) : null}
        </>
    );
}

function useAccionesProps(props) {
    const {
        pedido, can, esPapelera, puedeEditar, puedeEliminarBorrador, puedeEliminarRegistro, puedeCancelar,
        onVer, onEditar, onEliminar, onEliminarRegistro, onRestaurar, onVerAuditoria, onCancelar, onBitacora,
        onVerGuia, onAnexarEnvio, onCompletarEnvio, onCargarGuia,
    } = props;

    return useMemo(() => accionesPedidoLista({
        pedido,
        can,
        esPapelera,
        puedeEditar,
        puedeEliminarBorrador,
        puedeEliminarRegistro,
        puedeCancelar,
        onVer,
        onEditar,
        onEliminar,
        onEliminarRegistro,
        onRestaurar,
        onVerAuditoria,
        onCancelar,
        onBitacora,
        onVerGuia,
        onAnexarEnvio,
        onCompletarEnvio,
        onCargarGuia,
        onEsperaPago: (p) => router.post(route('control_pedidos.espera_pago', p.id), {}, { preserveScroll: true }),
    }), [
        pedido, can, esPapelera, puedeEditar, puedeEliminarBorrador, puedeEliminarRegistro, puedeCancelar,
        onVer, onEditar, onEliminar, onEliminarRegistro, onRestaurar, onVerAuditoria, onCancelar, onBitacora,
        onVerGuia, onAnexarEnvio, onCompletarEnvio, onCargarGuia,
    ]);
}

function AccionesFila({ acciones, idBase }) {
    const primaria = elegirAccionPrimaria(acciones);
    if (!primaria) return null;

    return (
        <div className="inline-flex items-center justify-end gap-1.5 flex-wrap">
            <button
                type="button"
                onClick={primaria.onClick}
                className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact min-h-[44px] px-3 text-xs font-bold`}
            >
                {primaria.label}
            </button>
            <MenuAccionesPedido acciones={acciones} primaria={primaria} idBase={idBase} />
        </div>
    );
}

function AlertasFila({ pedido, esRechazado }) {
    return (
        <>
            {pedido.esta_esperando_pago && (
                <p className="text-xs font-bold theme-text-aviso m-0 mt-1">
                    Esperando pago · {textoTiempoRestante(pedido.fecha_limite_espera) || 'Mercancía resguardada'}
                </p>
            )}
            {pedido.cancelacion_operativa && (
                <p className="text-xs font-bold theme-text-peligro m-0 mt-1">
                    Cancelación {pedido.cancelacion_operativa.estado}
                    {pedido.cancelacion_operativa.requiere_resolucion_financiera ? ' · Requiere resolución financiera' : ''}
                </p>
            )}
            {esRechazado && pedido.motivo_rechazo && (
                <p className="text-xs theme-text-peligro font-bold m-0 mt-1 flex items-start gap-1">
                    <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" aria-hidden />
                    {pedido.motivo_rechazo}
                </p>
            )}
        </>
    );
}

function lineaLogisticaPedido(pedido) {
    const transporte = etiquetaTransportePedido(pedido);
    const alm = etiquetaAlmacen(pedido.almacen);
    if (transporte && alm && alm !== '—') return `${transporte} · ${alm}`;
    if (transporte) return transporte;
    if (alm && alm !== '—') return alm;
    return null;
}

function CardPedido({
    pedido, esRechazado, accionesProps, onVer,
}) {
    const acciones = useAccionesProps(accionesProps);
    const primaria = elegirAccionPrimaria(acciones);
    const idBase = `pedido-card-${pedido.id}`;
    const badgeItems = useBadgesItems(pedido);
    const badgePrincipal = badgeItems[0] || null;
    const badgesResto = badgeItems.slice(1);
    const logistica = lineaLogisticaPedido(pedido);

    return (
        <article
            className={`rounded-xl border theme-border theme-element p-3.5 flex flex-col gap-2.5 min-w-0 ${
                esRechazado ? 'ring-1 ring-[color-mix(in_srgb,var(--color-peligro)_30%,transparent)]' : ''
            }`}
        >
            <header className="flex items-start justify-between gap-2 min-w-0">
                <button
                    type="button"
                    onClick={() => onVer(pedido)}
                    className="gelia-tienda-op-fila-link text-left min-w-0 flex-1"
                >
                    <EncabezadoFolioPedido pedido={pedido} size="sm" />
                </button>
                <BadgeEstadoPrincipal item={badgePrincipal} />
            </header>

            <div className="space-y-1 min-w-0">
                <p className="text-sm font-bold theme-text-main m-0 leading-snug normal-case">
                    {pedido.cliente?.nombre || '—'}
                </p>
                <p className="text-xs theme-text-muted font-medium m-0 tabular-nums">
                    {formatearFechaNegocio(pedido.fecha)}
                </p>
                {logistica && (
                    <p className="text-xs theme-text-muted font-medium m-0 leading-snug">{logistica}</p>
                )}
                {badgesResto.length > 0 && (
                    <BadgesPedido items={badgesResto} compact max={1} className="pt-0.5" />
                )}
                <AlertasFila pedido={pedido} esRechazado={esRechazado} />
            </div>

            <footer className="flex items-center justify-between gap-3 pt-2 border-t theme-border">
                <p className="text-sm font-bold tabular-nums theme-text-main m-0">
                    {formatearMoneda(pedido.total_a_cobrar)}
                </p>
                <div className="flex items-center justify-end gap-1.5 shrink-0">
                    {primaria ? (
                        <button
                            type="button"
                            onClick={primaria.onClick}
                            className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact min-h-[44px] px-3 text-xs font-bold`}
                        >
                            {primaria.label}
                        </button>
                    ) : null}
                    {acciones.length > 1 ? (
                        <MenuAccionesPedido acciones={acciones} primaria={primaria} idBase={idBase} />
                    ) : null}
                    {accionesProps.can('control_pedidos.ver_detalle') && !primaria && (
                        <button
                            type="button"
                            onClick={() => onVer(pedido)}
                            className={`${THEME_BTN_SECONDARY} theme-btn-primary--compact min-h-[44px] px-3 text-xs`}
                        >
                            Ver
                        </button>
                    )}
                </div>
            </footer>
        </article>
    );
}

export default function TablaPedidos({
    pedidos,
    can,
    tabActiva = 'TODAS',
    busqueda = '',
    cargando = false,
    onIrAPagina,
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
    const total = pedidos?.total ?? items.length;
    const esPapelera = tabActiva === 'ELIMINADAS';
    const menuId = useId();

    const tabMeta = [...TABS_PEDIDOS, ...TABS_PEDIDOS_ADMIN].find((t) => t.id === tabActiva);
    const tabLabel = tabMeta?.label || '';

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
        && (Boolean(pedido.puede_cancelar) || Boolean(pedido.cancelacion_operativa))
        && puedeMutarPedido(pedido)
        && can('control_pedidos.cancelar')
        && pedido.estatus?.fase_ciclo !== 'CANCELADO';

    const accionesBase = {
        can,
        esPapelera,
        puedeEditar,
        puedeEliminarBorrador,
        puedeEliminarRegistro,
        puedeCancelar,
        onVer,
        onEditar,
        onEliminar,
        onEliminarRegistro,
        onRestaurar,
        onVerAuditoria,
        onCancelar,
        onBitacora,
        onVerGuia: setDocPreview,
        onAnexarEnvio,
        onCompletarEnvio,
        onCargarGuia,
    };

    const vacio = (
        <div className={`${geliaCardClass()} p-10 md:p-16 text-center`}>
            <p className="text-sm font-bold theme-text-muted m-0">
                Sin pedidos en esta vista
                {tabLabel ? ` (${tabLabel})` : ''}.
            </p>
            {busqueda && (
                <p className="text-xs theme-text-muted mt-2 m-0">Prueba otra búsqueda o bandeja.</p>
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
                vacio
            ) : (
                <div className={`${geliaCardClass()} gelia-tienda-op-workspace p-4 md:p-5 space-y-4 relative`}>
                    {cargando && (
                        <div
                            className="absolute inset-0 z-10 rounded-[inherit] bg-[color-mix(in_srgb,var(--theme-surface-bg)_70%,transparent)] pointer-events-none"
                            aria-hidden
                        />
                    )}
                    <p className="text-sm font-bold theme-text-main m-0 tabular-nums">
                        {total} pedido{total === 1 ? '' : 's'}
                        {tabLabel ? <span className="theme-text-muted font-semibold"> · {tabLabel}</span> : null}
                    </p>

                    <div className="lg:hidden space-y-3 sm:space-y-3.5">
                        {items.map((pedido) => (
                            <CardPedido
                                key={pedido.id}
                                pedido={pedido}
                                esRechazado={pedido.estatus?.fase_ciclo === 'RECHAZADO_VENDEDORA'}
                                accionesProps={{ ...accionesBase, pedido }}
                                onVer={onVer}
                            />
                        ))}
                    </div>

                    <div className="gelia-tienda-op-table-wrap hidden lg:block">
                        <table className="gelia-tienda-op-table gelia-pedidos-bma-listado w-full">
                            <thead>
                                <tr>
                                    <th scope="col" className="min-w-[12rem]">Pedido</th>
                                    <th scope="col" className="min-w-[9rem]">Logística</th>
                                    <th scope="col" className="text-right w-[7.5rem]">Total</th>
                                    <th scope="col" className="min-w-[8rem]">Estado</th>
                                    <th scope="col" className="text-right w-[11rem]">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((pedido) => {
                                    const esRechazado = pedido.estatus?.fase_ciclo === 'RECHAZADO_VENDEDORA';
                                    const fuentes = textoFuentesPagoCompacto(pedido.fuentes_pago);
                                    const acciones = accionesPedidoLista({ ...accionesBase, pedido });
                                    const idBase = `${menuId}-${pedido.id}`;
                                    const badgeItems = badgesListaPedido(pedido);

                                    return (
                                        <tr
                                            key={pedido.id}
                                            className={esRechazado ? 'bg-[color-mix(in_srgb,var(--color-peligro)_5%,transparent)]' : undefined}
                                        >
                                            <td className="align-top py-2.5">
                                                <button
                                                    type="button"
                                                    onClick={() => onVer(pedido)}
                                                    className="gelia-tienda-op-fila-link text-left w-full min-w-0"
                                                >
                                                    <EncabezadoFolioPedido pedido={pedido} size="sm" />
                                                    <span className="block text-sm font-bold theme-text-main mt-1 normal-case leading-snug">
                                                        {pedido.cliente?.nombre || '—'}
                                                    </span>
                                                    <span className="block text-xs theme-text-muted font-medium mt-0.5 tabular-nums">
                                                        {formatearFechaNegocio(pedido.fecha)}
                                                    </span>
                                                </button>
                                                <AlertasFila pedido={pedido} esRechazado={esRechazado} />
                                            </td>
                                            <td className="align-top py-2.5 text-xs theme-text-muted font-medium leading-relaxed">
                                                <span className="block theme-text-main font-semibold">
                                                    {etiquetaTransportePedido(pedido) || '—'}
                                                </span>
                                                <span className="block mt-0.5">{etiquetaAlmacen(pedido.almacen)}</span>
                                                {fuentes.texto !== '—' && (
                                                    <span className="block mt-0.5 normal-case opacity-90" title={fuentes.completo || undefined}>
                                                        {fuentes.texto}
                                                    </span>
                                                )}
                                                {pedidoRequiereLogistica(pedido) && (
                                                    <span className="block mt-0.5 normal-case text-xs opacity-80">
                                                        {etiquetaOrigenGuia(pedido)}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="align-top py-2.5 text-right whitespace-nowrap">
                                                <span className="text-sm font-bold tabular-nums theme-text-main">
                                                    {formatearMoneda(pedido.total_a_cobrar)}
                                                </span>
                                            </td>
                                            <td className="align-top py-2.5">
                                                <BadgesPedido items={badgeItems} compact />
                                            </td>
                                            <td className="align-top py-2.5 text-right">
                                                <AccionesFila acciones={acciones} idBase={idBase} />
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

            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview} onClose={() => setDocPreview(null)} />
        </div>
    );
}
