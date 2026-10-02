import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Store, Loader2, Clock, CheckCircle2, AlertTriangle, Package, Truck, Undo2,
} from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import FiltrosTienda from './Partials/FiltrosTienda';
import TarjetasTienda from './Partials/TarjetasTienda';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import useListadoDiscreto from '../Partials/useListadoDiscreto';

const ORIGEN_TABLERO = {
    CALL_CENTER: 'Call Center',
    BELLAROMA: 'Bellaroma',
    SIN_ORIGEN: 'Sin origen',
};

const KPI_CONFIG = [
    { key: 'pendientes', label: 'Pendientes', tab: 'PENDIENTES', icon: Clock, color: '#F97316' },
    { key: 'en_atencion', label: 'En atención', tab: 'EN_ATENCION', icon: Package, color: '#0EA5E9' },
    { key: 'con_incidencia', label: 'Con incidencia', tab: 'CON_INCIDENCIA', icon: AlertTriangle, color: '#EA580C' },
    { key: 'listas_traslado', label: 'Listas traslado', tab: 'LISTAS_TRASLADO', icon: Truck, color: '#A855F7' },
    { key: 'listas_caratula', label: 'Listas carátula', tab: 'LISTAS_CARATULA', icon: Package, color: '#14B8A6' },
    { key: 'en_traslado', label: 'En traslado', tab: 'EN_TRASLADO', icon: Truck, color: '#6366F1' },
    { key: 'rechazadas_cedis', label: 'Rechazadas CEDIS', tab: 'RECHAZADAS_CEDIS', icon: Undo2, color: '#EF4444' },
    { key: 'respondidas_hoy', label: 'Respondidas hoy', tab: 'RESPONDIDAS_HOY', icon: CheckCircle2, color: '#22C55E' },
    { key: 'pendientes_liberacion', label: 'Liberación', tab: 'PENDIENTES_LIBERACION', icon: Store, color: '#EAB308' },
    { key: 'devolucion_pendiente', label: 'Devolución', tab: 'DEVOLUCION_PENDIENTE', icon: Undo2, color: '#F59E0B' },
    { key: 'historial_devueltas', label: 'Devueltas', tab: 'HISTORIAL_DEVUELTAS', icon: CheckCircle2, color: '#71717A' },
];

