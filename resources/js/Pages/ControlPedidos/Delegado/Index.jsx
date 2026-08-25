import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { FileSpreadsheet, Package, Search, Truck, Send, Clock, Loader2, ChevronDown } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../../utils/geliaTheme';
import { THEME_INPUT, THEME_LABEL } from '../../../utils/geliaTheme';
import TablaDelegado from './Partials/TablaDelegado';
import PanelImportExport from './Partials/PanelImportExport';
import ModalAlertaPedido from '../Partials/ModalAlertaPedido';
import { LABELS_ESTATUS_POR_FASE, TABS_DELEGADO } from '../Partials/pedidosBmaStyles';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import useListadoDiscreto from '../Partials/useListadoDiscreto';

const TIPOS_NOTIF_GUIA = new Set(['pedido_pendiente_guia', 'pedido_error_guia']);

export default function Index({ auth, pedidos, metricas = {}, filtros = {} }) {
    const { flash } = usePage().props;
    const {
        pedidos: pedidosVista,
        metricas: metricasVista,
        cargando,
        cargar,
    } = useListadoDiscreto({
        listadoRoute: 'control_pedidos.delegado.listado',
        indexRoute: 'control_pedidos.delegado.index',
        pedidos,
        metricas,
    });

    const [tabActiva, setTabActiva] = useState(filtros.tab || 'PENDIENTES_GUIA');
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const [filtrosAbiertos, setFiltrosAbiertos] = useState(false);
    const debounceBusqueda = useRef(null);
    const modalAbiertoRef = useRef(false);

    const paramsListado = useCallback(() => ({
        tab: tabActiva,
        q: busqueda || undefined,
        page: pedidosVista?.current_page || 1,
    }), [tabActiva, busqueda, pedidosVista?.current_page]);

    useEffect(() => {
        if (flash?.success) {
            setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: flash.success });
        } else if (flash?.error) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: flash.error });
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        const interval = setInterval(() => {
            if (modalAbiertoRef.current || cargando) return;
            cargar(paramsListado(), { silencioso: true });
        }, 15000);
        return () => clearInterval(interval);
    }, [cargando, paramsListado, cargar]);

    useEffect(() => {
        const onNotification = (e) => {
            const tipo = e.detail?.tipo;
            if (!TIPOS_NOTIF_GUIA.has(tipo)) return;
            if (!modalAbiertoRef.current) {
                cargar(paramsListado(), { silencioso: true });
            }
            if (tipo === 'pedido_pendiente_guia') {
                setAlerta({
                    abierto: true,
                    tipo: 'success',
                    titulo: 'Nuevo pedido pendiente de guía',
                    mensaje: e.detail?.mensaje || e.detail?.mensaje_visible || 'Hay un pedido listo para captura de guía.',
                });
            }
        };
        window.addEventListener('notification-received', onNotification);
        return () => window.removeEventListener('notification-received', onNotification);
    }, [cargar, paramsListado]);

    const onBuscar = (valor) => {
        setBusqueda(valor);
        if (debounceBusqueda.current) clearTimeout(debounceBusqueda.current);
        debounceBusqueda.current = setTimeout(() => {
            cargar({ q: valor || undefined, tab: tabActiva, page: 1 });
        }, 400);
    };

    const onTabChange = (tab) => {
        setFiltrosAbiertos(false);
        setTabActiva(tab);
        cargar({ tab, q: busqueda || undefined, page: 1 });
    };

    const onIrAPagina = (page) => {
        cargar({ tab: tabActiva, q: busqueda || undefined, page });
    };

    const conteoTab = (tabId) => {
        const map = {
            TODOS: metricasVista.total,
            PENDIENTES_GUIA: metricasVista.pendientes_guia,
            EN_CEDIS: metricasVista.pendiente_empaque,
            PENDIENTES_ENVIO: metricasVista.pendientes_envio,
            ENVIADOS: metricasVista.enviados,
        };
        return map[tabId];
    };

    const tabActual = TABS_DELEGADO.find((t) => t.id === tabActiva) || TABS_DELEGADO[0];
    const conteoActual = conteoTab(tabActual.id);

    return (
        <AppLayout auth={auth}>
            <Head title="Actualizar guías | GELIANV" />
            <GeliaPageShell className="space-y-3 md:space-y-6">
                <header className={`${geliaCardClass()} p-4 md:p-8`}>
                    <div className="flex items-center gap-2 mb-1 md:mb-2">
                        <FileSpreadsheet className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Control de pedidos_</span>
                    </div>
                    <h1 className="text-2xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0">
                        Actualizar <span style={{ color: 'var(--color-primario)' }}>guías</span>
                    </h1>
                    <p className="text-xs md:text-sm theme-text-muted font-bold mt-1.5 md:mt-2 m-0">
                        Revisa datos, captura o corrige guías, y reporta errores al área correspondiente.
                    </p>
                </header>

                <div className={`${geliaCardClass()} p-4 md:p-5 grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-4`}>
                    <div className="flex flex-col md:flex-row items-center md:items-center gap-1 md:gap-3 text-center md:text-left">
                        <Package className="w-4 h-4 md:w-5 md:h-5" style={{ color: 'var(--color-primario)' }} />
                        <div className="min-w-0">
                            <p className="text-[8px] md:text-[9px] font-black uppercase theme-text-muted m-0 truncate">
                                {LABELS_ESTATUS_POR_FASE.PENDIENTE_DE_GUIA}
                            </p>
                            <p className="text-xl md:text-2xl font-black m-0 tabular-nums" style={{ color: 'var(--color-primario)' }}>
                                {metricasVista.pendientes_guia ?? 0}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-col md:flex-row items-center gap-1 md:gap-3 text-center md:text-left">
                        <Clock className="w-4 h-4 md:w-5 md:h-5 text-amber-500" />
                        <div className="min-w-0">
                            <p className="text-[8px] md:text-[9px] font-black uppercase theme-text-muted m-0 truncate">
                                {LABELS_ESTATUS_POR_FASE.EN_CEDIS}
                            </p>
                            <p className="text-xl md:text-2xl font-black m-0 text-amber-500 tabular-nums">
                                {metricasVista.pendiente_empaque ?? 0}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-col md:flex-row items-center gap-1 md:gap-3 text-center md:text-left">
                        <Truck className="w-4 h-4 md:w-5 md:h-5 text-sky-500" />
                        <div className="min-w-0">
                            <p className="text-[8px] md:text-[9px] font-black uppercase theme-text-muted m-0 truncate">
                                {LABELS_ESTATUS_POR_FASE.PENDIENTE_DE_ENVIO}
                            </p>
                            <p className="text-xl md:text-2xl font-black m-0 text-sky-500 tabular-nums">
                                {metricasVista.pendientes_envio ?? 0}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-col md:flex-row items-center gap-1 md:gap-3 text-center md:text-left">
                        <Send className="w-4 h-4 md:w-5 md:h-5 text-emerald-500" />
                        <div className="min-w-0">
                            <p className="text-[8px] md:text-[9px] font-black uppercase theme-text-muted m-0 truncate">
                                {LABELS_ESTATUS_POR_FASE.ENVIADO}
                            </p>
                            <p className="text-xl md:text-2xl font-black m-0 text-emerald-500 tabular-nums">
                                {metricasVista.enviados ?? 0}
                            </p>
                        </div>
                    </div>
                </div>

                <div className={`${geliaCardClass()} p-4 md:p-5 space-y-4`}>
                    <div className="flex flex-col lg:flex-row lg:items-end gap-4 justify-between">
                        <div className="flex-1 max-w-md w-full">
                            <label htmlFor="delegado-busqueda" className={`${THEME_LABEL} ml-1`}>Buscar</label>
                            <div className="theme-field-with-icon relative mt-1.5">
                                <Search className="theme-field-icon w-4 h-4" aria-hidden />
                                <input
                                    id="delegado-busqueda"
                                    type="text"
                                    value={busqueda}
                                    onChange={(e) => onBuscar(e.target.value)}
                                    placeholder="Folio, cliente o guía..."
                                    className={`${THEME_INPUT} w-full py-3 text-sm font-bold pr-10`}
                                    aria-busy={cargando}
                                    autoComplete="off"
                                />
                                {cargando && (
                                    <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin theme-text-muted" aria-label="Buscando" />
                                )}
                            </div>
                        </div>
                        {tabActiva === 'PENDIENTES_GUIA' && <PanelImportExport onAlerta={setAlerta} />}
                    </div>

                    <div className="md:hidden space-y-2">
                        <button
                            type="button"
                            onClick={() => setFiltrosAbiertos((v) => !v)}
                            aria-expanded={filtrosAbiertos}
                            className="w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl border theme-border theme-element outline-none"
                        >
                            <span className="min-w-0 text-left">
                                <span className="block text-[9px] font-black uppercase tracking-widest theme-text-muted">Filtro</span>
                                <span className="block text-xs font-black uppercase theme-text-main truncate mt-0.5">
                                    {tabActual.label}
                                    {conteoActual !== undefined ? ` · ${conteoActual}` : ''}
                                </span>
                            </span>
                            <ChevronDown className={`w-4 h-4 theme-text-muted shrink-0 transition-transform ${filtrosAbiertos ? 'rotate-180' : ''}`} aria-hidden />
                        </button>
                        {filtrosAbiertos && (
                            <div className="grid grid-cols-2 gap-2" role="tablist">
                                {TABS_DELEGADO.map((tab) => {
                                    const conteo = conteoTab(tab.id);
                                    const activo = tabActiva === tab.id;
                                    return (
                                        <button
                                            key={tab.id}
                                            type="button"
                                            role="tab"
                                            aria-selected={activo}
                                            onClick={() => onTabChange(tab.id)}
                                            className={`flex items-center justify-between gap-1 px-3 py-2.5 rounded-xl text-[10px] font-black uppercase outline-none border ${
                                                activo ? 'border-transparent text-white' : 'theme-border theme-element theme-text-muted'
                                            }`}
                                            style={activo ? { backgroundColor: 'var(--color-primario)' } : undefined}
                                        >
                                            <span className="truncate">{tab.label}</span>
                                            {conteo !== undefined && <span className="tabular-nums">{conteo}</span>}
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    <div className={`hidden md:block ${GELIA_SEGMENT_TABS_SCROLL}`}>
                        <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label="Filtro de guías">
                            {TABS_DELEGADO.map((tab) => {
                                const conteo = conteoTab(tab.id);
                                return (
                                    <button
                                        key={tab.id}
                                        type="button"
                                        role="tab"
                                        aria-selected={tabActiva === tab.id}
                                        onClick={() => onTabChange(tab.id)}
                                        className="gelia-segment-btn whitespace-nowrap gap-1.5"
                                        data-active={tabActiva === tab.id}
                                    >
                                        {tab.label}
                                        {conteo !== undefined && (
                                            <span className="text-[9px] font-black px-1.5 py-0.5 rounded-md theme-element border theme-border">
                                                {conteo}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="pt-1 border-t theme-border">
                        <GeliaPaginacion
                            paginator={pedidosVista}
                            onIrAPagina={onIrAPagina}
                            embedded
                            className="!border-0 !p-0 !pt-3"
                        />
                    </div>
                </div>

                <div className="relative min-h-[12rem]">
                    {cargando && (
                        <div className="absolute inset-0 z-10 flex items-start justify-center pt-16 pointer-events-none">
                            <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} aria-label="Cargando pedidos" />
                        </div>
                    )}
                    <TablaDelegado
                        pedidos={pedidosVista}
                        tabActiva={tabActiva}
                        onModalAbierto={(abierto) => { modalAbiertoRef.current = abierto; }}
                    />
                </div>
            </GeliaPageShell>

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
