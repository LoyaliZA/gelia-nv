import { esRegistroMunicipal, nombreTerminalMunicipal } from '../Municipal/terminalMunicipal';
import React, {
    useCallback, useEffect, useId, useLayoutEffect, useMemo, useRef, useState,
} from 'react';
import { createPortal } from 'react-dom';
import {
    AlertTriangle,
    ArrowRightLeft,
    Ban,
    ChevronDown,
    Clock,
    Eye,
    FileText,
    MapPin,
    Package,
    Store,
    Pencil,
    Repeat,
    RotateCcw,
    ScrollText,
    Shield,
    Trash2,
    Truck,
    Upload,
    Wallet,
} from 'lucide-react';
import { router } from '@inertiajs/react';
import { pedidoEsTrasladoInternoPdv } from './destinoSucursalPedido';
import {
    geliaCardClass,
    GELIA_ICON_BOX,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
} from '../../../utils/geliaTheme';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import {
    formatearMoneda,
    montoVentaPedido,
    etiquetaAlmacen,
    pedidoRequiereLogistica,
    formatearFechaNegocio,
    etiquetaTransportePedido,
    textoFuentesPagoCompacto,
    FASES_PRE_VENTA,
    TABS_PEDIDOS,
    TABS_PEDIDOS_ADMIN,
    badgesRetrasoSla,
} from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import { diasDesde, textoAntiguedad } from './bandejasPedidos';
import {
    accionesPedidoLista,
    agruparAccionesPedido,
    badgesListaPedido,
    elegirAccionPrimaria,
    MAX_BADGES_LISTADO,
} from './pedidosListadoUi';

const MENU_ACCIONES_Z_INDEX = 60;

const ICONO_ACCION_PEDIDO = {
    ver: Eye,
    bitacora: ScrollText,
    auditoria: Shield,
    guia: FileText,
    cargar: Upload,
    completar: FileText,
    anexar: Wallet,
    'traslado-tienda': ArrowRightLeft,
    'seguimiento-traslado': Truck,
    'modalidad-tienda': Repeat,
    'conversion-modalidad': Repeat,
    editar: Pencil,
    espera: Clock,
    cancelar: Ban,
    'eliminar-borrador': Trash2,
    'eliminar-registro': Trash2,
    restaurar: RotateCcw,
};

const CLASE_FILA_MENU = {
    default: 'theme-text-main',
    danger: 'theme-text-peligro',
    warn: 'theme-text-aviso',
};

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

