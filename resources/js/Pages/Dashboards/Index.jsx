import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm } from '@inertiajs/react';
import { LayoutDashboard, Activity, Settings2, X, Check, Layers, RotateCcw, Sparkles } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import DashboardLayoutGrid from '../../Components/Dashboard/DashboardLayoutGrid';
import DashboardMobileView from '../../Components/Dashboard/DashboardMobileView';
import DashboardPanel, { DashboardCardSlot, DashboardPanelCards } from '../../Components/Dashboard/DashboardPanel';
import DashboardToolbar from '../../Components/Dashboard/DashboardToolbar';
import { useDashboardBreakpoint } from '../../Components/Dashboard/useDashboardBreakpoint';
import {
    PANEL_IDS,
    buildDefaultLayout,
    optimizeLayout,
    resolveLayout,
} from '../../Components/Dashboard/dashboardLayoutUtils';
import DashboardModuleCard from '../../Components/Dashboard/DashboardModuleCard';
import { DASHBOARD_MODULE_CARDS, DASHBOARD_FUNCTION_CARDS } from '../../Components/Dashboard/dashboardModulesCatalog';

import WidgetSolicitudes from './Widgets/WidgetSolicitudes';
import WidgetCancelacionesCotizaciones from './Widgets/WidgetCancelacionesCotizaciones';
import WidgetActivos from './Widgets/WidgetActivos';
import WidgetRh from './Widgets/WidgetRh';

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

