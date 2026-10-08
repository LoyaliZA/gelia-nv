import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Store, Loader2, Radio } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaTituloCard from '../../../Components/GeliaTituloCard';
import { geliaCardClass } from '../../../utils/geliaTheme';
import FiltrosTienda, { TABS_TIENDA } from './Partials/FiltrosTienda';
import MetricasBandejaTienda from './Partials/MetricasBandejaTienda';
import BandejasTienda from './Partials/BandejasTienda';
import ListadoTienda from './Partials/ListadoTienda';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import useListadoDiscreto from '../Partials/useListadoDiscreto';
import { ORIGEN_TABLERO } from './Partials/tiendaUi';

export default function Index({
    auth,
    tareas,
    metricas = {},
    cola_estados_cuenta = { total: 0, casos: [] },
    origenes_solicitud = [],
    almacenes = [],
    filtros = {},
}) {
    const { flash } = usePage().props;
    const {
        tareas: tareasVista,
        metricas: metricasVista,
        cargando,
        cargar,
    } = useListadoDiscreto({
        listadoRoute: 'control_pedidos.tienda.listado',
        indexRoute: 'control_pedidos.tienda.index',
        tareas,
        metricas,
    });

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'PENDIENTES');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [origenSolicitud, setOrigenSolicitud] = useState(filtros.origen_solicitud || '');
    const [prioridadMd, setPrioridadMd] = useState(
        filtros.prioridad_md === true || filtros.prioridad_md === '1' ? '1'
            : filtros.prioridad_md === false || filtros.prioridad_md === '0' ? '0' : ''
    );
    const [modalidad, setModalidad] = useState(filtros.modalidad || '');
    const [almacenId, setAlmacenId] = useState(
        filtros.almacen_id ? String(filtros.almacen_id) : ''
    );
    const [ultimaSync, setUltimaSync] = useState(null);

    const paramsListado = (extra = {}) => ({
        tab: tabActiva,
        q: busqueda || undefined,
        origen_solicitud: origenSolicitud || undefined,
        prioridad_md: prioridadMd === '' ? undefined : prioridadMd,
        modalidad: modalidad || undefined,
        almacen_id: almacenId || undefined,
        ...extra,
    });
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const debounceBusqueda = useRef(null);

    const tabLabel = (TABS_TIENDA.find((t) => t.id === tabActiva) || TABS_TIENDA[0]).label;

    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        const interval = setInterval(() => {
            if (cargando) return;
            cargar(paramsListado({ page: tareasVista?.current_page || 1 }), { silencioso: true });
        }, 15000);
        return () => clearInterval(interval);
    }, [cargando, tabActiva, busqueda, origenSolicitud, prioridadMd, modalidad, almacenId, tareasVista?.current_page, cargar]);

    useEffect(() => {
        if (!cargando && tareasVista) {
            setUltimaSync(new Date());
        }
    }, [cargando, tareasVista?.data]);

    useEffect(() => {
        if (filtros.tarea && tareasVista?.data?.length) {
            const t = tareasVista.data.find((x) => String(x.id) === String(filtros.tarea));
            if (t) router.visit(route('control_pedidos.tienda.show', t.id));
        }
    }, [filtros.tarea, tareasVista]);

    const onTabChange = (tab) => {
        setTabActiva(tab);
        cargar(paramsListado({ tab, page: 1 }));
    };

    const onBuscar = (valor) => {
        setBusqueda(valor);
        clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            cargar(paramsListado({ q: valor || undefined, page: 1 }));
        }, 350);
    };

    const onOrigenChange = (valor) => {
        setOrigenSolicitud(valor);
        cargar(paramsListado({ origen_solicitud: valor || undefined, page: 1 }));
    };

    const onPrioridadMdChange = (valor) => {
        setPrioridadMd(valor);
        cargar(paramsListado({ prioridad_md: valor === '' ? undefined : valor, page: 1 }));
    };

    const onModalidadChange = (valor) => {
        setModalidad(valor);
        cargar(paramsListado({ modalidad: valor || undefined, page: 1 }));
    };

    const onAlmacenChange = (valor) => {
        setAlmacenId(valor);
        cargar(paramsListado({ almacen_id: valor || undefined, page: 1 }));
    };

    const onLimpiarFiltros = () => {
        setTabActiva('PENDIENTES');
        setBusqueda('');
        setOrigenSolicitud('');
        setPrioridadMd('');
        setModalidad('');
        setAlmacenId('');
        cargar({ tab: 'PENDIENTES', page: 1 });
    };

    const onActualizar = () => {
        cargar(paramsListado({ page: tareasVista?.current_page || 1 }));
    };

    const syncAside = (
        <div className="flex flex-col items-end gap-1 text-right" aria-live="polite">
            <span className="inline-flex items-center gap-2 text-xs font-semibold theme-text-muted">
                {cargando ? (
                    <Loader2 className="w-3.5 h-3.5 animate-spin" aria-hidden />
                ) : (
                    <Radio className="w-3.5 h-3.5 text-primario" aria-hidden />
                )}
                {cargando ? 'Sincronizando…' : 'En vivo · cada 15 s'}
            </span>
            {ultimaSync && !cargando && (
                <span className="text-[11px] theme-text-muted font-medium">
                    Actualizado {ultimaSync.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}
                </span>
            )}
        </div>
    );

    return (
        <AppLayout auth={auth}>
            <Head title="Preparación Tienda | GELIANV" />
            <GeliaPageShell className="gelia-tienda-op space-y-4 md:space-y-6">
                <GeliaTituloCard
                    eyebrow="Gestión de pedidos"
                    title="Preparación"
                    titleHighlight="Tienda"
                    description="Bandeja de recolección local y traslado a CEDIS"
                    icon={Store}
                    aside={syncAside}
                >
                    {metricasVista?.observabilidad_origen && (
                        <div className="flex flex-wrap gap-2 pt-1">
                            {Object.entries(metricasVista.observabilidad_origen).map(([k, n]) => (
                                <span key={k} className="text-xs font-semibold px-2.5 py-1 rounded-lg theme-element border theme-border tabular-nums">
                                    {ORIGEN_TABLERO[k] || k}: {n}
                                </span>
                            ))}
                            <span className="text-xs font-semibold px-2.5 py-1 rounded-lg theme-element border theme-border tabular-nums">
                                Mismo día activas: {metricasVista.prioridad_md_activas ?? 0}
                            </span>
                        </div>
                    )}
                </GeliaTituloCard>

                {cola_estados_cuenta?.total > 0 && (
                    <div
                        className={`${geliaCardClass()} p-4 theme-element border theme-border`}
                        role="status"
                    >
                        <p className="text-sm font-bold gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--aviso inline-flex m-0 mb-2">
                            Estados de cuenta en Caja
                        </p>
                        <p className="text-sm theme-text-muted font-medium m-0">
                            {cola_estados_cuenta.total} caso(s) con comprobante pendiente de conciliar.
                            La separación en Tienda puede continuar.
                        </p>
                    </div>
                )}

                <div className={`${geliaCardClass()} gelia-tienda-op-workspace p-4 md:p-5 space-y-4`}>
                    <MetricasBandejaTienda
                        metricas={metricasVista}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                    />
                    <div className="gelia-tienda-op-divider" aria-hidden />
                    <BandejasTienda
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                        metricas={metricasVista}
                    />
                    <div className="gelia-tienda-op-divider" aria-hidden />
                    <FiltrosTienda
                        tabActiva={tabActiva}
                        busqueda={busqueda}
                        origenSolicitud={origenSolicitud}
                        prioridadMd={prioridadMd}
                        modalidad={modalidad}
                        almacenId={almacenId}
                        almacenes={almacenes}
                        origenesSolicitud={origenes_solicitud}
                        onBuscar={onBuscar}
                        onOrigenChange={onOrigenChange}
                        onPrioridadMdChange={onPrioridadMdChange}
                        onModalidadChange={onModalidadChange}
                        onAlmacenChange={onAlmacenChange}
                        onLimpiarFiltros={onLimpiarFiltros}
                        onActualizar={onActualizar}
                        buscando={cargando}
                    />
                </div>

                <ListadoTienda
                    tareas={tareasVista}
                    auth={auth}
                    tabLabel={tabLabel}
                    buscando={cargando}
                    hayFiltrosActivos={Boolean(busqueda) || Boolean(origenSolicitud) || prioridadMd !== ''
                        || Boolean(modalidad) || Boolean(almacenId) || tabActiva !== 'PENDIENTES'}
                    onLimpiarFiltros={onLimpiarFiltros}
                    onIrAPagina={(page) => cargar(paramsListado({ page }))}
                />
            </GeliaPageShell>
            <ModalAlertaPedido {...alerta} onCerrar={() => setAlerta((a) => ({ ...a, abierto: false }))} />
        </AppLayout>
    );
}
