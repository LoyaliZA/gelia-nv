import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Warehouse, Clock, CheckCircle2, Package } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import FiltrosCedis from './Partials/FiltrosCedis';
import TarjetasCedis from './Partials/TarjetasCedis';
import ModalDetalleCedis from './Partials/ModalDetalleCedis';
import ModalReportarIncidencia from './Partials/ModalReportarIncidencia';
import ModalReportarErrorDatos from '../Partials/ModalReportarErrorDatos';
import ModalMarcarApartadoResguardo from './Partials/ModalMarcarApartadoResguardo';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';

const PROPS_LISTADO = ['pedidos', 'metricas', 'filtros'];

const KPI_CONFIG = [
    { key: 'empacados', label: 'Por empacar', icon: Clock, color: '#EAB308' },
    { key: 'pendientes_guia', label: 'Pendientes de guía', icon: Package, color: '#A855F7' },
    { key: 'pendientes_envio', label: 'Pendientes de envío', icon: Package, color: '#0EA5E9' },
    { key: 'enviados', label: 'Enviados', icon: CheckCircle2, color: '#22C55E' },
    { key: 'incorrectas', label: 'Incorrectas', icon: CheckCircle2, color: '#F97316' },
    { key: 'total', label: 'Total en bandeja', icon: Package, color: 'var(--color-primario)' },
];

export default function Index({ auth, pedidos, metricas = {}, filtros = {} }) {
    const { flash } = usePage().props;
    const [tabActiva, setTabActiva] = useState(filtros.tab || 'TODOS');
    const [modalDetalle, setModalDetalle] = useState({ abierto: false, pedido: null });
    const [modalIncidencia, setModalIncidencia] = useState({ abierto: false, pedido: null });
    const [modalErrorDatos, setModalErrorDatos] = useState({ abierto: false, pedido: null });
    const [modalApartado, setModalApartado] = useState({ abierto: false, pedido: null });
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
        modalAbiertoRef.current = modalDetalle.abierto || modalIncidencia.abierto || modalErrorDatos.abierto || modalApartado.abierto;
    }, [modalDetalle.abierto, modalIncidencia.abierto, modalErrorDatos.abierto, modalApartado.abierto]);

    useEffect(() => {
        const interval = setInterval(() => {
            if (modalAbiertoRef.current) return;
            router.reload({
                only: PROPS_LISTADO,
                preserveState: true,
                preserveScroll: true,
                showProgress: false,
            });
        }, 15000);
        return () => clearInterval(interval);
    }, []);

    const onTabChange = (tab) => {
        setTabActiva(tab);
        router.get(route('control_pedidos.cedis.index'), { tab, q: filtros.q || '' }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: PROPS_LISTADO,
        });
    };

    const onBuscar = (valor) => {
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            router.get(route('control_pedidos.cedis.index'), { tab: tabActiva, q: valor }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: PROPS_LISTADO,
            });
        }, 400);
    };

    const onActualizar = () => {
        router.reload({ only: PROPS_LISTADO, preserveScroll: true });
    };

    const onIrAPagina = (page) => {
        router.get(route('control_pedidos.cedis.index'), {
            tab: tabActiva,
            q: filtros.q || '',
            page,
        }, {
            preserveState: true,
            preserveScroll: true,
            only: PROPS_LISTADO,
        });
    };

    const abrirDetalle = (pedido) => setModalDetalle({ abierto: true, pedido });
    const abrirIncidencia = (pedido) => setModalIncidencia({ abierto: true, pedido });
    const abrirErrorDatos = (pedido) => setModalErrorDatos({ abierto: true, pedido });
    const abrirApartado = (pedido) => setModalApartado({ abierto: true, pedido });

    return (
        <AppLayout auth={auth}>
            <Head title="Control pedidos CEDIS | GELIANV" />
            <GeliaPageShell className="space-y-3 md:space-y-6">
                <header className={`${geliaCardClass()} p-4 md:p-8`}>
                    <div className="flex items-center gap-2 mb-1 md:mb-2">
                        <Warehouse className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Control de pedidos_</span>
                    </div>
                    <h1 className="text-2xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Control <span style={{ color: 'var(--color-primario)' }}>pedidos</span> CEDIS
                    </h1>
                    <p className="text-xs md:text-sm theme-text-muted font-bold mt-1.5 md:mt-2 m-0">Bandeja de empaque para almacén</p>
                </header>

                {/* KPIs: compactos en mobile (3 cols), amplios en desktop */}
                <div className="grid grid-cols-3 lg:grid-cols-6 gap-2 md:gap-4">
                    {KPI_CONFIG.map(({ key, label, icon: Icon, color }) => (
                        <div key={key} className={`${geliaCardClass()} p-2.5 md:p-5 text-center md:text-left`}>
                            <div className="flex items-center justify-center md:justify-start gap-1 md:gap-2 mb-0.5 md:mb-2 min-w-0">
                                <Icon className="w-3 h-3 md:w-4 md:h-4 shrink-0" style={{ color }} />
                                <span className="text-[8px] md:text-[9px] font-black uppercase tracking-wide theme-text-muted truncate leading-tight">
                                    {label}
                                </span>
                            </div>
                            <p className="text-xl md:text-3xl font-black m-0 tabular-nums" style={{ color }}>
                                {metricas[key] ?? 0}
                            </p>
                        </div>
                    ))}
                </div>

                <div className={`${geliaCardClass()} p-4 md:p-5`}>
                    <FiltrosCedis
                        filtros={filtros}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                        onBuscar={onBuscar}
                        onActualizar={onActualizar}
                        metricas={metricas}
                        pedidos={pedidos}
                        onIrAPagina={onIrAPagina}
                    />
                </div>

                <TarjetasCedis
                    pedidos={pedidos}
                    onVerDetalle={abrirDetalle}
                    onReportarIncidencia={abrirIncidencia}
                    onReportarErrorDatos={abrirErrorDatos}
                    onMarcarApartado={abrirApartado}
                />
            </GeliaPageShell>

            <ModalDetalleCedis
                abierto={modalDetalle.abierto}
                pedido={modalDetalle.pedido}
                onClose={() => setModalDetalle({ abierto: false, pedido: null })}
                onReportarIncidencia={abrirIncidencia}
                onReportarErrorDatos={abrirErrorDatos}
                onMarcarApartado={abrirApartado}
            />

            <ModalReportarIncidencia
                abierto={modalIncidencia.abierto}
                pedido={modalIncidencia.pedido}
                onClose={() => setModalIncidencia({ abierto: false, pedido: null })}
            />
            <ModalReportarErrorDatos
                abierto={modalErrorDatos.abierto}
                pedido={modalErrorDatos.pedido}
                origen="cedis"
                onClose={() => setModalErrorDatos({ abierto: false, pedido: null })}
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
