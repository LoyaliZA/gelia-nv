import React, { useEffect, useId, useMemo, useState } from 'react';
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
import {
    geliaCardClass,
    GELIA_BTN_OUTLINE,
    GELIA_MODAL_TITLE,
    THEME_BTN_PRIMARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../utils/geliaTheme';
import RelojConexion from '../../Components/Dashboard/RelojConexion';
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
    { key: 'mis_activas', label: 'Mis solicitudes abiertas', hint: 'TAG · mi cartera' },
    { key: 'solicitudes_mes', label: 'Solicitudes creadas', hint: 'Este mes · todas' },
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
        title: 'Módulos del sistema',
        icon: LayoutDashboard,
        iconStyle: { color: 'var(--color-primario)' },
        emptyMessage: 'No hay módulos visibles. Haz clic en "Configurar" para añadir accesos a tu panel.',
        items: tarjetasVisibles,
    });
}

function buildFuncionesPanel({ variant, funcionesVisibles }) {
    return buildCardGridPanel({
        variant,
        title: 'Funciones operativas',
        icon: Layers,
        iconStyle: { color: 'var(--color-primario)' },
        emptyMessage: 'No hay funciones visibles. Usa Configurar para mostrar accesos operativos.',
        items: funcionesVisibles,
    });
}

function trazarPerfilCotizado(puntos, inicio) {
    return puntos.map(([x, y], i) => {
        if (i === 0) return `${inicio} ${x},${y}`;

        const [xAnterior, yAnterior] = puntos[i - 1];
        const tercio = (x - xAnterior) / 3;
        // Las tangentes horizontales unen los tramos sin sobrepasar sus importes.
        return `C ${xAnterior + tercio},${yAnterior} ${x - tercio},${y} ${x},${y}`;
    }).join(' ');
}

