import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { Settings2, X, Check, RotateCcw, Sparkles } from 'lucide-react';
import { Layers, LayoutDashboard } from '../../Components/Dashboard/dashboardIconosAnimados';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import DashboardLayoutGrid from '../../Components/Dashboard/DashboardLayoutGrid';
import DashboardMobileView from '../../Components/Dashboard/DashboardMobileView';
import DashboardPanel, { DashboardCardSlot, DashboardPanelCards } from '../../Components/Dashboard/DashboardPanel';
import DashboardToolbar from '../../Components/Dashboard/DashboardToolbar';
import DashboardAnalitico from '../../Components/Dashboard/DashboardAnalitico';
import { useDashboardBreakpoint } from '../../Components/Dashboard/useDashboardBreakpoint';
import {
    PANEL_IDS,
    DASHBOARD_PRESETS,
    buildPresetLayout,
    optimizeLayout,
    resolveLayout,
} from '../../Components/Dashboard/dashboardLayoutUtils';
import DashboardModuleCard from '../../Components/Dashboard/DashboardModuleCard';
import { DASHBOARD_MODULE_CARDS, DASHBOARD_FUNCTION_CARDS, dashboardTonoClass } from '../../Components/Dashboard/dashboardModulesCatalog';
import {
    geliaCardClass,
    GELIA_BTN_OUTLINE,
    GELIA_MODAL_TITLE,
    THEME_BTN_PRIMARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../utils/geliaTheme';
import RelojConexion from '../../Components/Dashboard/RelojConexion';

import WidgetSolicitudes from './Widgets/WidgetSolicitudes';
import WidgetCancelacionesCotizaciones from './Widgets/WidgetCancelacionesCotizaciones';
import WidgetActivos from './Widgets/WidgetActivos';
import WidgetRh from './Widgets/WidgetRh';
import WidgetCredibox from './Widgets/WidgetCredibox';
import WidgetPedidosBma from './Widgets/WidgetPedidosBma';
import WidgetFacturas from './Widgets/WidgetFacturas';
import WidgetContabilidad from './Widgets/WidgetContabilidad';

const PRESET_IDS = Object.values(DASHBOARD_PRESETS);

function buildCardGridPanel({
    variant,
    title,
    icon,
    emptyMessage,
    items,
}) {
    return (
        <DashboardPanel
            variant={variant}
            title={title}
            icon={icon}
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
                            tono={item.tono}
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
        title: 'Módulos del sistema',
        icon: LayoutDashboard,
        emptyMessage: 'No hay módulos visibles. Haz clic en "Configurar" para añadir accesos a tu panel.',
        items: tarjetasVisibles,
    });
}

function buildFuncionesPanel({ variant, funcionesVisibles }) {
    return buildCardGridPanel({
        variant,
        title: 'Funciones operativas',
        icon: Layers,
        emptyMessage: 'No hay funciones visibles. Usa Configurar para mostrar accesos operativos.',
        items: funcionesVisibles,
    });
}

