import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm } from '@inertiajs/react';
import { LayoutDashboard, Activity, Settings2, X, Check, Layers, Move, RotateCcw } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import DashboardLayoutGrid from '../../Components/Dashboard/DashboardLayoutGrid';
import DashboardPanel, { DashboardCardSlot, DashboardPanelCards } from '../../Components/Dashboard/DashboardPanel';
import { useDashboardBreakpoint } from '../../Components/Dashboard/useDashboardBreakpoint';
import {
    PANEL_IDS,
    buildDefaultLayout,
    resolveLayout,
} from '../../Components/Dashboard/dashboardLayoutUtils';

// Separación de responsabilidades: Imports de submódulos atómicos
import ModuleUsuarios from './Modules/ModuleUsuarios';
import ModuleAccesos from './Modules/ModuleAccesos';
import ModuleCatalogos from './Modules/ModuleCatalogos';
import ModuleClientes from './Modules/ModuleClientes';
import ModuleSolicitudes from './Modules/ModuleSolicitudes';
import ModuleMisClientes from './Modules/ModuleMisClientes';
import ModuleEntregas from './Modules/ModuleEntregas';
import WidgetSolicitudes from './Widgets/WidgetSolicitudes';
import FunctionListados from './Functions/FunctionListados';

const ESTILOS_ANIMACION_NATIVA = `
    @keyframes slideUpFadeDashboard { 
        0% { opacity: 0; transform: translateY(15px); } 
        100% { opacity: 1; transform: translateY(0); } 
    }
    .animate-page-reveal { 
        opacity: 0; 
        animation: slideUpFadeDashboard 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
    }
`;