export default function Index({
    auth,
    tareas,
    metricas = {},
    cola_estados_cuenta = { total: 0, casos: [] },
    origenes_solicitud = [],
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

    const paramsListado = (extra = {}) => ({
        tab: tabActiva,
        q: busqueda || undefined,
        origen_solicitud: origenSolicitud || undefined,
        prioridad_md: prioridadMd === '' ? undefined : prioridadMd,
        ...extra,
    });
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const debounceBusqueda = useRef(null);

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
    }, [cargando, tabActiva, busqueda, origenSolicitud, prioridadMd, tareasVista?.current_page, cargar]);

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

    const onActualizar = () => {
        cargar(paramsListado({ page: tareasVista?.current_page || 1 }));
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Preparación Tienda | GELIANV" />
            <GeliaPageShell className="space-y-3 md:space-y-6">
                <header className={`${geliaCardClass()} p-3 md:p-8`}>
                    <div className="flex items-center gap-2 mb-0.5 md:mb-2">
                        <Store className="w-4 h-4 md:w-5 md:h-5" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Gestión de pedidos_</span>
                        {cargando && <Loader2 className="w-4 h-4 animate-spin theme-text-muted ml-auto" aria-label="Actualizando" />}
                    </div>
                    <h1 className="text-xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Preparación <span style={{ color: 'var(--color-primario)' }}>Tienda</span>
                    </h1>
                    <p className="hidden md:block text-sm theme-text-muted font-bold mt-2 m-0">
                        Bandeja de recolección local y traslado a CEDIS
                    </p>
                </header>

                {cola_estados_cuenta?.total > 0 && (
                    <div className={`${geliaCardClass()} p-3 md:p-4 border border-amber-500/30 bg-amber-500/5`} role="status">
                        <p className="text-[10px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-200 m-0">
                            Estados de cuenta en Caja (informativo)
                        </p>
                        <p className="text-sm theme-text-muted font-bold mt-1 m-0">
                            {cola_estados_cuenta.total} caso(s) con comprobante pendiente de conciliar.
                            La separación en Tienda puede continuar.
                        </p>
                    </div>
                )}

                {metricasVista?.observabilidad_origen && (
                    <div className={`${geliaCardClass()} p-3 md:p-4 hidden md:block`}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-2">
                            Activas por origen
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(metricasVista.observabilidad_origen).map(([k, n]) => (
                                <span key={k} className="text-xs font-bold px-2 py-1 rounded-lg theme-element border theme-border">
                                    {ORIGEN_TABLERO[k] || k}: {n}
                                </span>
                            ))}
                            <span className="text-xs font-bold px-2 py-1 rounded-lg theme-element border theme-border">
                                MD: {metricasVista.prioridad_md_activas ?? 0}
                            </span>
                        </div>
                    </div>
                )}

                <div className="md:hidden -mx-1 overflow-x-auto snap-x snap-mandatory flex gap-2 pb-1" role="tablist" aria-label="Estado de preparación">
                    {KPI_CONFIG.map(({ key, label, tab, icon: Icon, color }) => {
                        const activo = tabActiva === tab;
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => onTabChange(tab)}
                                aria-pressed={activo}
                                className={`${geliaCardClass()} snap-start shrink-0 min-w-[9.5rem] p-3 min-h-[72px] text-left outline-none ${
                                    activo ? 'ring-2 ring-[var(--color-primario)]' : ''
                                }`}
                            >
                                <div className="flex items-center gap-1.5 mb-1 min-w-0">
                                    <Icon className="w-3.5 h-3.5 shrink-0" style={{ color }} />
                                    <span className="text-[10px] font-black uppercase tracking-wide theme-text-muted truncate leading-tight">
                                        {label}
                                    </span>
                                </div>
                                <p className="text-2xl font-black m-0 tabular-nums" style={{ color }}>
                                    {metricasVista[key] ?? 0}
                                </p>
                            </button>
                        );
                    })}
                </div>
                <div className="hidden md:grid grid-cols-3 xl:grid-cols-5 gap-3">
                    {KPI_CONFIG.map(({ key, label, tab, icon: Icon, color }) => {
                        const activo = tabActiva === tab;
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => onTabChange(tab)}
                                aria-pressed={activo}
                                className={`${geliaCardClass()} p-4 text-left outline-none transition-shadow ${
                                    activo ? 'ring-2 ring-[var(--color-primario)]' : ''
                                }`}
                            >
                                <div className="flex items-center gap-2 mb-2 min-w-0">
                                    <Icon className="w-4 h-4 shrink-0" style={{ color }} />
                                    <span className="text-[9px] font-black uppercase tracking-wide theme-text-muted truncate leading-tight">
                                        {label}
                                    </span>
                                </div>
                                <p className="text-2xl xl:text-3xl font-black m-0 tabular-nums" style={{ color }}>
                                    {metricasVista[key] ?? 0}
                                </p>
                            </button>
                        );
                    })}
                </div>

                <div className={`${geliaCardClass()} p-4 md:p-5`}>
                    <FiltrosTienda
                        tabActiva={tabActiva}
                        busqueda={busqueda}
                        origenSolicitud={origenSolicitud}
                        prioridadMd={prioridadMd}
                        origenesSolicitud={origenes_solicitud}
                        onTabChange={onTabChange}
                        onBuscar={onBuscar}
                        onOrigenChange={onOrigenChange}
                        onPrioridadMdChange={onPrioridadMdChange}
                        onActualizar={onActualizar}
                        metricas={metricasVista}
                        tareas={tareasVista}
                        buscando={cargando}
                        onIrAPagina={(page) => cargar(paramsListado({ page }))}
                    />
                </div>

                <TarjetasTienda tareas={tareasVista} auth={auth} />
            </GeliaPageShell>
            <ModalAlertaPedido {...alerta} onCerrar={() => setAlerta((a) => ({ ...a, abierto: false }))} />
        </AppLayout>
    );
}