export default function AdminDashboard({
    auth,
    estadisticas = {},
    filtro_periodo = { clave: 'mes', desde: '', hasta: '', etiqueta: '' },
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
    const [refreshingPeriodo, setRefreshingPeriodo] = useState(false);
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

    const aplicarPeriodo = (params) => {
        const query = { periodo: params.periodo };
        if (params.periodo === 'rango') {
            query.desde = params.desde;
            query.hasta = params.hasta;
        }
        setRefreshingPeriodo(true);
        router.get(route('dashboard'), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            showProgress: false,
            only: ['estadisticas', 'filtro_periodo', 'metricas_contabilidad'],
            onFinish: () => setRefreshingPeriodo(false),
        });
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

    const primerNombre = auth?.user?.name ? auth.user.name.trim().split(' ')[0] : 'Usuario';

    return (
        <AppLayout auth={auth}>
            <Head title="Dashboard | GELIANV" />

            <GeliaPageShell className="space-y-5 md:space-y-6 min-h-screen relative py-4 md:py-6">
                <header
                    className={geliaCardClass(
                        'dashboard-page-header p-4 md:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative z-10 dashboard-page-reveal'
                    )}
                >
                    <div className="min-w-0 flex items-center gap-3">
                        <div className="dashboard-page-header__icon" aria-hidden>
                            <LayoutDashboard size={24} duration={1} isAnimated={false} color="currentColor" />
                        </div>
                        <div className="min-w-0">
                            <h1 className="dashboard-page-header__title theme-text-main m-0">Panel de control</h1>
                            <p className="dashboard-page-header__support theme-text-muted m-0">
                                Sesión de <span className="theme-text-main">{primerNombre}</span>
                            </p>
                        </div>
                    </div>

                    <RelojConexion />
                </header>

                {activePreset !== DASHBOARD_PRESETS.LAUNCHER && (
                    <DashboardAnalitico
                        filtroPeriodo={filtro_periodo}
                        estadisticas={estadisticas}
                        metricasCredibox={metricas_credibox}
                        metricasPedidos={metricas_pedidos}
                        metricasSolicitudes={metricas_solicitudes}
                        ultimasSolicitudes={ultimas_solicitudes}
                        ultimasOperativas={ultimas_operativas}
                        alertasActivos={alertas_activos_destacadas}
                        onPeriodoChange={aplicarPeriodo}
                        refreshing={refreshingPeriodo}
                    />
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
                    <p className="text-xs theme-text-muted text-center px-2 -mt-2 dashboard-page-reveal m-0">
                        Vista optimizada para móvil. Organiza el panel desde escritorio.
                    </p>
                )}

                {editLayoutMode && !isMobile && (
                    <div
                        className="gelia-estado-vivo gelia-estado-vivo--info rounded-xl border theme-border p-4 md:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 dashboard-page-reveal"
                        role="status"
                    >
                        <div className="min-w-0">
                            <p className="text-sm font-semibold theme-text-main m-0">Modo organización activo</p>
                            <p className="text-xs theme-text-muted mt-1 m-0">
                                Arrastra los contenedores y ajústalos desde la esquina inferior derecha. Usa Autoajuste para reorganizar al instante.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <button type="button" onClick={autoAjustarDisposicion} className={GELIA_BTN_OUTLINE}>
                                <Sparkles className="w-3.5 h-3.5 shrink-0" aria-hidden />
                                Autoajuste
                            </button>
                            <button type="button" onClick={restaurarDisposicionPredeterminada} className={GELIA_BTN_OUTLINE}>
                                <RotateCcw className="w-3.5 h-3.5 shrink-0" aria-hidden />
                                Restablecer
                            </button>
                            <button type="button" onClick={cancelarEdicionLayout} className={GELIA_BTN_OUTLINE}>
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={guardarDisposicion}
                                disabled={processing}
                                aria-busy={processing}
                                className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact min-h-[44px] disabled:opacity-60`}
                            >
                                {processing ? 'Guardando…' : 'Guardar disposición'}
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
                    <div className={geliaCardClass('p-10 md:p-12 text-center dashboard-page-reveal')}>
                        <p className="text-sm theme-text-muted m-0">
                            No hay secciones visibles en tu panel. Usa Configurar para mostrar módulos.
                        </p>
                    </div>
                )}
            </GeliaPageShell>

            {showConfig &&
                createPortal(
                    <div
                        className={`${THEME_MODAL_OVERLAY} z-[9999] items-center p-4`}
                        onClick={cerrarModal}
                        role="presentation"
                    >
                        <div
                            className={`${THEME_MODAL_SHELL} w-full max-w-lg p-6 md:p-8 flex flex-col gap-5 relative max-h-[90vh]`}
                            onClick={(e) => e.stopPropagation()}
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="dashboard-config-title"
                        >
                            <button
                                type="button"
                                onClick={cerrarModal}
                                className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-full transition-colors outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)]"
                                aria-label="Cerrar"
                            >
                                <X className="w-5 h-5" />
                            </button>

                            <h3 id="dashboard-config-title" className={`${GELIA_MODAL_TITLE} flex items-center gap-3`}>
                                <Settings2 className="w-6 h-6 shrink-0" style={{ color: 'var(--color-primario)' }} aria-hidden />
                                Personalizar panel
                            </h3>

                            <div className="flex-1 min-h-0 overflow-y-auto custom-scrollbar pr-1 -mr-1 space-y-2">
                                <p className="text-xs font-medium theme-text-muted m-0 mb-2">
                                    Módulos y funciones visibles en el panel
                                </p>

                                {[...tarjetasHabilitadas, ...funcionesHabilitadas].map((item) => {
                                    const isVisible = !data.dashboard_ocultos.includes(item.id);
                                    const ItemIcon = item.icon;
                                    return (
                                        <button
                                            key={item.id}
                                            type="button"
                                            onClick={() => toggleVisibilidad(item.id)}
                                            aria-pressed={isVisible}
                                            className={`dashboard-config-row w-full flex items-center justify-between gap-3 p-3 rounded-xl border transition-colors text-left text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] ${dashboardTonoClass(item.tono)} ${
                                                isVisible
                                                    ? 'border-[color-mix(in_srgb,var(--dashboard-modulo)_45%,transparent)] bg-[color-mix(in_srgb,var(--dashboard-modulo)_8%,transparent)] theme-text-main'
                                                    : 'theme-border theme-element theme-text-muted'
                                            }`}
                                        >
                                            <span className="flex items-center gap-2 min-w-0">
                                                {ItemIcon && (
                                                    <span className="dashboard-widget__icon-wrap" aria-hidden>
                                                        <ItemIcon size={16} duration={1} isAnimated={false} color="currentColor" className="dashboard-widget__icon" />
                                                    </span>
                                                )}
                                                <span className="min-w-0 truncate">{item.titulo}</span>
                                            </span>
                                            <span
                                                className={`dashboard-config-row__check shrink-0 w-5 h-5 rounded-md border flex items-center justify-center ${
                                                    isVisible ? 'border-[var(--color-primario)] bg-[var(--color-primario)] text-white' : 'theme-border'
                                                }`}
                                                aria-hidden
                                            >
                                                {isVisible && <Check className="w-3.5 h-3.5" />}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>

                            <div className="flex flex-col sm:flex-row gap-2 pt-1">
                                <button type="button" onClick={cerrarModal} className={`${GELIA_BTN_OUTLINE} flex-1 min-h-[44px]`}>
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    onClick={guardarPreferencias}
                                    disabled={processing}
                                    aria-busy={processing}
                                    className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact flex-1 min-h-[44px] disabled:opacity-60`}
                                >
                                    {processing ? 'Guardando…' : 'Guardar preferencias'}
                                </button>
                            </div>
                        </div>
                    </div>,
                    document.body
                )}
        </AppLayout>
    );
}
