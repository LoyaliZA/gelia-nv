import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { ClipboardCheck, Clock, CheckCircle2, XCircle, Inbox, MapPin, Loader2 } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';
import FiltrosAuditoria from './Partials/FiltrosAuditoria';
import TablaAuditoria from './Partials/TablaAuditoria';
import ModalRevisarPedido from './Partials/ModalRevisarPedido';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import ModalAnexarPagoEnvio from '../Partials/ModalAnexarPagoEnvio';
import ModalBitacoraPedido from '../Partials/ModalBitacoraPedido';
import useListadoDiscreto from '../Partials/useListadoDiscreto';

const KPI_CONFIG = [
    { key: 'pendientes', label: 'Pendientes', icon: Clock, color: '#EAB308' },
    { key: 'aprobados', label: 'A Registro', icon: CheckCircle2, color: '#22C55E' },
    { key: 'rechazados', label: 'Rechazados', icon: XCircle, color: '#EF4444' },
    { key: 'total', label: 'Total', icon: Inbox, color: 'var(--color-primario)' },
];

export default function Index({ auth, pedidos, metricas = {}, filtros = {}, catalogos = {} }) {
    const { flash } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p)
        || auth?.user?.roles?.includes('Super Admin')
        || auth?.user?.roles?.includes('Admin')
        || auth?.user?.roles?.includes('Super admin (admin)');

    const {
        pedidos: pedidosVista,
        metricas: metricasVista,
        cargando,
        cargar,
    } = useListadoDiscreto({
        listadoRoute: 'control_pedidos.auditar.listado',
        indexRoute: 'control_pedidos.auditar.index',
        pedidos,
        metricas,
    });

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'PENDIENTES');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [modalRevisar, setModalRevisar] = useState({ abierto: false, pedido: null });
    const [modalAnexo, setModalAnexo] = useState({ abierto: false, pedido: null });
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
        modalAbiertoRef.current = modalRevisar.abierto || modalAnexo.abierto || modalBitacora.abierto;
    }, [modalRevisar.abierto, modalAnexo.abierto, modalBitacora.abierto]);

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

    const onIrAPagina = (page) => {
        cargar({ tab: tabActiva, q: busqueda || undefined, page });
    };

    const onActualizar = () => {
        cargar({ tab: tabActiva, q: busqueda || undefined, page: pedidosVista?.current_page || 1 });
    };

    const abrirRevisar = (pedido) => setModalRevisar({ abierto: true, pedido });
    const abrirAnexar = (pedido) => setModalAnexo({ abierto: true, pedido });
    const abrirBitacora = (pedido) => setModalBitacora({ abierto: true, pedido });

    return (
        <AppLayout auth={auth}>
            <Head title="Auditar pedidos | GELIANV" />
            <GeliaPageShell className="space-y-3 md:space-y-6">
                <header className={`${geliaCardClass()} p-4 md:p-8`}>
                    <div className="flex flex-wrap items-end justify-between gap-3 md:gap-4">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 mb-1 md:mb-2">
                                <ClipboardCheck className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Control de pedidos_</span>
                            </div>
                            <h1 className="text-2xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                                Auditar <span style={{ color: 'var(--color-primario)' }}>pedidos</span>
                            </h1>
                            <p className="text-xs md:text-sm theme-text-muted font-bold mt-1.5 md:mt-2 m-0">
                                Validación antes de Registro General. Verde parpadeante = corregido, dar luz verde.
                            </p>
                        </div>
                        {can('clientes.direcciones.ver') && (
                            <button
                                type="button"
                                className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2 w-full sm:w-auto justify-center`}
                                onClick={() => router.get(route('control_pedidos.direcciones.index'))}
                            >
                                <MapPin className="w-4 h-4" />
                                Gestión de Direcciones
                            </button>
                        )}
                    </div>
                </header>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-2 md:gap-4">
                    {KPI_CONFIG.map(({ key, label, icon: Icon, color }) => (
                        <div key={key} className={`${geliaCardClass()} p-2.5 md:p-5 text-center md:text-left`}>
                            <div className="flex items-center justify-center md:justify-start gap-1 md:gap-2 mb-0.5 md:mb-2 min-w-0">
                                <Icon className="w-3 h-3 md:w-4 md:h-4 shrink-0" style={{ color }} />
                                <span className="text-[8px] md:text-[9px] font-black uppercase tracking-wide theme-text-muted truncate leading-tight">
                                    {label}
                                </span>
                            </div>
                            <p className="text-xl md:text-3xl font-black m-0 tabular-nums" style={{ color }}>
                                {metricasVista[key] ?? 0}
                            </p>
                        </div>
                    ))}
                </div>

                <div className={`${geliaCardClass()} p-4 md:p-5`}>
                    <FiltrosAuditoria
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
                    <TablaAuditoria
                        pedidos={pedidosVista}
                        onRevisar={abrirRevisar}
                        onAnexarEnvio={abrirAnexar}
                        onBitacora={abrirBitacora}
                    />
                </div>
            </GeliaPageShell>

            <ModalRevisarPedido
                abierto={modalRevisar.abierto}
                pedido={modalRevisar.pedido}
                bancos={catalogos.bancos || []}
                onClose={() => setModalRevisar({ abierto: false, pedido: null })}
            />
            <ModalBitacoraPedido
                abierto={modalBitacora.abierto}
                pedido={modalBitacora.pedido}
                onClose={() => setModalBitacora({ abierto: false, pedido: null })}
            />
            <ModalAnexarPagoEnvio
                abierto={modalAnexo.abierto}
                pedido={modalAnexo.pedido}
                bancos={catalogos.bancos || []}
                routeName="control_pedidos.auditar.anexar_pago_envio"
                onClose={() => setModalAnexo({ abierto: false, pedido: null })}
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
