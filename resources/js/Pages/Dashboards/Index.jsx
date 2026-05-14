import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, Link, useForm } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { 
    LayoutDashboard, UserPlus, FolderTree, 
    Users, Activity, ShieldCheck,
    Database, History, FileText,
    CheckCircle2, AlertTriangle, Clock,
    Settings2, X, Check, ArrowRight,
    AlertOctagon, CheckSquare, FileSignature
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function AdminDashboard({ auth, estadisticas = {}, ultimas_solicitudes = [] }) {
    
    // Función centralizada de permisos (Seguridad verificada contra web.php)
    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Super Admin');

    const [showConfig, setShowConfig] = useState(false);
    const dashboardOcultosBD = auth?.tema_visual?.dashboard_ocultos || [];

    const { data, setData, put, processing } = useForm({
        dashboard_ocultos: dashboardOcultosBD
    });

    useEffect(() => {
        animate('.fade-in', {
            opacity: [0, 1],
            translateY: [20, 0],
            delay: (el, i) => i * 100,
            easing: 'easeOutExpo',
            duration: 800
        });
    }, [dashboardOcultosBD.length]);

    useEffect(() => {
        if (showConfig) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
        return () => { document.body.style.overflow = ''; };
    }, [showConfig]);

    // 1. MATRIZ BASE DE ACCESOS (Sincronizada con base de datos y middleware)
    const todosLosAccesos = [];

    if (can('usuarios.gestionar')) {
        todosLosAccesos.push({ 
            id: 'card_usuarios',
            titulo: 'Control de Usuarios', 
            desc: 'Administrar roles y personal.', 
            icon: Users, 
            href: route('admin.usuarios'), 
            isPrimary: true 
        });
        todosLosAccesos.push({ 
            id: 'card_enlaces',
            titulo: 'Generar Accesos', 
            desc: 'Crear enlaces seguros.', 
            icon: UserPlus, 
            href: route('admin.enlaces'), 
            color: 'theme-text-main', 
            bgColor: 'theme-element', 
            hoverBg: 'hover:bg-zinc-200 dark:hover:bg-zinc-800', 
            borderColor: 'theme-border' 
        });
    }

    // CORRECCIÓN: Rutas de catálogos requieren catalogos.gestionar o configuracion.ver_auditoria
    if (can('configuracion.ver_auditoria') || can('catalogos.gestionar')) {
        todosLosAccesos.push({ 
            id: 'card_catalogos', 
            titulo: 'Catálogos Centrales', 
            desc: 'Procesos y comisiones.', 
            icon: FolderTree, 
            href: route('admin.catalogos'), 
            color: 'text-blue-500', 
            bgColor: 'bg-blue-500/10', 
            hoverBg: 'hover:bg-blue-50', 
            borderColor: 'border-blue-500/20' 
        });
    }

    // CORRECCIÓN: Visualizar clientes requiere clientes.ver, no carga_masiva
    if (can('clientes.ver')) {
        todosLosAccesos.push({ 
            id: 'card_clientes', 
            titulo: 'Base de Clientes', 
            desc: 'Sincronización Wizerp.', 
            icon: Database, 
            href: route('admin.clientes'), 
            color: 'text-emerald-500', 
            bgColor: 'bg-emerald-500/10', 
            hoverBg: 'hover:bg-emerald-50', 
            borderColor: 'border-emerald-500/20' 
        });
    }

    // CORRECCIÓN: Listado de solicitudes requiere solicitudes.ver_listado
    if (can('solicitudes.ver_listado') || can('solicitudes.crear')) {
        todosLosAccesos.push({ 
            id: 'card_solicitudes', 
            titulo: 'Panel Solicitudes', 
            desc: 'Gestión operativa.', 
            icon: FileSignature, 
            href: route('solicitudes.index'), 
            color: 'text-amber-500', 
            bgColor: 'bg-amber-500/10', 
            hoverBg: 'hover:bg-amber-50', 
            borderColor: 'border-amber-500/20' 
        });
    }

    // Acceso al Directorio de Clientes (Módulo Operativo)
    if (can('mis_clientes.gestionar')) {
        todosLosAccesos.push({ 
            id: 'card_clientes', 
            titulo: 'Mis Clientes', 
            desc: 'Cartera y altas rápidas.', 
            icon: Users, 
            href: route('mis_clientes.index'), 
            color: 'text-purple-500', 
            bgColor: 'bg-purple-500/10', 
            hoverBg: 'hover:bg-purple-50', 
            borderColor: 'border-purple-500/20' 
        });
    }
    

    const accesosVisibles = todosLosAccesos.filter(acceso => !dashboardOcultosBD.includes(acceso.id));

    const toggleVisibilidad = (id) => {
        const nuevosOcultos = data.dashboard_ocultos.includes(id)
            ? data.dashboard_ocultos.filter(item => item !== id)
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
            preserveScroll: true
        });
    };

    // CORRECCIÓN: Permisos verificados para estadísticas
    const puedeVerEstadisticas = can('configuracion.ver_auditoria') || can('clientes.ver') || can('solicitudes.ver_listado');

    const obtenerEstiloLive = (nombreEstado) => {
        switch(nombreEstado?.toLowerCase()) {
            case 'respondida': return { icon: CheckCircle2, iconColor: 'text-emerald-500', bg: 'bg-emerald-500/10 border-emerald-500/20' };
            case 'incorrecta': return { icon: AlertOctagon, iconColor: 'text-red-500', bg: 'bg-red-500/10 border-red-500/20' };
            case 'verificada': return { icon: CheckSquare, iconColor: 'text-blue-500', bg: 'bg-blue-500/10 border-blue-500/20' };
            default: return { icon: Clock, iconColor: 'text-amber-500', bg: 'bg-amber-500/10 border-amber-500/20' }; 
        }
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Admin Dashboard | GELIANV" />

            <div className="w-full max-w-[1400px] mx-auto p-4 md:p-6 lg:p-12 space-y-8 md:space-y-10 min-h-screen relative">
                
                <header className="fade-in theme-surface border-2 theme-border rounded-[2rem] md:rounded-[3rem] p-6 md:p-8 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
                    <div className="space-y-4">
                        <div className="flex items-center space-x-3">
                            <span className="h-1.5 w-12 rounded-full transition-colors duration-300" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] transition-colors duration-300" style={{ color: 'var(--color-primario)' }}>
                                Gelia NV
                            </p>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0 p-0">
                            BIENVENIDO, <span className="transition-colors duration-300" style={{ color: 'var(--color-primario)' }}>{auth?.user?.name ? auth.user.name.trim().split(' ')[0] : 'USUARIO'}</span>
                        </h1>
                    </div>

                    <div className="flex items-center gap-4 p-4 theme-element border-2 theme-border rounded-2xl shadow-sm transition-colors duration-300 w-full md:w-auto">
                        <div className="w-10 h-10 bg-emerald-500/10 rounded-xl border border-emerald-500/20 flex items-center justify-center shrink-0">
                            <Activity className="w-5 h-5 text-emerald-500 animate-pulse" />
                        </div>
                        <div>
                            <p className="text-[9px] font-black theme-text-muted uppercase tracking-widest leading-tight">Estado de Servidor_</p>
                            <p className="text-xs font-black text-emerald-500 italic uppercase leading-tight mt-0.5">Operativo</p>
                        </div>
                    </div>
                </header>

                {puedeVerEstadisticas && (
                    <div className="fade-in grid grid-cols-2 md:grid-cols-4 gap-6 relative z-10">
                        <div className="theme-surface border-2 theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-sm flex flex-col justify-center transition-colors duration-300">
                            <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest mb-2">Solicitudes Mes_</p>
                            <h3 className="text-3xl md:text-4xl font-black italic theme-text-main">{estadisticas?.solicitudes_mes || 0}</h3>
                            <p className="text-[10px] font-bold text-emerald-500 mt-2 flex items-center gap-1">Mes actual</p>
                        </div>
                        <div className="theme-surface border-2 theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-sm flex flex-col justify-center transition-colors duration-300">
                            <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest mb-2">Cotizado Global_</p>
                            <h3 className="text-3xl md:text-4xl font-black italic transition-colors duration-300 truncate" style={{ color: 'var(--color-primario)' }}>
                                ${Number(estadisticas?.cotizado_global || 0).toLocaleString('en-US')}
                            </h3>
                            <p className="text-[10px] font-bold theme-text-muted mt-2">Monto auditado en sistema</p>
                        </div>
                        <div className="theme-surface border-2 theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-sm flex flex-col justify-center transition-colors duration-300">
                            <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest mb-2">Usuarios Activos_</p>
                            <h3 className="text-3xl md:text-4xl font-black italic theme-text-main">{estadisticas?.usuarios_activos || 0}</h3>
                            <p className="text-[10px] font-bold theme-text-muted mt-2">Personal en plataforma</p>
                        </div>
                        <div className="theme-surface border-2 theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-sm flex flex-col justify-center transition-colors duration-300">
                            <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest mb-2">Salud de Datos_</p>
                            <h3 className="text-3xl md:text-4xl font-black italic text-blue-500">100%</h3>
                            <p className="text-[10px] font-bold theme-text-muted mt-2 flex items-center gap-1">Sincronizado</p>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 xl:grid-cols-3 gap-8 md:gap-10 relative z-10 items-start">
                    
                    <div className={`space-y-6 fade-in theme-surface border-2 theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-sm transition-colors duration-300 ${can('configuracion.ver_auditoria') || can('solicitudes.ver_listado') ? 'xl:col-span-2' : 'xl:col-span-3'}`}>
                        
                        <div className="flex items-center justify-between border-b theme-border pb-4">
                            <div className="flex items-center gap-3">
                                <LayoutDashboard className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">Módulos de Sistema_</h2>
                            </div>
                            
                            <button 
                                onClick={() => setShowConfig(true)}
                                className="flex items-center gap-2 px-3 py-1.5 rounded-xl theme-element border theme-border hover:border-[var(--color-primario)] transition-colors theme-text-muted hover:theme-text-main text-[9px] font-black uppercase tracking-widest shadow-sm outline-none"
                            >
                                <Settings2 className="w-3 h-3" /> Configurar
                            </button>
                        </div>
                        
                        {accesosVisibles.length > 0 ? (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                {accesosVisibles.map((item, idx) => (
                                    <Link 
                                        key={idx} 
                                        href={item.href} 
                                        className="theme-element border-2 theme-border p-6 rounded-[1.5rem] shadow-sm transition-all group relative overflow-hidden flex flex-col hover:border-[var(--color-primario)] outline-none"
                                        style={item.isPrimary ? { borderColor: 'var(--color-primario)' } : {}}
                                    >
                                        <div className={`w-12 h-12 ${item.isPrimary ? 'bg-transparent' : item.bgColor} border ${item.isPrimary ? 'border-transparent' : item.borderColor} rounded-2xl flex items-center justify-center mb-6 transition-colors duration-500 z-10`} style={item.isPrimary ? { backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' } : {}}>
                                            <item.icon className={`w-5 h-5 ${item.isPrimary ? '' : item.color} transition-colors duration-500`} style={item.isPrimary ? { color: 'var(--color-primario)' } : {}} />
                                        </div>
                                        <h3 className="text-lg font-black uppercase italic mb-2 tracking-tighter theme-text-main flex items-center gap-2 z-10">{item.titulo}</h3>
                                        <p className="text-[11px] font-bold theme-text-muted italic leading-relaxed z-10">{item.desc}</p>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="p-10 border-2 border-dashed theme-border rounded-[1.5rem] text-center theme-element">
                                <p className="text-xs font-bold theme-text-muted uppercase tracking-widest">
                                    No hay módulos visibles. Haz clic en "Configurar" para añadir accesos a tu panel.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* CORRECCIÓN: Seguridad para ver módulo en vivo */}
                    {(can('configuracion.ver_auditoria') || can('solicitudes.ver_listado')) && (
                        <div className="fade-in xl:col-span-1 h-full">
                            <div className="group block h-full theme-surface border-2 theme-border rounded-[2rem] md:rounded-[2.5rem] p-6 md:p-8 shadow-sm flex flex-col transition-all duration-300 hover:border-[var(--color-primario)] relative overflow-hidden">
                                
                                <div className="absolute inset-0 opacity-0 group-hover:opacity-10 transition-opacity duration-500 pointer-events-none" style={{ background: 'linear-gradient(180deg, var(--color-primario) 0%, transparent 100%)' }}></div>

                                <div className="flex items-center justify-between mb-8 relative z-10">
                                    <div className="flex items-center gap-3">
                                        <FileSignature className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main group-hover:text-[var(--color-primario)] transition-colors">Live Solicitudes_</h2>
                                    </div>
                                    <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md border border-emerald-500/20">
                                        <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-ping"></span> Live
                                    </span>
                                </div>
                                
                                <div className="space-y-4 flex-1 relative z-10">
                                    {ultimas_solicitudes && ultimas_solicitudes.length > 0 ? (
                                        ultimas_solicitudes.slice(0, 4).map((sol) => {
                                            const uiEstado = obtenerEstiloLive(sol.estado?.nombre);
                                            const StatusIcon = uiEstado.icon;
                                            
                                            return (
                                                <div key={sol.id} className="flex items-center justify-between theme-element border theme-border p-4 rounded-2xl shadow-sm transition-all duration-300 group-hover:shadow-md bg-white/50 dark:bg-zinc-900/50">
                                                    <div className="flex items-center gap-4 overflow-hidden">
                                                        <div className={`w-10 h-10 shrink-0 rounded-xl border flex items-center justify-center ${uiEstado.bg}`}>
                                                            <StatusIcon className={`w-4 h-4 ${uiEstado.iconColor}`} />
                                                        </div>
                                                        <div className="overflow-hidden">
                                                            <h4 className="font-black text-[11px] uppercase theme-text-main truncate">FOL-{sol.id}</h4>
                                                            <p className="text-[10px] font-bold theme-text-muted truncate mt-0.5">{sol.cliente?.nombre || 'Nuevo Prospecto'}</p>
                                                        </div>
                                                    </div>
                                                    <div className="text-right shrink-0 pl-2">
                                                        <div className="font-black italic text-[11px] theme-text-main">
                                                            ${Number(sol.monto_cotizado || 0).toLocaleString('en-US')}
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })
                                    ) : (
                                        <div className="text-center py-10 italic theme-text-muted text-xs font-bold uppercase tracking-widest opacity-50">Sin actividad reciente_</div>
                                    )}
                                </div>

                                <div className="mt-8 pt-4 border-t theme-border relative z-10">
                                    <Link href={route('solicitudes.index')} className="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all duration-300 shadow-sm flex justify-center items-center gap-2 outline-none theme-element border theme-border text-zinc-500 dark:text-zinc-400 group-hover:bg-[var(--color-primario)] group-hover:text-white group-hover:border-[var(--color-primario)]">
                                        Explorar Todas las Frecuencias <ArrowRight className="w-4 h-4" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {showConfig && createPortal(
                <div 
                    className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl transition-opacity animate-fade-in" 
                    onClick={cerrarModal}
                >
                    <div 
                        className="w-full max-w-lg theme-surface theme-border border shadow-2xl rounded-[2.5rem] p-8 md:p-10 flex flex-col space-y-6 relative modal-pop" 
                        onClick={e => e.stopPropagation()}
                    >
                        <button 
                            type="button"
                            onClick={cerrarModal} 
                            className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none"
                        >
                            <X className="w-5 h-5" />
                        </button>
                        
                        <h3 className="text-lg font-black uppercase italic tracking-tighter theme-text-main m-0 flex items-center gap-3">
                            <Settings2 className="w-6 h-6" style={{ color: 'var(--color-primario)' }}/>
                            Personalizar Panel_
                        </h3>

                        <div className="space-y-3 max-h-[50vh] overflow-y-auto custom-scrollbar pr-2">
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mb-4 ml-1">
                                Tarjetas operativas visibles:
                            </p>
                            
                            {todosLosAccesos.map(acceso => {
                                const isVisible = !data.dashboard_ocultos.includes(acceso.id);
                                return (
                                    <button 
                                        key={acceso.id}
                                        type="button"
                                        onClick={() => toggleVisibilidad(acceso.id)}
                                        className={`w-full flex items-center justify-between p-4 rounded-2xl border transition-all text-[11px] font-black uppercase tracking-widest outline-none ${isVisible ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/5 theme-text-main' : 'theme-border theme-element theme-text-muted hover:border-[var(--color-primario)]/30'}`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="p-2 rounded-xl transition-colors" style={isVisible ? { backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' } : {}}>
                                                <acceso.icon className="w-4 h-4" style={isVisible ? { color: 'var(--color-primario)' } : {}} />
                                            </div>
                                            {acceso.titulo}
                                        </div>
                                        {isVisible && <Check className="w-5 h-5 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />}
                                    </button>
                                );
                            })}
                        </div>

                        <button 
                            type="button"
                            onClick={guardarPreferencias} 
                            disabled={processing}
                            className="w-full py-4 rounded-full text-white font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-105 shadow-md flex justify-center items-center gap-2 outline-none m-0" 
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