export default function AdminDashboard({ auth, ultimas_solicitudes = [], ultimas_operativas = [], alertas_activos_resumen = {}, alertas_activos_destacadas = [], rh_widget = {} }) {
    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Super Admin');

    const [showConfig, setShowConfig] = useState(false);
    const [editLayoutMode, setEditLayoutMode] = useState(false);
    const { isMobile } = useDashboardBreakpoint();

    const dashboardOcultosBD = auth?.tema_visual?.dashboard_ocultos || [];
    const dashboardLayoutBD = auth?.tema_visual?.dashboard_layout || null;

    const { data, setData, put, processing } = useForm({
        dashboard_ocultos: dashboardOcultosBD,
        dashboard_layout: dashboardLayoutBD,
    });

    const catalogoFunciones = DASHBOARD_FUNCTION_CARDS;
    const catalogoTarjetas = DASHBOARD_MODULE_CARDS;

    const tarjetasHabilitadas = catalogoTarjetas.filter((tarjeta) => can(tarjeta.permiso));
    const tarjetasVisibles = tarjetasHabilitadas.filter((tarjeta) => !dashboardOcultosBD.includes(tarjeta.id));

    const funcionesHabilitadas = catalogoFunciones.filter((func) => can(func.permiso));
    const funcionesVisibles = funcionesHabilitadas.filter((func) => !dashboardOcultosBD.includes(func.id));

    const mostrarWidgetSolicitudes = can('configuracion.ver_auditoria') || can('solicitudes.ver_listado');
    const mostrarWidgetCancelaciones = can('cancelaciones_cotizaciones.ver_listado');
    const mostrarWidgetActivos = can('activos.ver');
    const mostrarWidgetRh = can('rh.ver');

    const visiblePanelIds = useMemo(() => {
        const ids = [];
        if (tarjetasVisibles.length > 0) ids.push(PANEL_IDS.MODULOS);
        if (funcionesVisibles.length > 0) ids.push(PANEL_IDS.FUNCIONES);
        if (mostrarWidgetSolicitudes) ids.push(PANEL_IDS.SOLICITUDES);
        if (mostrarWidgetCancelaciones) ids.push(PANEL_IDS.CANCELACIONES);
        if (mostrarWidgetActivos) ids.push(PANEL_IDS.ACTIVOS);
        if (mostrarWidgetRh) ids.push(PANEL_IDS.RH);
        return ids;
    }, [tarjetasVisibles.length, funcionesVisibles.length, mostrarWidgetSolicitudes, mostrarWidgetCancelaciones, mostrarWidgetActivos, mostrarWidgetRh]);

    const defaultLayout = useMemo(
        () =>
            buildDefaultLayout({
                hasModulos: tarjetasVisibles.length > 0,
                hasFunciones: funcionesVisibles.length > 0,
                hasWidgetSolicitudes: mostrarWidgetSolicitudes,
                hasWidgetCancelaciones: mostrarWidgetCancelaciones,
                hasWidgetActivos: mostrarWidgetActivos,
                hasWidgetRh: mostrarWidgetRh,
            }),
        [tarjetasVisibles.length, funcionesVisibles.length, mostrarWidgetSolicitudes, mostrarWidgetCancelaciones, mostrarWidgetActivos, mostrarWidgetRh]
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
            [PANEL_IDS.SOLICITUDES]: <WidgetSolicitudes ultimas_solicitudes={ultimas_solicitudes} variant="desktop" />,
            [PANEL_IDS.CANCELACIONES]: <WidgetCancelacionesCotizaciones ultimas_operativas={ultimas_operativas} variant="desktop" />,
            [PANEL_IDS.ACTIVOS]: (
                <WidgetActivos
                    alertas_resumen={alertas_activos_resumen}
                    alertas_destacadas={alertas_activos_destacadas}
                    variant="desktop"
                />
            ),
            [PANEL_IDS.RH]: <WidgetRh rh_widget={rh_widget} variant="desktop" />,
        }),
        [tarjetasVisibles, funcionesVisibles, ultimas_solicitudes, ultimas_operativas, alertas_activos_resumen, alertas_activos_destacadas, rh_widget]
    );

    const mobilePanels = useMemo(
        () => ({
            [PANEL_IDS.MODULOS]: buildModulosPanel({ variant: 'mobile', ...panelArgs }),
            [PANEL_IDS.FUNCIONES]: buildFuncionesPanel({ variant: 'mobile', ...panelArgs }),
            [PANEL_IDS.SOLICITUDES]: <WidgetSolicitudes ultimas_solicitudes={ultimas_solicitudes} variant="mobile" />,
            [PANEL_IDS.CANCELACIONES]: <WidgetCancelacionesCotizaciones ultimas_operativas={ultimas_operativas} variant="mobile" />,
            [PANEL_IDS.ACTIVOS]: (
                <WidgetActivos
                    alertas_resumen={alertas_activos_resumen}
                    alertas_destacadas={alertas_activos_destacadas}
                    variant="mobile"
                />
            ),
            [PANEL_IDS.RH]: <WidgetRh rh_widget={rh_widget} variant="mobile" />,
        }),
        [tarjetasVisibles, funcionesVisibles, ultimas_solicitudes, ultimas_operativas, alertas_activos_resumen, alertas_activos_destacadas, rh_widget]
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
            onSuccess: () => setEditLayoutMode(false),
            preserveScroll: true,
        });
    };

    const cancelarEdicionLayout = () => {
        setData('dashboard_layout', dashboardLayoutBD);
        setEditLayoutMode(false);
    };

    const restaurarDisposicionPredeterminada = () => {
        setData('dashboard_layout', defaultLayout);
    };

    const onLayoutChange = (newLayout) => {
        setData('dashboard_layout', newLayout);
    };

    const autoAjustarDisposicion = () => {
        const optimized = optimizeLayout(activeLayout, defaultLayout);
        setData('dashboard_layout', optimized);
        if (!editLayoutMode) setEditLayoutMode(true);
    };

    const hayPaneles = activeLayout.length > 0;

    return (
        <AppLayout auth={auth}>
            <Head title="Dashboard | GELIANV" />

            <div className="w-full max-w-[1400px] mx-auto p-4 md:p-6 lg:p-12 space-y-8 md:space-y-10 min-h-screen relative">
                <header className="theme-surface border-2 theme-border rounded-[2rem] md:rounded-[3rem] p-6 md:p-8 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10 dashboard-page-reveal">
                    <div className="space-y-4">
                        <div className="flex items-center space-x-3">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>
                                Gelia NV
                            </p>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0 p-0">
                            BIENVENIDO,{' '}
                            <span style={{ color: 'var(--color-primario)' }}>
                                {auth?.user?.name ? auth.user.name.trim().split(' ')[0] : 'USUARIO'}
                            </span>
                        </h1>
                    </div>

                    <div className="flex items-center gap-4 p-4 theme-element border-2 theme-border rounded-2xl shadow-sm w-full md:w-auto">
                        <div className="w-10 h-10 bg-emerald-500/10 rounded-xl border border-emerald-500/20 flex items-center justify-center shrink-0">
                            <Activity className="w-5 h-5 text-emerald-500" />
                        </div>
                        <div>
                           
                           
                           
                           
                           
                           
                           
                           
                           
                           
                           
                           
                           
                           
                           
                           
                             <p className="text-[9px] font-black theme-text-muted uppercase tracking-widest leading-tight">Estado de Servidor_</p>
                            <p className="text-xs font-black text-emerald-500 italic uppercase leading-tight mt-0.5">Operativo</p>
                        </div>
                    </div>
                </header>

                {hayPaneles && (
                    <DashboardToolbar
                        editLayoutMode={editLayoutMode}
                        isMobile={isMobile}
                        onOrganize={() => setEditLayoutMode(true)}
                        onConfigure={() => setShowConfig(true)}
                        onAutoAdjust={autoAjustarDisposicion}
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