export function MenuAccionesPedido({ acciones, primaria, idBase }) {
    const [abierto, setAbierto] = useState(false);
    const triggerRef = useRef(null);
    const menuRef = useRef(null);
    const [pos, setPos] = useState(null);
    const menuId = `${idBase}-mas`;
    const restantes = acciones.filter((a) => a.key !== primaria?.key);
    const grupos = agruparAccionesPedido(restantes);
    const altoEstimado = Math.min(restantes.length * 40 + grupos.length * 22 + 16, 448);

    const actualizarPosicion = useCallback(() => {
        const el = triggerRef.current;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        setPos({
            top: window.innerHeight - rect.bottom > altoEstimado
                ? rect.bottom + 4
                : Math.max(8, rect.top - Math.min(altoEstimado, window.innerHeight - 16)),
            right: Math.max(8, window.innerWidth - rect.right),
        });
    }, [altoEstimado]);

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
            className="gelia-pedidos-menu fixed min-w-[13rem] max-w-[min(18rem,calc(100vw-1rem))] p-1.5 rounded-xl border theme-border theme-surface shadow-lg flex flex-col"
            style={{ top: pos.top, right: pos.right, zIndex: MENU_ACCIONES_Z_INDEX }}
        >
            {grupos.map((grupo, indice) => (
                <div
                    key={grupo.key}
                    role="group"
                    aria-label={grupo.label}
                    className={indice > 0 ? 'mt-1 pt-1 border-t theme-border' : ''}
                >
                    <p className="px-3 pt-1.5 pb-0.5 text-[10px] font-semibold uppercase tracking-wide theme-text-muted m-0">
                        {grupo.label}
                    </p>
                    {grupo.acciones.map((accion) => {
                        const Icono = accion.icon || ICONO_ACCION_PEDIDO[accion.key];
                        const tono = CLASE_FILA_MENU[accion.tone] || CLASE_FILA_MENU.default;
                        return (
                            <button
                                key={accion.key}
                                type="button"
                                role="menuitem"
                                onClick={() => {
                                    setAbierto(false);
                                    triggerRef.current?.focus();
                                    accion.onClick();
                                }}
                                className={`w-full flex items-center gap-2 px-3 py-2 min-h-11 rounded-lg text-left text-xs font-semibold outline-none hover:bg-black/5 dark:hover:bg-white/5 ${tono}`}
                            >
                                {Icono ? <Icono className="w-4 h-4 shrink-0 opacity-70" aria-hidden /> : null}
                                <span className="leading-tight break-words">{accion.label}</span>
                            </button>
                        );
                    })}
                </div>
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
                className={`${THEME_BTN_SECONDARY} theme-btn-primary--compact min-h-[44px] lg:min-h-[36px] px-3 text-xs font-bold inline-flex items-center gap-1`}
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
        onVer, onEditar, onEliminar, onEliminarRegistro, onRestaurar, onVerAuditoria, onCancelar, onBitacora, onMovimientoTienda,
        onCambiarModalidad, onVerGuia, onAnexarEnvio, onCompletarEnvio, onCargarGuia,
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
        onMovimientoTienda,
        onCambiarModalidad,
        onVerGuia,
        onAnexarEnvio,
        onCompletarEnvio,
        onCargarGuia,
        onEsperaPago: (p) => router.post(route('control_pedidos.espera_pago', p.id), {}, { preserveScroll: true }),
    }), [
        pedido, can, esPapelera, puedeEditar, puedeEliminarBorrador, puedeEliminarRegistro, puedeCancelar,
        onVer, onEditar, onEliminar, onEliminarRegistro, onRestaurar, onVerAuditoria, onCancelar, onBitacora, onMovimientoTienda,
        onCambiarModalidad, onVerGuia, onAnexarEnvio, onCompletarEnvio, onCargarGuia,
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
                className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact min-h-[44px] lg:min-h-[36px] px-3 text-xs font-bold`}
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


function iconoEntrega(pedido) {
    if (esRegistroMunicipal(pedido)) return MapPin;
    if (pedidoEsTrasladoInternoPdv(pedido)) return ArrowRightLeft;
    if (pedido.solicitud_preparacion?.modalidad_entrega === 'tienda' || pedido.usa_preparacion_tienda) return Store;
    return pedidoRequiereLogistica(pedido) ? Truck : Package;
}

function datosEntrega(pedido) {
    if (esRegistroMunicipal(pedido)) {
        return { titulo: 'Envío municipal', destino: `Terminal: ${nombreTerminalMunicipal(pedido)}` };
    }
    const almacen = etiquetaAlmacen(pedido.almacen);
    return { titulo: etiquetaTransportePedido(pedido) || '—', destino: almacen !== '—' ? almacen : null };
}

/** Logística: modalidad (con icono por tipo) y sucursal/almacén de destino. */
function CeldaEntrega({ pedido }) {
    const Icono = iconoEntrega(pedido);
    const { titulo, destino } = datosEntrega(pedido);
    return (
        <div className="flex items-start gap-2.5 min-w-0">
            <span className={`${GELIA_ICON_BOX} w-9 h-9 shrink-0 inline-flex items-center justify-center`} aria-hidden>
                <Icono className="w-4 h-4 theme-text-primario" />
            </span>
            <span className="min-w-0">
                <span className="block text-sm font-semibold theme-text-main leading-snug">{titulo}</span>
                {destino ? <span className="block text-xs theme-text-muted font-medium leading-snug mt-0.5">{destino}</span> : null}
            </span>
        </div>
    );
}

/** Administración: importe a cobrar y forma de pago. */
function CeldaTotal({ pedido, fuentes }) {
    const monto = montoVentaPedido(pedido);
    const esImporte = String(monto).trim().startsWith('$');
    return (
        <div className="text-right">
            {esImporte ? (
                <span className="block text-base font-bold tabular-nums theme-text-main leading-tight">{monto}</span>
            ) : (
                <span className="inline-block whitespace-nowrap px-2.5 py-1 rounded-lg border theme-border theme-element text-xs font-bold theme-text-main">{monto}</span>
            )}
            {fuentes?.texto && fuentes.texto !== '—' ? (
                <span className="block text-xs theme-text-muted font-medium mt-0.5 normal-case" title={fuentes.completo || undefined}>{fuentes.texto}</span>
            ) : null}
        </div>
    );
}

const FASES_CERRADAS = ['ENVIADO', 'CANCELADO', 'ENTREGADO'];

function antiguedadPedido(pedido) {
    if (FASES_CERRADAS.includes(pedido.estatus?.fase_ciclo)) return null;
    return diasDesde(pedido.fecha);
}

function requiereAtencion(pedido, esRechazado) {
    return esRechazado || badgesRetrasoSla(pedido).length > 0;
}

function EstadoVacio({ tabLabel, busqueda, tabActiva, onLimpiarBusqueda, onVerTodas, onNuevo }) {
    const botonSec = `${THEME_BTN_SECONDARY} theme-btn-primary--compact min-h-[44px] px-4 text-xs font-bold`;
    return (
        <div className={`${geliaCardClass()} p-8 md:p-12 text-center space-y-3`}>
            <p className="text-sm font-bold theme-text-main m-0">
                {busqueda ? `Sin resultados para «${busqueda}»` : `No hay pedidos en ${tabLabel ? `«${tabLabel}»` : 'esta bandeja'}`}
            </p>
            <p className="text-xs theme-text-muted m-0">
                {busqueda ? 'Revisa el folio o el nombre, o busca en todas las bandejas.' : 'Cuando haya pedidos en esta etapa aparecerán aquí.'}
            </p>
            <div className="flex flex-wrap items-center justify-center gap-2 pt-1">
                {busqueda && onLimpiarBusqueda ? (
                    <button type="button" onClick={onLimpiarBusqueda} className={botonSec}>Limpiar búsqueda</button>
                ) : null}
                {tabActiva !== 'TODAS' && onVerTodas ? (
                    <button type="button" onClick={onVerTodas} className={botonSec}>Ver todas</button>
                ) : null}
                {!busqueda && onNuevo ? (
                    <button type="button" onClick={onNuevo} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact min-h-[44px] px-4 text-xs font-bold`}>Nuevo pedido</button>
                ) : null}
            </div>
        </div>
    );
}

