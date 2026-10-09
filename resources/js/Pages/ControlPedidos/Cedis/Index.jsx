import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Warehouse, Clock, CheckCircle2, Package, Scale, Loader2, PackageOpen, AlertTriangle } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import FiltrosCedis from './Partials/FiltrosCedis';
import TarjetasCedis from './Partials/TarjetasCedis';
import ModalDetalleCedis from './Partials/ModalDetalleCedis';
import ModalResponderPesaje from './Partials/ModalResponderPesaje';
import ModalReportarErrorDatos from '../Partials/ModalReportarErrorDatos';
import ModalMarcarApartadoResguardo from './Partials/ModalMarcarApartadoResguardo';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import ModalBitacoraPedido from '../Partials/ModalBitacoraPedido';
import ModalLiberarMercancia from '../Partials/ModalLiberarMercancia';
import useListadoDiscreto from '../Partials/useListadoDiscreto';

const KPI_CONFIG = [
    { key: 'pendientes_pesaje', label: 'Consultas pendientes', tab: 'PENDIENTES_PESAJE', icon: Scale, color: 'var(--color-aviso)' },
    { key: 'empacados', label: 'Pendiente de empaque', tab: 'EMPACADOS', icon: Clock, color: 'var(--color-aviso)' },
    { key: 'pendientes_guia', label: 'Pendientes de guía', tab: 'PENDIENTES_GUIA', icon: Package, color: 'var(--color-primario)' },
    { key: 'pendientes_envio', label: 'Pendiente de recolección', tab: 'PENDIENTES_ENVIO', icon: Package, color: 'var(--color-info)' },
    { key: 'enviados', label: 'Enviados', tab: 'ENVIADOS', icon: CheckCircle2, color: 'var(--color-exito)' },
    { key: 'incorrectas', label: 'Errores CEDIS', tab: 'INCORRECTAS', icon: AlertTriangle, color: 'var(--color-aviso)' },
    { key: 'liberaciones_pendientes', label: 'Liberaciones pendientes', tab: 'LIBERACIONES', icon: PackageOpen, color: 'var(--color-aviso)' },
];

