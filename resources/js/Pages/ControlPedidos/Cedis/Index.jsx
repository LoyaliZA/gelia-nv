import React, { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Warehouse, Clock, CheckCircle2, Package } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import FiltrosCedis from './Partials/FiltrosCedis';
import TarjetasCedis from './Partials/TarjetasCedis';
import ModalDetalleCedis from './Partials/ModalDetalleCedis';
import ModalReportarIncidencia from './Partials/ModalReportarIncidencia';

const PROPS_LISTADO = ['pedidos', 'metricas', 'filtros'];

const KPI_CONFIG = [
    { key: 'pendientes', label: 'Pendientes de empaque', icon: Clock, color: '#EAB308' },
    { key: 'empacados', label: 'Empacados', icon: CheckCircle2, color: '#22C55E' },
    { key: 'total', label: 'Total en bandeja', icon: Package, color: 'var(--color-primario)' },
];

export default function Index({ auth, pedidos, metricas = {}, filtros = {} }) {
    const [tabActiva, setTabActiva] = useState(filtros.tab || 'PENDIENTES');
    const [modalDetalle, setModalDetalle] = useState({ abierto: false, pedido: null });
    const [modalIncidencia, setModalIncidencia] = useState({ abierto: false, pedido: null });
    const debounceBusqueda = useRef(null);
    const modalAbiertoRef = useRef(false);

    useEffect(() => {
        modalAbiertoRef.current = modalDetalle.abierto || modalIncidencia.abierto;
    }, [modalDetalle.abierto, modalIncidencia.abierto]);

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

    const abrirDetalle = (pedido) => setModalDetalle({ abierto: true, pedido });
    const abrirIncidencia = (pedido) => setModalIncidencia({ abierto: true, pedido });

    return (
        <AppLayout auth={auth}>
            <Head title="Control pedidos CEDIS | GELIANV" />
            <GeliaPageShell className="space-y-6">
                <header className={`${geliaCardClass()} p-6 md:p-8`}>
                    <div className="flex items-center gap-2 mb-2">
                        <Warehouse className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Control de pedidos_</span>
                    </div>
                    <h1 className="text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Control <span style={{ color: 'var(--color-primario)' }}>pedidos</span> CEDIS
                    </h1>
                    <p className="text-sm theme-text-muted font-bold mt-2 m-0">Bandeja de empaque para almacén</p>
                </header>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {KPI_CONFIG.map(({ key, label, icon: Icon, color }) => (
                        <div key={key} className={`${geliaCardClass()} p-5`}>
                            <div className="flex items-center gap-2 mb-2">
                                <Icon className="w-4 h-4" style={{ color }} />
                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">{label}</span>
                            </div>
                            <p className="text-3xl font-black m-0" style={{ color }}>{metricas[key] ?? 0}</p>
                        </div>
                    ))}
                </div>

                <div className={`${geliaCardClass()} p-5`}>
                    <FiltrosCedis
                        filtros={filtros}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                        onBuscar={onBuscar}
                        onActualizar={onActualizar}
                        metricas={metricas}
                    />
                </div>

                <TarjetasCedis
                    pedidos={pedidos}
                    onVerDetalle={abrirDetalle}
                    onReportarIncidencia={abrirIncidencia}
                />
            </GeliaPageShell>

            <ModalDetalleCedis
                abierto={modalDetalle.abierto}
                pedido={modalDetalle.pedido}
                onClose={() => setModalDetalle({ abierto: false, pedido: null })}
                onReportarIncidencia={abrirIncidencia}
            />

            <ModalReportarIncidencia
                abierto={modalIncidencia.abierto}
                pedido={modalIncidencia.pedido}
                onClose={() => setModalIncidencia({ abierto: false, pedido: null })}
            />
        </AppLayout>
    );
}