function EsqueletoListado() {
    return (
        <div className={`${geliaCardClass()} p-4 md:p-5 space-y-3`} aria-busy="true" aria-label="Cargando pedidos">
            {[0, 1, 2, 3, 4].map((i) => (
                <div key={i} className="h-14 rounded-xl theme-element animate-pulse" />
            ))}
        </div>
    );
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

    return (
        <article
            className={`rounded-xl border theme-border theme-element p-3.5 flex flex-col gap-2.5 min-w-0 ${
                requiereAtencion(pedido, esRechazado) ? 'border-l-4 border-l-[var(--color-peligro)]' : ''
            }`}
        >
            <header className="flex items-start justify-between gap-2 min-w-0">
                <button
                    type="button"
                    onClick={() => onVer(pedido)}
                    className="gelia-tienda-op-fila-link text-left min-w-0 flex-1"
                >
                    <EncabezadoFolioPedido pedido={pedido} size="sm" internoCompacto />
                </button>
                <BadgeEstadoPrincipal item={badgePrincipal} />
            </header>

            <div className="space-y-1 min-w-0">
                <p className="text-sm font-bold theme-text-main m-0 leading-snug normal-case">
                    {pedido.cliente?.nombre || '—'}
                </p>
                <p className="text-xs theme-text-muted font-medium m-0 tabular-nums">
                    {formatearFechaNegocio(pedido.fecha)}
                    {antiguedadPedido(pedido) !== null ? ` · ${textoAntiguedad(antiguedadPedido(pedido))}` : ''}
                </p>
                <CeldaEntrega pedido={pedido} />
                {badgesResto.length > 0 && (
                    <BadgesPedido items={badgesResto} compact max={1} className="pt-0.5" />
                )}
                <AlertasFila pedido={pedido} esRechazado={esRechazado} />
            </div>

            <footer className="flex items-center justify-between gap-3 pt-2 border-t theme-border">
                <CeldaTotal pedido={pedido} fuentes={textoFuentesPagoCompacto(pedido.fuentes_pago)} />
                <div className="flex items-center justify-end gap-1.5 shrink-0">
                    {primaria ? (
                        <button
                            type="button"
                            onClick={primaria.onClick}
                            className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact min-h-[44px] lg:min-h-[36px] px-3 text-xs font-bold`}
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
    onLimpiarBusqueda,
    onVerTodas,
    onNuevo,
    onVer,
    onEditar,
    onEliminar,
    onEliminarRegistro,
    onRestaurar,
    onVerAuditoria,
    onCancelar,
    onBitacora,
    onMovimientoTienda,
    onCambiarModalidad,
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
            && (fasesEditarUi.includes(fase) || pedido.puede_continuar_nacional);
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
        onMovimientoTienda,
        onCambiarModalidad,
        onVerGuia: setDocPreview,
        onAnexarEnvio,
        onCompletarEnvio,
        onCargarGuia,
    };

    const vacio = (
        <EstadoVacio
            tabLabel={tabLabel}
            busqueda={busqueda}
            tabActiva={tabActiva}
            onLimpiarBusqueda={onLimpiarBusqueda}
            onVerTodas={onVerTodas}
            onNuevo={onNuevo}
        />
    );

    return (
        <div className="relative space-y-4">
            {cargando && items.length > 0 && (
                <p className="text-xs theme-text-muted m-0 px-1" aria-live="polite">
                    Actualizando listado…
                </p>
            )}
            {items.length === 0 ? (
                cargando ? <EsqueletoListado /> : vacio
            ) : (
                <div className={`${geliaCardClass()} gelia-tienda-op-workspace p-4 md:p-5 space-y-4 relative`}>
                    {cargando && (
                        <div
                            className="absolute inset-0 z-10 rounded-[inherit] bg-[color-mix(in_srgb,var(--theme-surface-bg)_70%,transparent)] pointer-events-none"
                            aria-hidden
                        />
                    )}
                    <div className="flex items-baseline gap-2 pb-1">
                        <h2 className="text-base font-bold theme-text-main m-0">{tabLabel || 'Pedidos'}</h2>
                        <span className="text-sm font-semibold theme-text-muted tabular-nums">{total} pedido{total === 1 ? '' : 's'}</span>
                    </div>

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
                                    <th scope="col" className="min-w-[13rem]">Pedido</th>
                                    <th scope="col" className="min-w-[9rem]">{items.some(esRegistroMunicipal) ? 'Tipo de pedido' : 'Entrega'}</th>
                                    <th scope="col" className="min-w-[9rem]">Estado</th>
                                    <th scope="col" className="w-[7rem]">Antigüedad</th>
                                    <th scope="col" className="!text-right w-[10rem]">Total</th>
                                    <th scope="col" className="!text-right w-[11rem]">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((pedido) => {
                                    const esRechazado = pedido.estatus?.fase_ciclo === 'RECHAZADO_VENDEDORA';
                                    const fuentes = textoFuentesPagoCompacto(pedido.fuentes_pago);
                                    const acciones = accionesPedidoLista({ ...accionesBase, pedido });
                                    const idBase = `${menuId}-${pedido.id}`;
                                    const badgeItems = badgesListaPedido(pedido);
                                    const atencion = requiereAtencion(pedido, esRechazado);
                                    const dias = antiguedadPedido(pedido);

                                    return (
                                        <tr
                                            key={pedido.id}
                                            className={atencion ? 'bg-[color-mix(in_srgb,var(--color-peligro)_5%,transparent)] shadow-[inset_3px_0_0_var(--color-peligro)]' : undefined}
                                        >
                                            <td className="align-top py-3">
                                                <button
                                                    type="button"
                                                    onClick={() => onVer(pedido)}
                                                    className="gelia-tienda-op-fila-link text-left w-full min-w-0"
                                                >
                                                    <EncabezadoFolioPedido pedido={pedido} size="md" internoCompacto />
                                                    <span className="block text-sm font-semibold theme-text-main mt-1 normal-case leading-snug">
                                                        {pedido.cliente?.nombre || '—'}
                                                    </span>
                                                </button>
                                                <AlertasFila pedido={pedido} esRechazado={esRechazado} />
                                            </td>
                                            <td className="align-top py-3">
                                                <CeldaEntrega pedido={pedido} />
                                            </td>
                                            <td className="align-top py-3">
                                                <BadgesPedido items={badgeItems} compact max={2} />
                                            </td>
                                            <td className="align-top py-3 text-xs font-medium tabular-nums whitespace-nowrap">
                                                <span className={`block font-semibold theme-text-main`}>
                                                    {dias === null ? formatearFechaNegocio(pedido.fecha) : textoAntiguedad(dias)}
                                                </span>
                                                {dias !== null ? <span className="block mt-0.5 theme-text-muted">{formatearFechaNegocio(pedido.fecha)}</span> : null}
                                            </td>
                                            <td className="align-top py-3 text-right">
                                                <CeldaTotal pedido={pedido} fuentes={fuentes} />
                                            </td>
                                            <td className="align-top py-3 text-right">
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