export default function Index({
    auth,
    pedidos,
    liberaciones = null,
    metricas = {},
    filtros = {},
    tipos_caja = [],
    almacenes_busqueda = [],
    can: canProps = {},
}) {
    const { flash } = usePage().props;
    const {
        pedidos: pedidosVista,
        metricas: metricasVista,
        cargando,
        cargar,
    } = useListadoDiscreto({
        listadoRoute: 'control_pedidos.cedis.listado',
        indexRoute: 'control_pedidos.cedis.index',
        pedidos,
        metricas,
    });

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'TODOS');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [modalDetalle, setModalDetalle] = useState({ abierto: false, pedido: null });
    const [modalPesaje, setModalPesaje] = useState({ abierto: false, pedido: null });
    const [modalErrorDatos, setModalErrorDatos] = useState({ abierto: false, pedido: null });
    const [modalApartado, setModalApartado] = useState({ abierto: false, pedido: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, pedido: null });
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const [modalLiberar, setModalLiberar] = useState({ abierto: false, tarea: null });
    const debounceBusqueda = useRef(null);
    const modalAbiertoRef = useRef(false);

    useEffect(() => () => clearTimeout(debounceBusqueda.current), []);

    const onTabChange = (tab) => {
        clearTimeout(debounceBusqueda.current);
        setTabActiva(tab);
        if (tab === 'LIBERACIONES') {
            router.get(route('control_pedidos.cedis.index'), { tab, q: busqueda || undefined }, { preserveState: true, preserveScroll: true });
            return;
        }
        cargar({ tab, q: busqueda || undefined, page: 1 });
    };

    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        modalAbiertoRef.current = modalDetalle.abierto || modalPesaje.abierto || modalErrorDatos.abierto || modalApartado.abierto || modalBitacora.abierto || modalLiberar.abierto;
    }, [modalDetalle.abierto, modalPesaje.abierto, modalErrorDatos.abierto, modalApartado.abierto, modalBitacora.abierto, modalLiberar.abierto]);

    useEffect(() => {
        const interval = setInterval(() => {
            if (modalAbiertoRef.current || cargando || tabActiva === 'LIBERACIONES') return;
            cargar(
                { tab: tabActiva, q: busqueda || undefined, page: pedidosVista?.current_page || 1 },
                { silencioso: true }
            );
        }, 15000);
        return () => clearInterval(interval);
    }, [cargando, tabActiva, busqueda, pedidosVista?.current_page, cargar]);

    const onBuscar = (valor) => {
        setBusqueda(valor);
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            if (tabActiva === 'LIBERACIONES') {
                router.get(route('control_pedidos.cedis.index'), { tab: tabActiva, q: valor || undefined }, { preserveState: true, preserveScroll: true });
            } else {
                cargar({ tab: tabActiva, q: valor || undefined, page: 1 });
            }
        }, 400);
    };

    const onActualizar = () => {
        if (tabActiva === 'LIBERACIONES') {
            onIrAPagina(liberaciones?.current_page || 1);
            return;
        }
        cargar({ tab: tabActiva, q: busqueda || undefined, page: pedidosVista?.current_page || 1 });
    };

    const onIrAPagina = (page) => {
        if (tabActiva === 'LIBERACIONES') {
            router.get(route('control_pedidos.cedis.index'), { tab: tabActiva, q: busqueda || undefined, page }, { preserveState: true, preserveScroll: true });
            return;
        }
        cargar({ tab: tabActiva, q: busqueda || undefined, page });
    };

    const abrirDetalle = (pedido) => setModalDetalle({ abierto: true, pedido });
    const abrirPesaje = (pedido) => setModalPesaje({ abierto: true, pedido });
    const abrirErrorDatos = (pedido) => setModalErrorDatos({ abierto: true, pedido });
    const abrirApartado = (pedido) => setModalApartado({ abierto: true, pedido });
    const abrirBitacora = (pedido) => setModalBitacora({ abierto: true, pedido });

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de pedidos CEDIS | GELIANV" />
            <GeliaPageShell className="gelia-pedidos-bma gelia-pedidos-cedis space-y-4 md:space-y-6">
                <header className={`${geliaCardClass()} gelia-pedidos-encabezado p-4 md:p-6`}>
                    <div className="flex items-center gap-3">
                        <span className="gelia-pedidos-icono" aria-hidden="true"><Warehouse className="w-6 h-6" /></span>
                        <div className="min-w-0">
                            <h1 className="text-xl md:text-2xl font-bold theme-text-main m-0">Control pedidos CEDIS</h1>
                            <p className="text-sm theme-text-muted mt-1 m-0">Consulta, pesaje y empaque de pedidos</p>
                        </div>
                    </div>
                </header>

                <div className="gelia-pedidos-kpis grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-7 gap-2 md:gap-3" role="group" aria-label="Filtrar por estado del pedido">
                    {KPI_CONFIG.map(({ key, label, tab, icon: Icon, color }) => {
                        const activo = tabActiva === tab;
                        return (
                            <button key={key} type="button" onClick={() => onTabChange(tab)} aria-pressed={activo}
                                className={`${geliaCardClass()} gelia-pedidos-kpi min-w-0 p-3 md:p-4 text-left ${activo ? 'border-[var(--color-primario)]' : ''}`}>
                                <div className="flex items-start gap-1.5 mb-2 min-w-0">
                                    <Icon className="w-4 h-4 shrink-0 mt-0.5" style={{ color }} aria-hidden="true" />
                                    <span className="text-xs font-semibold theme-text-muted leading-snug">{label}</span>
                                </div>
                                <p className="text-2xl font-semibold theme-text-main m-0 tabular-nums">{new Intl.NumberFormat('es-MX').format(metricasVista[key] ?? 0)}</p>
                            </button>
                        );
                    })}
                </div>

                <div className={`${geliaCardClass()} p-4 md:p-5`}>
                    <FiltrosCedis
                        filtros={filtros}
                        tabActiva={tabActiva}
                        busqueda={busqueda}
                        onTabChange={onTabChange}
                        onBuscar={onBuscar}
                        onActualizar={onActualizar}
                        metricas={metricasVista}
                        buscando={cargando}
                    />
                </div>

                <div className="relative min-h-[12rem] space-y-3" aria-busy={cargando}>
                    <div className="flex flex-wrap justify-between items-center gap-2 px-1">
                        <h2 className="text-sm font-semibold theme-text-main m-0">{tabActiva === 'LIBERACIONES' ? 'Liberaciones' : 'Pedidos'}</h2>
                        <p className="text-xs theme-text-muted m-0 tabular-nums">{(tabActiva === 'LIBERACIONES' ? liberaciones : pedidosVista)?.total ?? 0} resultados{busqueda ? ` para “${busqueda}”` : ''}</p>
                    </div>
                    <p className="sr-only" role="status">{cargando ? 'Actualizando pedidos…' : 'Listado actualizado'}</p>
                    {cargando && tabActiva !== 'LIBERACIONES' && (
                        <div className="absolute inset-0 z-10 flex items-start justify-center pt-16 pointer-events-none">
                            <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} aria-label="Cargando pedidos" />
                        </div>
                    )}
                    {tabActiva === 'LIBERACIONES' ? (
                        <div className="space-y-3">
                            {(liberaciones?.data || []).length === 0 && (
                                <div className={`${geliaCardClass()} p-8 text-center text-sm font-bold theme-text-muted`}>
                                    Sin liberaciones pendientes
                                </div>
                            )}
                            {(liberaciones?.data || []).map((tarea) => {
                                const folio = tarea.pedido?.folio_remision || tarea.pedido?.folio || `#${tarea.id}`;
                                return (
                                    <div key={tarea.id} className={`${geliaCardClass()} p-4 flex flex-wrap items-center justify-between gap-3`}>
                                        <div>
                                            <p className="text-sm font-black theme-text-main m-0">{folio}</p>
                                            <p className="text-xs font-bold theme-text-muted m-0">
                                                {tarea.almacen?.nombre || '—'} · {tarea.modalidad?.nombre || tarea.estado}
                                            </p>
                                            <p className="text-xs font-bold theme-text-muted m-0 mt-1">
                                                {(tarea.productos || []).slice(0, 3).map((p) => p.descripcion_snapshot || p.sku).join(', ') || 'Sin productos'}
                                            </p>
                                        </div>
                                        {(canProps.liberar || (auth?.user?.permissions || []).includes('control_pedidos.cedis.liberar')) && (
                                            <button
                                                type="button"
                                                className="px-4 py-2 min-h-[44px] rounded-xl text-xs font-black uppercase tracking-wide text-white"
                                                style={{ background: 'var(--color-primario)' }}
                                                onClick={() => setModalLiberar({ abierto: true, tarea })}
                                            >
                                                Liberar mercancía
                                            </button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <TarjetasCedis
                            pedidos={pedidosVista}
                            onVerDetalle={abrirDetalle}
                            onResponderPesaje={abrirPesaje}
                            onReportarErrorDatos={abrirErrorDatos}
                            onMarcarApartado={abrirApartado}
                            onBitacora={abrirBitacora}
                        />
                    )}
                    <GeliaPaginacion paginator={tabActiva === 'LIBERACIONES' ? liberaciones : pedidosVista} onIrAPagina={onIrAPagina} embedded />
                </div>
            </GeliaPageShell>

            <ModalLiberarMercancia
                abierto={modalLiberar.abierto}
                onClose={() => setModalLiberar({ abierto: false, tarea: null })}
                tarea={modalLiberar.tarea}
                routeName="control_pedidos.cedis.liberar"
            />
            <ModalDetalleCedis
                abierto={modalDetalle.abierto}
                pedido={modalDetalle.pedido}
                onClose={() => setModalDetalle({ abierto: false, pedido: null })}
                onReportarErrorDatos={abrirErrorDatos}
                onMarcarApartado={abrirApartado}
            />
            <ModalBitacoraPedido
                abierto={modalBitacora.abierto}
                pedido={modalBitacora.pedido}
                onClose={() => setModalBitacora({ abierto: false, pedido: null })}
            />
            <ModalResponderPesaje
                abierto={modalPesaje.abierto}
                pedido={modalPesaje.pedido}
                tiposCaja={tipos_caja}
                almacenesBusqueda={almacenes_busqueda}
                onClose={() => setModalPesaje({ abierto: false, pedido: null })}
            />
            <ModalReportarErrorDatos
                abierto={modalErrorDatos.abierto}
                pedido={modalErrorDatos.pedido}
                origen="cedis"
                onClose={() => setModalErrorDatos({ abierto: false, pedido: null })}
                onSuccess={() => {
                    setModalErrorDatos({ abierto: false, pedido: null });
                    setModalDetalle({ abierto: false, pedido: null });
                }}
            />
            <ModalMarcarApartadoResguardo
                abierto={modalApartado.abierto}
                pedido={modalApartado.pedido}
                onClose={() => setModalApartado({ abierto: false, pedido: null })}
            />
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta({ ...alerta, abierto: false })}
            />
        </AppLayout>
    );
}
