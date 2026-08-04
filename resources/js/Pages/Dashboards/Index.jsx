import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { LayoutDashboard, Activity, Settings2, X, Check, Layers, RotateCcw, Sparkles, Clock } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import DashboardLayoutGrid from '../../Components/Dashboard/DashboardLayoutGrid';
import DashboardMobileView from '../../Components/Dashboard/DashboardMobileView';
import DashboardPanel, { DashboardCardSlot, DashboardPanelCards } from '../../Components/Dashboard/DashboardPanel';
import DashboardToolbar from '../../Components/Dashboard/DashboardToolbar';
import { useDashboardBreakpoint } from '../../Components/Dashboard/useDashboardBreakpoint';
import {
    PANEL_IDS,
    DASHBOARD_PRESETS,
    buildPresetLayout,
    optimizeLayout,
    resolveLayout,
} from '../../Components/Dashboard/dashboardLayoutUtils';
import DashboardModuleCard from '../../Components/Dashboard/DashboardModuleCard';
import { DASHBOARD_MODULE_CARDS, DASHBOARD_FUNCTION_CARDS } from '../../Components/Dashboard/dashboardModulesCatalog';
import { geliaCardClass } from '../../utils/geliaTheme';
import { formatoMoneda } from '../../utils/formatoMoneda';

import WidgetSolicitudes from './Widgets/WidgetSolicitudes';
import WidgetCancelacionesCotizaciones from './Widgets/WidgetCancelacionesCotizaciones';
import WidgetActivos from './Widgets/WidgetActivos';
import WidgetRh from './Widgets/WidgetRh';
import WidgetCredibox from './Widgets/WidgetCredibox';
import WidgetPedidosBma from './Widgets/WidgetPedidosBma';
import WidgetFacturas from './Widgets/WidgetFacturas';
import WidgetContabilidad from './Widgets/WidgetContabilidad';

const KPI_STRIP_CONFIG = [
    { key: 'mis_activas', label: 'Mis solicitudes abiertas', hint: 'TAG · mi cartera', format: 'number' },
    { key: 'solicitudes_mes', label: 'Solicitudes creadas', hint: 'Este mes · todas', format: 'number' },
    { key: 'cotizado_global', label: 'Monto cotizado', hint: 'Este mes · suma', format: 'money' },
];

const PRESET_IDS = Object.values(DASHBOARD_PRESETS);

function buildCardGridPanel({
    variant,
    title,
    icon,
    iconStyle,
    iconClassName = '',
    emptyMessage,
    items,
}) {
    return (
        <DashboardPanel
            variant={variant}
            title={title}
            icon={icon}
            iconStyle={iconStyle}
            iconClassName={iconClassName}
        >
            <DashboardPanelCards variant={variant} emptyMessage={emptyMessage}>
                {items.map((item) => (
                    <DashboardCardSlot key={item.id} variant={variant}>
                        <DashboardModuleCard
                            variant={variant}
                            href={item.href()}
                            title={item.titulo}
                            subtitle={item.subtitulo}
                            icon={item.icon}
                            borderClass={item.borderClass || 'theme-border'}
                            iconWrapClass={item.iconWrapClass || 'theme-element theme-border'}
                            iconClass={item.iconClass || 'theme-text-main'}
                            iconWrapStyle={item.iconWrapStyle}
                            iconStyle={item.iconStyle}
                            borderStyle={item.borderStyle}
                        />
                    </DashboardCardSlot>
                ))}
            </DashboardPanelCards>
        </DashboardPanel>
    );
}

function buildModulosPanel({ variant, tarjetasVisibles }) {
    return buildCardGridPanel({
        variant,
        title: 'Módulos de Sistema_',
        icon: LayoutDashboard,
        iconStyle: { color: 'var(--color-primario)' },
        emptyMessage: 'No hay módulos visibles. Haz clic en "Configurar" para añadir accesos a tu panel.',
        items: tarjetasVisibles,
    });
}

