import React, { useEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { ClipboardCheck, Clock, CheckCircle2, XCircle, RefreshCw, Loader2, Sparkles } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import FiltrosAuditoria from './Partials/FiltrosAuditoria';
import TablaAuditoria from './Partials/TablaAuditoria';
import ModalRevisarPedido from './Partials/ModalRevisarPedido';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import ModalAnexarPagoEnvio from '../Partials/ModalAnexarPagoEnvio';
import ModalBitacoraPedido from '../Partials/ModalBitacoraPedido';
import useListadoDiscreto from '../Partials/useListadoDiscreto';

const KPI_CONFIG = [
    { key: 'pendientes', label: 'Pendientes', tab: 'PENDIENTES', icon: Clock, color: '#EAB308' },
    { key: 'corregidos', label: 'Corregidos', tab: 'CORREGIDOS', icon: Sparkles, color: '#10B981' },
    { key: 'rechazados', label: 'Rechazados', tab: 'RECHAZADOS', icon: XCircle, color: '#EF4444' },
    { key: 'aprobados', label: 'A Registro', tab: 'APROBADOS', icon: CheckCircle2, color: '#22C55E' },
];

function formatearHoraActualizacion(date) {
    if (!date) return '—';
    try {
        return date.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch {
        return '—';
    }
}

export default function Index({ auth, pedidos, metricas = {}, filtros = {}, catalogos = {} }) {
    const { flash } = usePage().props;

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
    const [paqueteriaId, setPaqueteriaId] = useState(filtros.catalogo_paqueteria_id || '');
    const [departamentoId, setDepartamentoId] = useState(filtros.departamento_id || '');
    const [clienteFiltro, setClienteFiltro] = useState(filtros.cliente || '');
    const [ordenar, setOrdenar] = useState(filtros.ordenar || 'fecha_desc');
    const [modalRevisar, setModalRevisar] = useState({ abierto: false, pedido: null });
    const [modalAnexo, setModalAnexo] = useState({ abierto: false, pedido: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, pedido: null });
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const [ultimaActualizacion, setUltimaActualizacion] = useState(() => new Date());
    const debounceBusqueda = useRef(null);
    const debounceCliente = useRef(null);
    const modalAbiertoRef = useRef(false);

    const paramsListado = (extra = {}) => ({
        tab: tabActiva,
        q: busqueda || undefined,
        catalogo_paqueteria_id: paqueteriaId || undefined,
        departamento_id: departamentoId || undefined,
        cliente: clienteFiltro || undefined,
        ordenar: ordenar && ordenar !== 'fecha_desc' ? ordenar : undefined,
        page: pedidosVista?.current_page || 1,
        ...extra,
    });

    const cargarYMarcar = async (params, opts) => {
        const data = await cargar(params, opts);
        if (data) setUltimaActualizacion(new Date());
        return data;
    };

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
            cargarYMarcar(paramsListado(), { silencioso: true });
        }, 15000);
        return () => clearInterval(interval);
    }, [cargando, tabActiva, busqueda, paqueteriaId, departamentoId, clienteFiltro, ordenar, pedidosVista?.current_page, cargar]);

    const onTabChange = (tab) => {
        setTabActiva(tab);
        cargarYMarcar(paramsListado({ tab, page: 1 }));
    };

    const onBuscar = (valor) => {
        setBusqueda(valor);
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            cargarYMarcar(paramsListado({ q: valor || undefined, page: 1 }));
        }, 400);
    };

    const onPaqueteriaChange = (valor) => {
        setPaqueteriaId(valor);
        cargarYMarcar(paramsListado({ catalogo_paqueteria_id: valor || undefined, page: 1 }));
    };

    const onDepartamentoChange = (valor) => {
        setDepartamentoId(valor);
        cargarYMarcar(paramsListado({ departamento_id: valor || undefined, page: 1 }));
    };

    const onClienteFiltroChange = (valor) => {
        setClienteFiltro(valor);
        if (debounceCliente.current) clearTimeout(debounceCliente.current);
        debounceCliente.current = setTimeout(() => {
            cargarYMarcar(paramsListado({ cliente: valor || undefined, page: 1 }));
        }, 400);
    };

    const onOrdenarChange = (valor) => {
        setOrdenar(valor || 'fecha_desc');
        cargarYMarcar(paramsListado({
            ordenar: valor && valor !== 'fecha_desc' ? valor : undefined,
            page: 1,
        }));
    };

    const onLimpiarFiltros = () => {
        setTabActiva('PENDIENTES');
        setBusqueda('');
        setPaqueteriaId('');
        setDepartamentoId('');
        setClienteFiltro('');
        setOrdenar('fecha_desc');
        cargarYMarcar({ tab: 'PENDIENTES', page: 1 });
    };

    const onIrAPagina = (page) => {
        cargarYMarcar(paramsListado({ page }));
    };

    const onActualizar = () => {
        cargarYMarcar(paramsListado());
    };

    const abrirRevisar = (pedido) => setModalRevisar({ abierto: true, pedido });
    const abrirAnexar = (pedido) => setModalAnexo({ abierto: true, pedido });
    const abrirBitacora = (pedido) => setModalBitacora({ abierto: true, pedido });

    const atencion = (metricasVista.pendientes ?? 0) + (metricasVista.corregidos ?? 0);
    const hayFiltrosActivos = Boolean(busqueda) || Boolean(paqueteriaId) || Boolean(departamentoId)
        || Boolean(clienteFiltro) || (ordenar && ordenar !== 'fecha_desc');

    return (
        <AppLayout auth={auth}>
            <Head title="Revisión de pedidos | GELIANV" />
            <GeliaPageShell className="space-y-3 md:space-y-6">
                <header className={`${geliaCardClass()} p-4 md:p-8`}>
                    <div className="flex flex-wrap items-end justify-between gap-3 md:gap-4">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2 mb-1 md:mb-2">
                                <ClipboardCheck className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Auditar · Control de pedidos_</span>
                            </div>
                            <h1 className="text-2xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                                Revisión de <span style={{ color: 'var(--color-primario)' }}>pedidos</span>
                            </h1>
                            <p className="text-xs md:text-sm theme-text-muted font-bold mt-1.5 md:mt-2 m-0">
                                Valida pagos y costos antes de que el pedido continúe.
                            </p>
                            <p className="text-[10px] theme-text-muted font-bold mt-2 m-0 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>{atencion} requieren atención</span>
                                <span>Actualizado {formatearHoraActualizacion(ultimaActualizacion)}</span>
                                <button
                                    type="button"
                                    onClick={onActualizar}
                                    disabled={cargando}
                                    className="inline-flex items-center gap-1 outline-none hover:opacity-80 disabled:opacity-40"
                                >
                                    <RefreshCw className={`w-3 h-3 ${cargando ? 'animate-spin' : ''}`} />
                                    Actualizar
                                </button>
                            </p>
                        </div>
                    </div>
                </header>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-2 md:gap-4">
                    {KPI_CONFIG.map(({ key, label, tab, icon: Icon, color }) => {
                        const activo = tabActiva === tab;
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => onTabChange(tab)}
                                aria-pressed={activo}
                                className={`${geliaCardClass()} p-2.5 md:p-5 text-center md:text-left outline-none transition-shadow ${
                                    activo ? 'ring-2 ring-[var(--color-primario)]' : ''
                                }`}
                            >
                                <div className="flex items-center justify-center md:justify-start gap-1 md:gap-2 mb-0.5 md:mb-2 min-w-0">
                                    <Icon className="w-3 h-3 md:w-4 md:h-4 shrink-0" style={{ color }} />
                                    <span className="text-[8px] md:text-[9px] font-black uppercase tracking-wide theme-text-muted truncate leading-tight">
                                        {label}
                                    </span>
                                </div>
                                <p className="text-xl md:text-3xl font-black m-0 tabular-nums" style={{ color }}>
                                    {metricasVista[key] ?? 0}
                                </p>
                            </button>
                        );
                    })}
                </div>

                <div className={`${geliaCardClass()} p-4 md:p-5`}>
                    <FiltrosAuditoria
                        tabActiva={tabActiva}
                        busqueda={busqueda}
                        paqueteriaId={paqueteriaId}
                        departamentoId={departamentoId}
                        clienteFiltro={clienteFiltro}
                        ordenar={ordenar}
                        paqueterias={catalogos.paqueterias || []}
                        departamentos={catalogos.departamentos || []}
                        onTabChange={onTabChange}
                        onBuscar={onBuscar}
                        onPaqueteriaChange={onPaqueteriaChange}
                        onDepartamentoChange={onDepartamentoChange}
                        onClienteFiltroChange={onClienteFiltroChange}
                        onOrdenarChange={onOrdenarChange}
                        onLimpiarFiltros={onLimpiarFiltros}
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
                        tabActiva={tabActiva}
                        hayFiltrosActivos={hayFiltrosActivos}
                        onLimpiarFiltros={onLimpiarFiltros}
                        onRevisar={abrirRevisar}
                        onAnexarEnvio={abrirAnexar}
                        onBitacora={abrirBitacora}
                        onFiltrarBusqueda={onBuscar}
                        onFiltrarPaqueteria={onPaqueteriaChange}
                        onFiltrarTab={onTabChange}
                    />
                </div>
            </GeliaPageShell>

            <ModalRevisarPedido
                abierto={modalRevisar.abierto}
                pedido={modalRevisar.pedido}
                bancos={catalogos.bancos || []}
                catalogos={catalogos}
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
