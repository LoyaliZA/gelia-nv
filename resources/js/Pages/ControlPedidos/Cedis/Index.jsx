import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Warehouse, Clock, CheckCircle2, Package, Scale, Loader2 } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import FiltrosCedis from './Partials/FiltrosCedis';
import TarjetasCedis from './Partials/TarjetasCedis';
import ModalDetalleCedis from './Partials/ModalDetalleCedis';
import ModalResponderPesaje from './Partials/ModalResponderPesaje';
import ModalReportarErrorDatos from '../Partials/ModalReportarErrorDatos';
import ModalMarcarApartadoResguardo from './Partials/ModalMarcarApartadoResguardo';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import ModalBitacoraPedido from '../Partials/ModalBitacoraPedido';
import useListadoDiscreto from '../Partials/useListadoDiscreto';

const KPI_CONFIG = [
    { key: 'pendientes_pesaje', label: 'Pendientes pesaje', tab: 'PENDIENTES_PESAJE', icon: Scale, color: '#F97316' },
    { key: 'empacados', label: 'Pendiente de empaque', tab: 'EMPACADOS', icon: Clock, color: '#EAB308' },
    { key: 'pendientes_guia', label: 'Pendientes de guía', tab: 'PENDIENTES_GUIA', icon: Package, color: '#A855F7' },
    { key: 'pendientes_envio', label: 'Pendiente de recolección', tab: 'PENDIENTES_ENVIO', icon: Package, color: '#0EA5E9' },
    { key: 'enviados', label: 'Enviados', tab: 'ENVIADOS', icon: CheckCircle2, color: '#22C55E' },
    { key: 'incorrectas', label: 'Errores CEDIS', tab: 'INCORRECTAS', icon: CheckCircle2, color: '#F97316' },
];

export default function Index({ auth, pedidos, metricas = {}, filtros = {}, tipos_caja = [], almacenes_busqueda = [] }) {
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
    const debounceBusqueda = useRef(null);
    const modalAbiertoRef = useRef(false);

    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        modalAbiertoRef.current = modalDetalle.abierto || modalPesaje.abierto || modalErrorDatos.abierto || modalApartado.abierto || modalBitacora.abierto;
    }, [modalDetalle.abierto, modalPesaje.abierto, modalErrorDatos.abierto, modalApartado.abierto, modalBitacora.abierto]);

    useEffect(() => {
        const interval = setInterval(() => {
            if (modalAbiertoRef.current || cargando) return;
            cargar(
                { tab: tabActiva, q: busqueda || undefined, page: pedidosVista?.current_page || 1 },
                { silencioso: true }
            );
        }, 15000);
        return () => clearInterval(interval);
    }, [cargando, tabActiva, busqueda, pedidosVista?.current_page, cargar]);

    const onTabChange = (tab) => {
        setTabActiva(tab);
        cargar({ tab, q: busqueda || undefined, page: 1 });
    };

    const onBuscar = (valor) => {
        setBusqueda(valor);
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            cargar({ tab: tabActiva, q: valor || undefined, page: 1 });
        }, 400);
    };

    const onActualizar = () => {
        cargar({ tab: tabActiva, q: busqueda || undefined, page: pedidosVista?.current_page || 1 });
    };

    const onIrAPagina = (page) => {
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
            <GeliaPageShell className="space-y-3 md:space-y-6">
                <header className={`${geliaCardClass()} p-3 md:p-8`}>
                    <div className="flex items-center gap-2 mb-0.5 md:mb-2">
                        <Warehouse className="w-4 h-4 md:w-5 md:h-5" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Gestión de pedidos_</span>
                    </div>
                    <h1 className="text-xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Control <span style={{ color: 'var(--color-primario)' }}>pedidos</span> CEDIS
                    </h1>
                    <p className="hidden md:block text-sm theme-text-muted font-bold mt-2 m-0">Bandeja de pesaje y empaque para almacén</p>
                </header>

                <div className="md:hidden -mx-1 overflow-x-auto snap-x snap-mandatory flex gap-2 pb-1" role="tablist" aria-label="Estado de empaque">
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
                <div className="hidden md:grid grid-cols-3 lg:grid-cols-6 gap-4">
                    {KPI_CONFIG.map(({ key, label, tab, icon: Icon, color }) => {
                        const activo = tabActiva === tab;
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => onTabChange(tab)}
                                aria-pressed={activo}
                                className={`${geliaCardClass()} p-5 text-left outline-none transition-shadow ${
                                    activo ? 'ring-2 ring-[var(--color-primario)]' : ''
                                }`}
                            >
                                <div className="flex items-center gap-2 mb-2 min-w-0">
                                    <Icon className="w-4 h-4 shrink-0" style={{ color }} />
                                    <span className="text-[9px] font-black uppercase tracking-wide theme-text-muted truncate leading-tight">
                                        {label}
                                    </span>
                                </div>
                                <p className="text-3xl font-black m-0 tabular-nums" style={{ color }}>
                                    {metricasVista[key] ?? 0}
                                </p>
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
                        pedidos={pedidosVista}
                        onIrAPagina={onIrAPagina}
                        buscando={cargando}
                    />
                </div>

                <div className="relative min-h-[12rem]">
                    {cargando && (
                        <div className="absolute inset-0 z-10 flex items-start justify-center pt-16 pointer-events-none">
                            <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} aria-label="Cargando pedidos" />
                        </div>
                    )}
                    <TarjetasCedis
                        pedidos={pedidosVista}
                        onVerDetalle={abrirDetalle}
                        onResponderPesaje={abrirPesaje}
                        onReportarErrorDatos={abrirErrorDatos}
                        onMarcarApartado={abrirApartado}
                        onBitacora={abrirBitacora}
                    />
                </div>
            </GeliaPageShell>

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