function GraficaMontoCotizado({ periodo, total, tramos }) {
    const degradado = useId();
    const maximo = Math.max(0, ...tramos.map(({ monto }) => monto));
    const base = 75;
    const profundidadX = 1.5;
    const profundidadY = 5;
    const anchoTramo = 108 / Math.max(1, tramos.length - 1);
    const puntos = tramos.map(({ monto }, i) => [
        2 + i * anchoTramo,
        base - (Math.max(0, monto) / (maximo || 1)) * 58,
    ]);
    const perfil = trazarPerfilCotizado(puntos, 'M');
    const puntosTraseros = puntos.map(([x, y]) => [x + profundidadX, y - profundidadY]).reverse();
    const perfilTrasero = trazarPerfilCotizado(puntosTraseros, 'L');
    const [ultimoX, ultimoY] = puntos.at(-1) ?? [2, base];

    return (
        <figure className={geliaCardClass('m-0 min-w-0 p-4 md:p-5')}>
            <figcaption className="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
                <span className="text-xs font-medium theme-text-muted">Monto cotizado</span>
                <span className="text-[11px] theme-text-muted capitalize">{periodo}</span>
            </figcaption>
            <p className="text-2xl md:text-[1.75rem] font-semibold theme-text-main m-0 leading-none tabular-nums mt-2 break-words">
                {formatoMoneda(total)}
            </p>
            {tramos.length > 0 ? (
                <div className="mt-3 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                    <svg aria-hidden="true" viewBox="0 0 120 80" preserveAspectRatio="none" className="block h-[4.75rem] w-full">
                        <defs>
                            <linearGradient id={degradado} x1="0" x2="0" y1="0" y2="1">
                                <stop stopColor="var(--color-primario)" />
                                <stop offset="1" stopColor="color-mix(in srgb, var(--color-primario), black 28%)" />
                            </linearGradient>
                        </defs>
                        <line x1="2" y1={base} x2="116" y2={base} stroke="currentColor" className="theme-text-muted opacity-25" />
                        {maximo > 0 && (
                            <>
                                <polygon
                                    points={`${ultimoX},${ultimoY} ${ultimoX + profundidadX},${ultimoY - profundidadY} ${ultimoX + profundidadX},${base - profundidadY} ${ultimoX},${base}`}
                                    fill="color-mix(in srgb, var(--color-primario), black 40%)"
                                />
                                <path
                                    d={`${perfil} ${perfilTrasero} Z`}
                                    fill="color-mix(in srgb, var(--color-primario), white 27%)"
                                />
                                <path d={`${perfil} L ${ultimoX},${base} 2,${base} Z`} fill={`url(#${degradado})`} />
                                <path d={perfil} fill="none" stroke="color-mix(in srgb, var(--color-primario), white 48%)" strokeWidth="1.2" strokeLinejoin="round" />
                            </>
                        )}
                        {puntos.map(([x], i) => {
                            const { desde, hasta, monto } = tramos[i];
                            const inicio = i === 0 ? 0 : x - anchoTramo / 2;
                            const fin = i === tramos.length - 1 ? 120 : x + anchoTramo / 2;

                            return (
                                <rect key={desde} x={inicio} width={fin - inicio} height="80" fill="transparent">
                                    <title>{`Días ${desde}–${hasta}: ${formatoMoneda(monto)}`}</title>
                                </rect>
                            );
                        })}
                    </svg>
                    <ol aria-label="Monto por tramo de días" className="m-0 grid list-none gap-y-1 p-0 text-[11px] tabular-nums">
                        {tramos.map(({ desde, hasta, monto }) => (
                            <li key={desde} className="flex items-center justify-between gap-2">
                                <span className="theme-text-muted"><span className="sr-only">Días </span>{desde}–{hasta}</span>
                                <span className="text-right theme-text-main">{formatoMoneda(monto)}</span>
                            </li>
                        ))}
                    </ol>
                </div>
            ) : (
                <p className="text-[11px] theme-text-muted m-0 mt-2">Sin montos registrados en este periodo.</p>
            )}
        </figure>
    );
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
    const cotizadoMesAnterior = estadisticas.cotizado_mes_anterior;

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
                    <div className="min-w-0">
                        <div className="flex items-center gap-3 mb-2">
                            <span
                                className="h-1 w-8 rounded-full shrink-0"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                                aria-hidden
                            />
                            <span className="text-[10px] font-semibold tracking-wide theme-text-muted m-0">Gelia NV</span>
                        </div>
                        <h1 className="text-xl md:text-2xl font-bold theme-text-main m-0 leading-tight">Panel de control</h1>
                        <p className="text-sm theme-text-muted m-0 mt-1">
                            Sesión de <span className="font-medium theme-text-main">{primerNombre}</span>
                        </p>
                    </div>

                    <RelojConexion />
                </header>

                {(kpiItems.length > 0 || cotizadoMesAnterior) && (
                    <section aria-label="Indicadores" className="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-5 dashboard-page-reveal">
                        {kpiItems.map(({ key, label, hint }) => (
                            <div key={key} className={geliaCardClass('p-4 md:p-5 min-h-[4.5rem] flex flex-col justify-center')}>
                                <p className="text-xs font-medium theme-text-muted m-0">{label}</p>
                                <p className="text-2xl md:text-[1.75rem] font-semibold theme-text-main m-0 leading-none tabular-nums mt-2">
                                    {Number(estadisticas[key] || 0).toLocaleString('es-MX')}
                                </p>
                                {hint && <p className="text-[11px] theme-text-muted m-0 mt-2">{hint}</p>}
                            </div>
                        ))}
                        {cotizadoMesAnterior && <GraficaMontoCotizado {...cotizadoMesAnterior} />}
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
                                    return (
                                        <button
                                            key={item.id}
                                            type="button"
                                            onClick={() => toggleVisibilidad(item.id)}
                                            aria-pressed={isVisible}
                                            className={`dashboard-config-row w-full flex items-center justify-between gap-3 p-3 rounded-xl border transition-colors text-left text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] ${
                                                isVisible
                                                    ? 'border-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_8%,transparent)] theme-text-main'
                                                    : 'theme-border theme-element theme-text-muted'
                                            }`}
                                        >
                                            <span className="min-w-0 truncate">{item.titulo}</span>
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
