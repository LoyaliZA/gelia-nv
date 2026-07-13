import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { ClipboardCheck, Clock, CheckCircle2, XCircle, Inbox } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import FiltrosAuditoria from './Partials/FiltrosAuditoria';
import TablaAuditoria from './Partials/TablaAuditoria';
import ModalRevisarPedido from './Partials/ModalRevisarPedido';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';

const PROPS_LISTADO = ['pedidos', 'metricas', 'filtros'];

const KPI_CONFIG = [
    { key: 'pendientes', label: 'Pendientes de revisión', icon: Clock, color: '#EAB308' },
    { key: 'aprobados', label: 'Enviados a registro general', icon: CheckCircle2, color: '#22C55E' },
    { key: 'rechazados', label: 'Rechazados / con reporte', icon: XCircle, color: '#EF4444' },
    { key: 'total', label: 'Total recibidas', icon: Inbox, color: 'var(--color-primario)' },
];

export default function Index({ auth, pedidos, metricas = {}, filtros = {} }) {
    const { flash } = usePage().props;
    const [tabActiva, setTabActiva] = useState(filtros.tab || 'PENDIENTES');
    const [modalRevisar, setModalRevisar] = useState({ abierto: false, pedido: null });
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
        modalAbiertoRef.current = modalRevisar.abierto;
    }, [modalRevisar.abierto]);

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
        router.get(route('control_pedidos.auditar.index'), { tab, q: filtros.q || '' }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: PROPS_LISTADO,
        });
    };

    const onBuscar = (valor) => {
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            router.get(route('control_pedidos.auditar.index'), { tab: tabActiva, q: valor }, {
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

    const abrirRevisar = (pedido) => setModalRevisar({ abierto: true, pedido });

    return (
        <AppLayout auth={auth}>
            <Head title="Auditar pedidos | GELIANV" />
            <GeliaPageShell className="space-y-6">
                <header className={`${geliaCardClass()} p-6 md:p-8`}>
                    <div className="flex items-center gap-2 mb-2">
                        <ClipboardCheck className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Control de pedidos_</span>
                    </div>
                    <h1 className="text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Auditar <span style={{ color: 'var(--color-primario)' }}>pedidos</span>
                    </h1>
                    <p className="text-sm theme-text-muted font-bold mt-2 m-0">Bandeja de entrada para validación antes de Registro General</p>
                </header>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
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
                    <FiltrosAuditoria
                        filtros={filtros}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                        onBuscar={onBuscar}
                        onActualizar={onActualizar}
                        metricas={metricas}
                    />
                </div>

                <TablaAuditoria pedidos={pedidos} onRevisar={abrirRevisar} />
            </GeliaPageShell>

            <ModalRevisarPedido
                abierto={modalRevisar.abierto}
                pedido={modalRevisar.pedido}
                onClose={() => setModalRevisar({ abierto: false, pedido: null })}
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