function buildFuncionesPanel({ variant, funcionesVisibles }) {
    return buildCardGridPanel({
        variant,
        title: 'Funciones Operativas_',
        icon: Layers,
        iconClassName: 'text-indigo-500',
        items: funcionesVisibles,
    });
}

function formatKpiValue(value, format) {
    if (format === 'money') return formatoMoneda(value);
    return Number(value || 0).toLocaleString('es-MX');
}

export default function AdminDashboard({
    auth,
    estadisticas = {},
    ultimas_solicitudes = [],
    ultimas_operativas = [],
    metricas_solicitudes = {},
    metricas_operativas = {},
    metricas_credibox = {},
    metricas_pedidos = {},
    metricas_facturas = {},
    metricas_contabilidad = {},
    alertas_activos_resumen = {},
    alertas_activos_destacadas = [],
    rh_widget = {},
}) {
    const { gelia_ai_visible: geliaAiVisible = false } = usePage().props;
    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Super Admin');

    const [showConfig, setShowConfig] = useState(false);
    const [editLayoutMode, setEditLayoutMode] = useState(false);
    const { isMobile } = useDashboardBreakpoint();

    const dashboardOcultosBD = auth?.tema_visual?.dashboard_ocultos || [];
    const dashboardLayoutBD = auth?.tema_visual?.dashboard_layout || null;
    const dashboardPresetBD = PRESET_IDS.includes(auth?.tema_visual?.dashboard_preset)
        ? auth.tema_visual.dashboard_preset
        : DASHBOARD_PRESETS.OPERATIVO;

    const { data, setData, put, processing } = useForm({
        dashboard_ocultos: dashboardOcultosBD,
        dashboard_layout: dashboardLayoutBD,
        dashboard_preset: dashboardPresetBD,
    });

    const activePreset = PRESET_IDS.includes(data.dashboard_preset)
        ? data.dashboard_preset
        : DASHBOARD_PRESETS.OPERATIVO;

    const catalogoFunciones = DASHBOARD_FUNCTION_CARDS;
    const catalogoTarjetas = DASHBOARD_MODULE_CARDS;

    const tarjetaPermitida = (tarjeta) => {
        if (tarjeta.accesoGeliaAi) {
            return Boolean(geliaAiVisible);
        }
        if (tarjeta.permisoAny?.length) {
            return tarjeta.permisoAny.some((permiso) => can(permiso));
        }
        return tarjeta.permiso ? can(tarjeta.permiso) : true;
    };

    const tarjetasHabilitadas = catalogoTarjetas.filter(tarjetaPermitida);
    const tarjetasVisibles = tarjetasHabilitadas.filter((tarjeta) => !dashboardOcultosBD.includes(tarjeta.id));

    const funcionesHabilitadas = catalogoFunciones.filter((func) => can(func.permiso));
    const funcionesVisibles = funcionesHabilitadas.filter((func) => !dashboardOcultosBD.includes(func.id));

    const mostrarWidgetSolicitudes = can('configuracion.ver_auditoria') || can('solicitudes.ver_listado') || can('solicitudes.gestionar');
    const mostrarWidgetCancelaciones = can('cancelaciones_cotizaciones.ver_listado');
    const mostrarWidgetActivos = can('activos.ver');
    const mostrarWidgetRh = can('rh.ver');
    const mostrarWidgetCredibox = can('cobranza.ver');
    const mostrarWidgetPedidos = can('control_pedidos.ver_listado');
    const mostrarWidgetFacturas = can('facturas.ver_listado');
    const mostrarWidgetContabilidad = can('contabilidad.ver');

    const kpiItems = useMemo(
        () => KPI_STRIP_CONFIG.filter((item) => Object.prototype.hasOwnProperty.call(estadisticas, item.key)),
        [estadisticas]
    );

    const pendientesAtencion = useMemo(() => {
        let total = 0;
        if (mostrarWidgetSolicitudes) total += metricas_solicitudes.pendientes ?? 0;
        if (mostrarWidgetCancelaciones) total += metricas_operativas.pendientes ?? 0;
        if (mostrarWidgetActivos) {
            total += (alertas_activos_resumen.vencidos || 0)
                + (alertas_activos_resumen.proximos_7 || 0)
                + (alertas_activos_resumen.mantenimiento || 0);
        }
        if (mostrarWidgetRh) {
            total += (rh_widget.pendientes_he ?? rh_widget.pendientes ?? 0)
                + (rh_widget.pendientes_incidencias ?? 0);
        }
        if (mostrarWidgetCredibox) total += metricas_credibox.alertas_pendientes ?? 0;
        if (mostrarWidgetPedidos) {
            total += (metricas_pedidos.pendiente_auxiliar ?? 0) + (metricas_pedidos.en_cedis ?? 0);
        }
        if (mostrarWidgetFacturas) total += metricas_facturas.pendientes ?? 0;
        return total;
    }, [
        mostrarWidgetSolicitudes, metricas_solicitudes,
        mostrarWidgetCancelaciones, metricas_operativas,
        mostrarWidgetActivos, alertas_activos_resumen,
        mostrarWidgetRh, rh_widget,
        mostrarWidgetCredibox, metricas_credibox,
        mostrarWidgetPedidos, metricas_pedidos,
        mostrarWidgetFacturas, metricas_facturas,
    ]);

    const layoutFlags = useMemo(
        () => ({
            hasModulos: tarjetasVisibles.length > 0,
            hasFunciones: funcionesVisibles.length > 0,
            hasWidgetSolicitudes: mostrarWidgetSolicitudes,
            hasWidgetCancelaciones: mostrarWidgetCancelaciones,
            hasWidgetActivos: mostrarWidgetActivos,
            hasWidgetRh: mostrarWidgetRh,
            hasWidgetCredibox: mostrarWidgetCredibox,
            hasWidgetPedidos: mostrarWidgetPedidos,
            hasWidgetFacturas: mostrarWidgetFacturas,
            hasWidgetContabilidad: mostrarWidgetContabilidad,
        }),
        [
            tarjetasVisibles.length, funcionesVisibles.length,
            mostrarWidgetSolicitudes, mostrarWidgetCancelaciones, mostrarWidgetActivos, mostrarWidgetRh,
            mostrarWidgetCredibox, mostrarWidgetPedidos, mostrarWidgetFacturas, mostrarWidgetContabilidad,
        ]
    );

    const visiblePanelIds = useMemo(() => {
        const ids = [];
        if (activePreset !== DASHBOARD_PRESETS.LAUNCHER) {
            if (mostrarWidgetCredibox) ids.push(PANEL_IDS.CREDIBOX);
            if (mostrarWidgetPedidos) ids.push(PANEL_IDS.PEDIDOS);
            if (mostrarWidgetFacturas) ids.push(PANEL_IDS.FACTURAS);
            if (mostrarWidgetContabilidad) ids.push(PANEL_IDS.CONTABILIDAD);
            if (mostrarWidgetSolicitudes) ids.push(PANEL_IDS.SOLICITUDES);
            if (mostrarWidgetCancelaciones) ids.push(PANEL_IDS.CANCELACIONES);
            if (mostrarWidgetActivos) ids.push(PANEL_IDS.ACTIVOS);
            if (mostrarWidgetRh) ids.push(PANEL_IDS.RH);
        }
        if (tarjetasVisibles.length > 0) ids.push(PANEL_IDS.MODULOS);
        if (funcionesVisibles.length > 0) ids.push(PANEL_IDS.FUNCIONES);
        return ids;
    }, [
        activePreset,
        tarjetasVisibles.length, funcionesVisibles.length,
        mostrarWidgetSolicitudes, mostrarWidgetCancelaciones, mostrarWidgetActivos, mostrarWidgetRh,
        mostrarWidgetCredibox, mostrarWidgetPedidos, mostrarWidgetFacturas, mostrarWidgetContabilidad,
    ]);

    const defaultLayout = useMemo(
        () => buildPresetLayout(activePreset, layoutFlags),
        [activePreset, layoutFlags]
    );

    const activeLayout = useMemo(
        () => resolveLayout(data.dashboard_layout, visiblePanelIds, defaultLayout),
        [data.dashboard_layout, visiblePanelIds, defaultLayout]
    );

    const panelArgs = { tarjetasVisibles, funcionesVisibles };

    const desktopPanels = useMemo(
        () => ({
            [PANEL_IDS.MODULOS]: buildModulosPanel({ variant: 'desktop', ...panelArgs }),
            [PANEL_IDS.FUNCIONES]: buildFuncionesPanel({ variant: 'desktop', ...panelArgs }),
            [PANEL_IDS.SOLICITUDES]: (
                <WidgetSolicitudes ultimas_solicitudes={ultimas_solicitudes} metricas={metricas_solicitudes} variant="desktop" />
            ),
            [PANEL_IDS.CANCELACIONES]: (
                <WidgetCancelacionesCotizaciones ultimas_operativas={ultimas_operativas} metricas={metricas_operativas} variant="desktop" />
            ),
            [PANEL_IDS.ACTIVOS]: (
                <WidgetActivos
                    alertas_resumen={alertas_activos_resumen}
                    alertas_destacadas={alertas_activos_destacadas}
                    variant="desktop"
                />
            ),
            [PANEL_IDS.RH]: <WidgetRh rh_widget={rh_widget} variant="desktop" />,
            [PANEL_IDS.CREDIBOX]: <WidgetCredibox metricas={metricas_credibox} variant="desktop" />,
            [PANEL_IDS.PEDIDOS]: <WidgetPedidosBma metricas={metricas_pedidos} variant="desktop" />,
            [PANEL_IDS.FACTURAS]: <WidgetFacturas metricas={metricas_facturas} variant="desktop" />,
            [PANEL_IDS.CONTABILIDAD]: <WidgetContabilidad metricas={metricas_contabilidad} variant="desktop" />,
        }),
        [
            tarjetasVisibles, funcionesVisibles, ultimas_solicitudes, ultimas_operativas,
            metricas_solicitudes, metricas_operativas, metricas_credibox, metricas_pedidos,
            metricas_facturas, metricas_contabilidad, alertas_activos_resumen, alertas_activos_destacadas, rh_widget,
        ]
    );

    const mobilePanels = useMemo(
        () => ({
            [PANEL_IDS.MODULOS]: buildModulosPanel({ variant: 'mobile', ...panelArgs }),
            [PANEL_IDS.FUNCIONES]: buildFuncionesPanel({ variant: 'mobile', ...panelArgs }),
            [PANEL_IDS.SOLICITUDES]: (
                <WidgetSolicitudes ultimas_solicitudes={ultimas_solicitudes} metricas={metricas_solicitudes} variant="mobile" />
            ),
            [PANEL_IDS.CANCELACIONES]: (
                <WidgetCancelacionesCotizaciones ultimas_operativas={ultimas_operativas} metricas={metricas_operativas} variant="mobile" />
            ),
            [PANEL_IDS.ACTIVOS]: (
                <WidgetActivos
                    alertas_resumen={alertas_activos_resumen}
                    alertas_destacadas={alertas_activos_destacadas}
                    variant="mobile"
                />
            ),
            [PANEL_IDS.RH]: <WidgetRh rh_widget={rh_widget} variant="mobile" />,
            [PANEL_IDS.CREDIBOX]: <WidgetCredibox metricas={metricas_credibox} variant="mobile" />,
            [PANEL_IDS.PEDIDOS]: <WidgetPedidosBma metricas={metricas_pedidos} variant="mobile" />,
            [PANEL_IDS.FACTURAS]: <WidgetFacturas metricas={metricas_facturas} variant="mobile" />,
            [PANEL_IDS.CONTABILIDAD]: <WidgetContabilidad metricas={metricas_contabilidad} variant="mobile" />,
        }),
        [
            tarjetasVisibles, funcionesVisibles, ultimas_solicitudes, ultimas_operativas,
            metricas_solicitudes, metricas_operativas, metricas_credibox, metricas_pedidos,
            metricas_facturas, metricas_contabilidad, alertas_activos_resumen, alertas_activos_destacadas, rh_widget,
        ]
    );

    useEffect(() => {
        if (showConfig) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
        return () => {
            document.body.style.overflow = '';
        };
    }, [showConfig]);

    useEffect(() => {
        if (isMobile && editLayoutMode) setEditLayoutMode(false);
    }, [isMobile, editLayoutMode]);

    const toggleVisibilidad = (id) => {
        const nuevosOcultos = data.dashboard_ocultos.includes(id)
            ? data.dashboard_ocultos.filter((item) => item !== id)
            : [...data.dashboard_ocultos, id];
        setData('dashboard_ocultos', nuevosOcultos);
    };

    const cerrarModal = () => {
        setData('dashboard_ocultos', dashboardOcultosBD);
        setShowConfig(false);
    };

    const guardarPreferencias = () => {
        put(route('dashboard.preferencias'), {
            onSuccess: () => setShowConfig(false),
            preserveScroll: true,
        });
    };

    const guardarDisposicion = () => {
        put(route('dashboard.preferencias'), {
            onSuccess: (page) => {
                setEditLayoutMode(false);
                const saved = page.props.auth?.tema_visual?.dashboard_layout;
                const savedPreset = page.props.auth?.tema_visual?.dashboard_preset;
                const next = { ...data };
                if (Array.isArray(saved)) next.dashboard_layout = saved;
                if (PRESET_IDS.includes(savedPreset)) next.dashboard_preset = savedPreset;
                setData(next);
            },
            preserveScroll: true,
        });
    };

    const cancelarEdicionLayout = () => {
        setData('dashboard_layout', dashboardLayoutBD);
        setEditLayoutMode(false);
    };

    const restaurarDisposicionPredeterminada = () => {
        setData('dashboard_layout', buildPresetLayout(activePreset, layoutFlags));
    };

    const onLayoutChange = (newLayout) => {
        setData('dashboard_layout', newLayout);
    };

    const autoAjustarDisposicion = () => {
        const optimized = optimizeLayout(activeLayout, defaultLayout);
        setData('dashboard_layout', optimized);
        if (!editLayoutMode) setEditLayoutMode(true);
    };

    const aplicarPreset = (presetId) => {
        if (!PRESET_IDS.includes(presetId)) return;
        const nextLayout = buildPresetLayout(presetId, layoutFlags);
        const payload = {
            dashboard_ocultos: data.dashboard_ocultos,
            dashboard_layout: nextLayout,
            dashboard_preset: presetId,
        };
        setData(payload);
        router.put(route('dashboard.preferencias'), payload, {
            preserveScroll: true,
        });
    };

    const hayPaneles = activeLayout.length > 0;
    const hayColasVisibles = mostrarWidgetSolicitudes || mostrarWidgetCancelaciones || mostrarWidgetActivos
        || mostrarWidgetRh || mostrarWidgetCredibox || mostrarWidgetPedidos || mostrarWidgetFacturas;

    return (
        <AppLayout auth={auth}>
            <Head title="Dashboard | GELIANV" />

            <div className="w-full max-w-[1400px] mx-auto p-4 md:p-6 lg:p-12 space-y-8 md:space-y-10 min-h-screen relative">
                <header className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 relative z-10 dashboard-page-reveal">
                    <div className="flex items-center gap-3 min-w-0 flex-1 theme-surface border theme-border rounded-2xl px-4 py-3 shadow-sm">
                        <span className="h-1 w-8 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                        <div className="min-w-0 overflow-visible">
                            <p className="text-[9px] font-black uppercase tracking-[0.25em] m-0" style={{ color: 'var(--color-primario)' }}>
                                Gelia NV
                            </p>
                            <p className="text-lg md:text-xl font-black italic tracking-tight uppercase theme-text-main m-0 pr-1">
                                Hola,{' '}
                                <span className="inline-block" style={{ color: 'var(--color-primario)' }}>
                                    {auth?.user?.name ? auth.user.name.trim().split(' ')[0] : 'Usuario'}
                                </span>
                            </p>
                        </div>
                    </div>

                    {hayColasVisibles && (
                        <div
                            className={`inline-flex items-center gap-2 px-4 py-3 rounded-2xl border text-[9px] font-black uppercase tracking-widest shrink-0 shadow-sm theme-surface ${
                                pendientesAtencion > 0
                                    ? 'border-amber-500/30 text-amber-600'
                                    : 'border-emerald-500/30 text-emerald-500'
                            }`}
                        >
                            {pendientesAtencion > 0 ? (
                                <>
                                    <span className="w-8 h-8 rounded-xl bg-amber-500/15 border border-amber-500/25 flex items-center justify-center shrink-0">
                                        <Clock className="w-3.5 h-3.5" />
                                    </span>
                                    {pendientesAtencion} pendientes
                                </>
                            ) : (
                                <>
                                    <span className="w-8 h-8 rounded-xl bg-emerald-500/15 border border-emerald-500/25 flex items-center justify-center shrink-0">
                                        <Activity className="w-3.5 h-3.5" />
                                    </span>
                                    Al día
                                </>
                            )}
                        </div>
                    )}
                </header>

                {kpiItems.length > 0 && (
                    <section aria-label="Indicadores" className="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6 dashboard-page-reveal">
                        {kpiItems.map(({ key, label, hint, format }) => (
                            <div key={key} className={geliaCardClass('p-4 md:p-5 min-h-[4.5rem] flex flex-col justify-center')}>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
                                <p
                                    className="text-2xl md:text-3xl font-black italic theme-text-main m-0 leading-none tabular-nums mt-2"
                                    style={{ color: 'var(--color-primario)' }}
                                >
                                    {formatKpiValue(estadisticas[key], format)}
                                </p>
                                {hint && (
                                    <p className="text-[9px] font-bold uppercase tracking-widest theme-text-muted m-0 mt-2 opacity-80">
                                        {hint}
                                    </p>
                                )}
                            </div>
                        ))}
                    </section>
                )}

                {hayPaneles && (
                    <DashboardToolbar
                        editLayoutMode={editLayoutMode}
                        isMobile={isMobile}
                        onOrganize={() => setEditLayoutMode(true)}
                        onConfigure={() => setShowConfig(true)}
                        onAutoAdjust={autoAjustarDisposicion}
                        preset={activePreset}
                        onPresetChange={aplicarPreset}
                    />
                )}

                {isMobile && hayPaneles && (
                    <p className="text-[9px] font-bold theme-text-muted uppercase tracking-widest text-center px-2 -mt-4 dashboard-page-reveal">
                        Vista optimizada para móvil. Organiza el panel desde escritorio.
                    </p>
                )}

                {editLayoutMode && !isMobile && (
                    <div
                        className="theme-surface border-2 rounded-2xl p-4 md:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 dashboard-page-reveal"
                        style={{ borderColor: 'var(--color-primario)' }}
                    >
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>
                                Modo organización activo_
                            </p>
                            <p className="text-xs font-bold theme-text-muted mt-1">
                                Arrastra los contenedores con &quot;Arrastrar&quot; y estíralos desde la esquina inferior derecha. Usa Autoajuste para reorganizar al instante.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                onClick={autoAjustarDisposicion}
                                className="flex items-center gap-2 px-4 py-2.5 rounded-xl theme-element border theme-border text-[9px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main outline-none"
                            >
                                <Sparkles className="w-3.5 h-3.5" /> Autoajuste
                            </button>
                            <button
                                type="button"
                                onClick={restaurarDisposicionPredeterminada}
                                className="flex items-center gap-2 px-4 py-2.5 rounded-xl theme-element border theme-border text-[9px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main outline-none"
                            >
                                <RotateCcw className="w-3.5 h-3.5" /> Restablecer
                            </button>
                            <button
                                type="button"
                                onClick={cancelarEdicionLayout}
                                className="px-4 py-2.5 rounded-xl theme-element border theme-border text-[9px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main outline-none"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={guardarDisposicion}
                                disabled={processing}
                                className="px-5 py-2.5 rounded-xl text-white text-[9px] font-black uppercase tracking-widest shadow-md outline-none disabled:opacity-60"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                {processing ? 'Guardando...' : 'Guardar disposición'}
                            </button>
                        </div>
                    </div>
                )}

                {hayPaneles ? (
                    <div className="relative z-10">
                        {isMobile ? (
                            <DashboardMobileView
                                layout={activeLayout}
                                visiblePanelIds={visiblePanelIds}
                                panels={mobilePanels}
                            />
                        ) : (
                            <DashboardLayoutGrid
                                layout={activeLayout}
                                editMode={editLayoutMode}
                                onLayoutChange={onLayoutChange}
                                panels={desktopPanels}
                                visiblePanelIds={visiblePanelIds}
                                animateLayout
                            />
                        )}
                    </div>
                ) : (
                    <div className="theme-surface border-2 theme-border rounded-[2rem] p-12 text-center dashboard-page-reveal">
                        <p className="text-xs font-bold theme-text-muted uppercase tracking-widest">
                            No hay secciones visibles en tu panel. Usa Configurar para mostrar módulos.
                        </p>
                    </div>
                )}
            </div>

            {showConfig &&
                createPortal(
                    <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl" onClick={cerrarModal}>
                        <div
                            className="w-full max-w-lg theme-surface theme-border border shadow-2xl rounded-[2.5rem] p-8 md:p-10 flex flex-col space-y-6 relative"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <button
                                type="button"
                                onClick={cerrarModal}
                                className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none"
                            >
                                <X className="w-5 h-5" />
                            </button>

                            <h3 className="text-lg font-black uppercase italic tracking-tighter theme-text-main m-0 flex items-center gap-3">
                                <Settings2 className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                                Personalizar Panel_
                            </h3>

                            <div className="space-y-3 max-h-[50vh] overflow-y-auto custom-scrollbar pr-2">
                                <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mb-4 ml-1">Tarjetas operativas disponibles:</p>

                                {tarjetasHabilitadas.map((tarjeta) => {
                                    const isVisible = !data.dashboard_ocultos.includes(tarjeta.id);
                                    return (
                                        <button
                                            key={tarjeta.id}
                                            type="button"
                                            onClick={() => toggleVisibilidad(tarjeta.id)}
                                            className={`w-full flex items-center justify-between p-4 rounded-2xl border transition-all text-[11px] font-black uppercase tracking-widest outline-none ${isVisible ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/5 theme-text-main' : 'theme-border theme-element theme-text-muted hover:border-[var(--color-primario)]/30'}`}
                                        >
                                            {tarjeta.titulo}
                                            {isVisible && <Check className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />}
                                        </button>
                                    );
                                })}
                            </div>

                            <button
                                type="button"
                                onClick={guardarPreferencias}
                                disabled={processing}
                                className="w-full py-4 rounded-full text-white font-black uppercase tracking-widest text-[11px] shadow-md flex justify-center items-center gap-2 outline-none m-0 disabled:opacity-60"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Check className="w-5 h-5" /> {processing ? 'Procesando...' : 'Aplicar Preferencias'}
                            </button>
                        </div>
                    </div>,
                    document.body
                )}
        </AppLayout>
    );
}