export default function AdminDashboard({ auth, ultimas_solicitudes = [] }) {
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

    const catalogoFunciones = [
        { id: 'func_listados', titulo: 'Cruce de Inventarios', permiso: 'listados.ver', componente: FunctionListados },
    ];

    const catalogoTarjetas = [
        { id: 'card_usuarios', titulo: 'Control de Usuarios', permiso: 'usuarios.gestionar', componente: ModuleUsuarios },
        { id: 'card_enlaces', titulo: 'Generar Accesos', permiso: 'usuarios.generar_permisos', componente: ModuleAccesos },
        { id: 'card_catalogos', titulo: 'Catálogos Centrales', permiso: 'catalogos.gestionar', componente: ModuleCatalogos },
        { id: 'card_clientes_bd', titulo: 'Base de Clientes', permiso: 'clientes.ver', componente: ModuleClientes },
        { id: 'card_solicitudes', titulo: 'Panel Solicitudes', permiso: 'solicitudes.ver_listado', componente: ModuleSolicitudes },
        { id: 'card_clientes', titulo: 'Mis Clientes', permiso: 'mis_clientes.gestionar', componente: ModuleMisClientes },
        { id: 'card_entregas', titulo: 'Área Logística', permiso: 'entregas.cotizar', componente: ModuleEntregas },
    ];

    const tarjetasHabilitadas = catalogoTarjetas.filter((tarjeta) => can(tarjeta.permiso));
    const tarjetasVisibles = tarjetasHabilitadas.filter((tarjeta) => !dashboardOcultosBD.includes(tarjeta.id));

    const funcionesHabilitadas = catalogoFunciones.filter((func) => can(func.permiso));
    const funcionesVisibles = funcionesHabilitadas.filter((func) => !dashboardOcultosBD.includes(func.id));

    const mostrarWidgetSolicitudes = can('configuracion.ver_auditoria') || can('solicitudes.ver_listado');

    const visiblePanelIds = useMemo(() => {
        const ids = [];
        if (tarjetasVisibles.length > 0) ids.push(PANEL_IDS.MODULOS);
        if (funcionesVisibles.length > 0) ids.push(PANEL_IDS.FUNCIONES);
        if (mostrarWidgetSolicitudes) ids.push(PANEL_IDS.SOLICITUDES);
        return ids;
    }, [tarjetasVisibles.length, funcionesVisibles.length, mostrarWidgetSolicitudes]);

    const defaultLayout = useMemo(
        () =>
            buildDefaultLayout({
                hasModulos: tarjetasVisibles.length > 0,
                hasFunciones: funcionesVisibles.length > 0,
                hasWidget: mostrarWidgetSolicitudes,
            }),
        [tarjetasVisibles.length, funcionesVisibles.length, mostrarWidgetSolicitudes]
    );

    const activeLayout = useMemo(
        () => resolveLayout(data.dashboard_layout, visiblePanelIds, defaultLayout),
        [data.dashboard_layout, visiblePanelIds, defaultLayout]
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

    const panelModulos = (
        <DashboardPanel
            title="Módulos de Sistema_"
            icon={LayoutDashboard}
            iconStyle={{ color: 'var(--color-primario)' }}
            headerActions={
                <>
                    <button
                        type="button"
                        onClick={() => !isMobile && setEditLayoutMode(true)}
                        disabled={isMobile}
                        title={isMobile ? 'Organiza el panel desde un equipo de escritorio' : undefined}
                        className={`flex items-center gap-2 px-3 py-1.5 rounded-xl theme-element border theme-border transition-colors text-[9px] font-black uppercase tracking-widest shadow-sm outline-none disabled:opacity-40 disabled:cursor-not-allowed ${editLayoutMode ? 'border-[var(--color-primario)] theme-text-main' : 'theme-text-muted hover:theme-text-main hover:border-[var(--color-primario)]'}`}
                    >
                        <Move className="w-3 h-3" /> Organizar
                    </button>
                    <button
                        type="button"
                        onClick={() => setShowConfig(true)}
                        className="flex items-center gap-2 px-3 py-1.5 rounded-xl theme-element border theme-border hover:border-[var(--color-primario)] transition-colors theme-text-muted hover:theme-text-main text-[9px] font-black uppercase tracking-widest shadow-sm outline-none"
                    >
                        <Settings2 className="w-3 h-3" /> Configurar
                    </button>
                </>
            }
        >
            <DashboardPanelCards
                emptyMessage='No hay módulos visibles. Haz clic en "Configurar" para añadir accesos a tu panel.'
            >
                {tarjetasVisibles.map((tarjeta) => {
                    const ComponenteModulo = tarjeta.componente;
                    return (
                        <DashboardCardSlot key={tarjeta.id}>
                            <ComponenteModulo />
                        </DashboardCardSlot>
                    );
                })}
            </DashboardPanelCards>
        </DashboardPanel>
    );

    const panelFunciones = (
        <DashboardPanel title="Funciones Operativas_" icon={Layers} iconClassName="text-indigo-500">
            <DashboardPanelCards>
                {funcionesVisibles.map((func) => {
                    const ComponenteFuncion = func.componente;
                    return (
                        <DashboardCardSlot key={func.id}>
                            <ComponenteFuncion />
                        </DashboardCardSlot>
                    );
                })}
            </DashboardPanelCards>
        </DashboardPanel>
    );

    const panels = {
        [PANEL_IDS.MODULOS]: panelModulos,
        [PANEL_IDS.FUNCIONES]: panelFunciones,
        [PANEL_IDS.SOLICITUDES]: <WidgetSolicitudes ultimas_solicitudes={ultimas_solicitudes} />,
    };

    const hayPaneles = activeLayout.length > 0;

    return (
        <AppLayout auth={auth}>
            <Head title="Dashboard | GELIANV" />
            <style>{ESTILOS_ANIMACION_NATIVA}</style>

            <div className="w-full max-w-[1400px] mx-auto p-4 md:p-6 lg:p-12 space-y-8 md:space-y-10 min-h-screen relative">
                <header className="theme-surface border-2 theme-border rounded-[2rem] md:rounded-[3rem] p-6 md:p-8 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10 animate-page-reveal">
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

                {isMobile && (
                    <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest text-center px-4 animate-page-reveal">
                        En móvil los bloques se apilan automáticamente. Tu disposición personalizada se aplica al abrir en escritorio.
                    </p>
                )}

                {editLayoutMode && (
                    <div
                        className="theme-surface border-2 rounded-2xl p-4 md:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 animate-page-reveal"
                        style={{ borderColor: 'var(--color-primario)' }}
                    >
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>
                                Modo organización activo_
                            </p>
                            <p className="text-xs font-bold theme-text-muted mt-1">
                                Arrastra los contenedores con &quot;Arrastrar&quot; y estíralos desde la esquina inferior derecha.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
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
                        <DashboardLayoutGrid
                            layout={activeLayout}
                            editMode={editLayoutMode}
                            onLayoutChange={onLayoutChange}
                            panels={panels}
                            isMobile={isMobile}
                            visiblePanelIds={visiblePanelIds}
                        />
                    </div>
                ) : (
                    <div className="theme-surface border-2 theme-border rounded-[2rem] p-12 text-center animate-page-reveal">
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
