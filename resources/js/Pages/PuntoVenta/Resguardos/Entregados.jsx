import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Eye, History, Loader2 } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import SelectorSucursalActivaPdv from '@/Components/PuntoVenta/SelectorSucursalActivaPdv';
import FiltrosHistorialEntregados from './Partials/FiltrosHistorialEntregados';
import ModalDetalleResguardo from './Partials/ModalDetalleResguardo';
import useHistorialEntregadosResguardo from './Partials/useHistorialEntregadosResguardo';
import {
    BTN_SECONDARY,
    formatearFechaOperativa,
    RESGUARDOS_BTN_ICON_TABLA,
    RESGUARDOS_TABLA_FILA,
    RESGUARDOS_TABLA_HEAD,
} from './Partials/resguardosStyles';
import MiniaturaEvidenciaResguardo from './Partials/MiniaturaEvidenciaResguardo';
import useToastAlCambiar from '../../../hooks/useToastAlCambiar';

export default function Entregados({
    auth,
    resguardos,
    filtros = {},
    sucursal_activa: sucursalActiva = null,
    sucursales_asignadas: sucursalesAsignadas = [],
    puede_ver_bandeja: puedeVerBandeja = false,
    detalle_modal_id: detalleModalIdInicial = null,
}) {
    const {
        resguardos: resguardosVista,
        cargando,
        error,
        cargar,
    } = useHistorialEntregadosResguardo({
        listadoRoute: 'punto_venta.resguardos.entregados.listado',
        indexRoute: 'punto_venta.resguardos.entregados.index',
        resguardos,
        filtros,
    });

    useToastAlCambiar(error, 'error');

    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [desde, setDesde] = useState(filtros.desde || '');
    const [hasta, setHasta] = useState(filtros.hasta || '');
    const [detalleResguardoId, setDetalleResguardoId] = useState(null);
    const debounceBusqueda = useRef(null);

    useEffect(() => {
        setBusqueda(filtros.q || '');
        setDesde(filtros.desde || '');
        setHasta(filtros.hasta || '');
    }, [filtros]);

    useEffect(() => {
        if (!detalleModalIdInicial) return;
        setDetalleResguardoId(detalleModalIdInicial);
    }, [detalleModalIdInicial]);

    const paramsActuales = (extra = {}) => ({
        q: busqueda || undefined,
        desde: desde || undefined,
        hasta: hasta || undefined,
        page: resguardosVista?.current_page || 1,
        ...extra,
    });

    const recargar = (extra = {}, opts) => cargar(paramsActuales(extra), opts);

    const onBusqueda = (valor) => {
        setBusqueda(valor);
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            recargar({ q: valor || undefined, page: 1 });
        }, 400);
    };

    const onDesde = (valor) => {
        setDesde(valor);
        recargar({ desde: valor || undefined, page: 1 });
    };

    const onHasta = (valor) => {
        setHasta(valor);
        recargar({ hasta: valor || undefined, page: 1 });
    };

    const onLimpiar = () => {
        setBusqueda('');
        setDesde('');
        setHasta('');
        recargar({ q: undefined, desde: undefined, hasta: undefined, page: 1 });
    };

    const onIrAPagina = (page) => recargar({ page });

    const hayFiltrosActivos = Boolean(busqueda || desde || hasta);
    const filas = resguardosVista?.data || [];

    const cerrarDetalle = useCallback(() => setDetalleResguardoId(null), []);

    return (
        <AppLayout auth={auth}>
            <Head title="Historial de entregas | Resguardos PDV" />
            <GeliaPageShell className="space-y-3 md:space-y-4">
                <GeliaTituloCard
                    eyebrow="Punto de Venta"
                    title="Historial de entregas"
                    titleHighlight="solo consulta"
                    icon={null}
                    aside={(
                        <div className="flex flex-wrap items-center gap-2 shrink-0 self-start md:self-center">
                            {puedeVerBandeja && (
                                <Link
                                    href={route('punto_venta.resguardos.index')}
                                    className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px] px-4 no-underline`}
                                >
                                    <ClipboardList className="w-4 h-4" /> Bandeja operativa
                                </Link>
                            )}
                            <div className="p-2.5 rounded-xl theme-element border theme-border flex items-center justify-center">
                                <History className="w-5 h-5" style={{ color: 'var(--color-primario)' }} aria-hidden />
                            </div>
                        </div>
                    )}
                    className="!p-4 md:!p-5 lg:!p-6 !gap-3 md:!gap-4 [&_h1]:!text-2xl sm:[&_h1]:!text-3xl md:[&_h1]:!text-3xl"
                >
                    <p className="text-sm theme-text-muted m-0 max-w-2xl">
                        Resguardos con entrega total registrada en la sucursal activa. Abre el detalle para revisar bultos, evidencias y auditoría.
                    </p>
                    <SelectorSucursalActivaPdv
                        sucursalActiva={sucursalActiva}
                        sucursalesAsignadas={sucursalesAsignadas}
                        variante="compacto"
                        reloadOnly={['sucursal_activa', 'resguardos', 'filtros']}
                    />
                </GeliaTituloCard>

                <FiltrosHistorialEntregados
                    busqueda={busqueda}
                    onBusqueda={onBusqueda}
                    desde={desde}
                    onDesde={onDesde}
                    hasta={hasta}
                    onHasta={onHasta}
                    cargando={cargando}
                    hayFiltrosActivos={hayFiltrosActivos}
                    onLimpiar={onLimpiar}
                />

                <div className={`${geliaCardClass()} overflow-hidden`}>
                    {cargando && filas.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-3 py-16">
                            <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} />
                            <p className="text-sm font-semibold theme-text-muted m-0">Cargando historial…</p>
                        </div>
                    ) : filas.length === 0 ? (
                        <p className="text-sm font-semibold theme-text-muted text-center m-0 py-12 px-4">
                            {hayFiltrosActivos
                                ? 'No hay entregas que coincidan con los filtros.'
                                : 'Aún no hay resguardos entregados en esta sucursal.'}
                        </p>
                    ) : (
                        <div className="overflow-x-auto overscroll-x-contain">
                            <table className="w-full border-collapse min-w-[640px] lg:min-w-[720px]">
                                <thead className={RESGUARDOS_TABLA_HEAD}>
                                    <tr>
                                        {['Folio', 'Cliente', 'Entrega completada', 'Quien retiró', 'Firma', 'Bultos'].map((col) => (
                                            <th
                                                key={col}
                                                scope="col"
                                                className="px-3 md:px-4 py-3 text-left text-xs font-semibold theme-text-muted whitespace-nowrap"
                                            >
                                                {col}
                                            </th>
                                        ))}
                                        <th scope="col" className="px-2 md:px-4 py-3 text-right text-xs font-semibold theme-text-muted">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filas.map((fila) => (
                                        <tr key={fila.id} className={RESGUARDOS_TABLA_FILA}>
                                            <td className="px-4 py-3 text-sm font-semibold theme-text-main">
                                                {fila.snapshot_folio || `#${fila.id}`}
                                            </td>
                                            <td className="px-4 py-3 text-sm theme-text-muted">
                                                <span className="block font-semibold theme-text-main">{fila.snapshot_cliente_nombre}</span>
                                                <span className="text-[10px]">{fila.referencia_cliente}</span>
                                            </td>
                                            <td className="px-4 py-3 text-[10px] theme-text-muted whitespace-nowrap">
                                                {formatearFechaOperativa(fila.entrega_completada_at)}
                                            </td>
                                            <td className="px-4 py-3 text-sm theme-text-muted">
                                                {fila.ultima_entrega?.nombre_quien_retira || '—'}
                                                {fila.ultima_entrega?.relacion_etiqueta && (
                                                    <span className="block text-[10px]">{fila.ultima_entrega.relacion_etiqueta}</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {fila.ultima_entrega?.firma_entrega ? (
                                                    <div className="w-24">
                                                        <MiniaturaEvidenciaResguardo
                                                            evidencia={fila.ultima_entrega.firma_entrega}
                                                            etiqueta=""
                                                            tamano="sm"
                                                        />
                                                    </div>
                                                ) : (
                                                    <span className="text-sm theme-text-muted">—</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-sm theme-text-muted">
                                                {fila.cantidad_bultos_esperada}
                                            </td>
                                            <td className="px-2 md:px-4 py-2.5 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => setDetalleResguardoId(fila.id)}
                                                    className={`${RESGUARDOS_BTN_ICON_TABLA} md:min-w-0 md:px-3 md:gap-2 inline-flex`}
                                                    aria-label={`Ver detalle de ${fila.snapshot_folio || fila.id}`}
                                                >
                                                    <Eye className="w-4 h-4 shrink-0" aria-hidden />
                                                    <span className="hidden md:inline text-xs font-semibold">Detalle</span>
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {filas.length > 0 && (
                    <GeliaPaginacion
                        paginator={resguardosVista}
                        onIrAPagina={onIrAPagina}
                    />
                )}

                <ModalDetalleResguardo
                    abierto={detalleResguardoId != null}
                    resguardoId={detalleResguardoId}
                    onClose={cerrarDetalle}
                    modoAuditoria
                />
            </GeliaPageShell>
        </AppLayout>
    );
}
