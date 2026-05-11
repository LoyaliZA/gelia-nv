import React, { useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
// Importación modular correcta para evitar crashes
import { animate } from 'animejs/animation';
import { 
    LayoutDashboard, UserPlus, FolderTree, 
    Users, Activity, ShieldCheck, ArrowUpRight,
    Database, History, LineChart, FileText,
    CheckCircle2, AlertTriangle, Clock
} from 'lucide-react';
import AppLayout from '../js/Layouts/AppLayout';

export default function AdminDashboard({ auth, estadisticas = {} }) {
    
    const can = (permiso) => auth?.user?.permissions?.includes(permiso);

    useEffect(() => {
        animate('.fade-in', {
            opacity: [0, 1],
            translateY: [20, 0],
            delay: (el, i) => i * 100, // Función de retardo en cascada segura
            easing: 'easeOutExpo',
            duration: 800
        });
    }, []);

    const accesosDisponibles = [];

    // Modificamos la primera tarjeta para que use el Tema Principal dinámicamente
    if (can('gestionar_usuarios')) {
        accesosDisponibles.push({ 
            titulo: 'Control de Usuarios', 
            desc: 'Administrar roles y personal.', 
            icon: Users, 
            href: route('admin.usuarios'), 
            isPrimary: true // Bandera para usar var(--color-primario)
        });
        accesosDisponibles.push({ 
            titulo: 'Generar Accesos', 
            desc: 'Crear enlaces seguros.', 
            icon: UserPlus, 
            href: route('admin.enlaces'), 
            color: 'theme-text-main', 
            bgColor: 'theme-element', 
            hoverBg: 'hover:bg-[#1A1A1A] dark:hover:bg-white', 
            borderColor: 'theme-border' 
        });
    }
    if (can('configurar_comisiones')) {
        accesosDisponibles.push({ titulo: 'Catálogos Centrales', desc: 'Procesos y comisiones.', icon: FolderTree, href: route('admin.catalogos'), color: 'text-blue-500', bgColor: 'bg-blue-500/10', hoverBg: 'hover:bg-blue-500', borderColor: 'border-blue-500/20' });
    }
    if (can('cargar_clientes_masivo')) {
        accesosDisponibles.push({ titulo: 'Base de Clientes', desc: 'Sincronización Wizerp.', icon: Database, href: route('admin.clientes'), color: 'text-emerald-500', bgColor: 'bg-emerald-500/10', hoverBg: 'hover:bg-emerald-500', borderColor: 'border-emerald-500/20' });
    }
    if (can('ver_auditoria')) {
        accesosDisponibles.push({ titulo: 'Auditoría', desc: 'Rastreo de sistema.', icon: History, href: '#', color: 'text-amber-500', bgColor: 'bg-amber-500/10', hoverBg: 'hover:bg-amber-500', borderColor: 'border-amber-500/20' });
    }

    const actividadReciente = [
        { id: 1, usuario: 'Carmen V. (Vendedora)', accion: 'Creó la solicitud CLI-092', tiempo: 'Hace 5 min', tipo: 'nueva', icon: FileText, color: 'text-blue-500', bg: 'bg-blue-500/10' },
        { id: 2, usuario: 'Rosa M. (Encargada TAGS)', accion: 'Aprobó folio CLI-089', tiempo: 'Hace 12 min', tipo: 'aprobacion', icon: CheckCircle2, color: 'text-emerald-500', bg: 'bg-emerald-500/10' },
        { id: 3, usuario: 'Monica C. (Auxiliar)', accion: 'Reportó inconsistencia en CLI-091', tiempo: 'Hace 1 hora', tipo: 'alerta', icon: AlertTriangle, color: 'text-red-500', bg: 'bg-red-500/10' },
        { id: 4, usuario: 'Gabriel Admin', accion: 'Actualizó Tabulador 2026', tiempo: 'Hace 2 horas', tipo: 'sistema', icon: ShieldCheck, color: 'text-amber-500', bg: 'bg-amber-500/10' },
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="Admin Dashboard | GELIANV" />

            <div className="w-full max-w-[1400px] mx-auto p-4 md:p-6 lg:p-12 space-y-8 md:space-y-10 min-h-screen">
                
                {/* ENCABEZADO */}
                <header className="fade-in theme-surface border-2 theme-border rounded-[2rem] md:rounded-[3rem] p-6 md:p-8 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div className="space-y-4">
                        <div className="flex items-center space-x-3">
                            <span className="h-1.5 w-12 rounded-full transition-colors duration-300" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] transition-colors duration-300" style={{ color: 'var(--color-primario)' }}>
                                Protocolo de Mando Global
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
                            <p className="text-xs font-black text-emerald-500 italic uppercase leading-tight mt-0.5">Sistema Operativo</p>
                        </div>
                    </div>
                </header>

                {/* ESTADÍSTICAS SUPERIORES */}
                {can('ver_auditoria') && (
                    <div className="fade-in grid grid-cols-2 md:grid-cols-4 gap-6">
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
                            <p className="text-[10px] font-bold theme-text-muted mt-2 flex items-center gap-1">Sincronizado con Wizerp</p>
                        </div>
                    </div>
                )}

                {can('crear_solicitud') && (
                    <div className="fade-in grid grid-cols-2 gap-6">
                        <div className="theme-surface border-2 theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-sm flex flex-col justify-center transition-colors duration-300">
                            <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest mb-2">Mis Solicitudes Activas_</p>
                            <h3 className="text-3xl md:text-4xl font-black italic theme-text-main">{estadisticas?.mis_activas || 0}</h3>
                        </div>
                        <div className="theme-surface border-2 theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-sm flex flex-col justify-center transition-colors duration-300">
                            <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest mb-2">Pagos Pendientes por Confirmar_</p>
                            <h3 className="text-3xl md:text-4xl font-black italic text-amber-500">{estadisticas?.mis_pagos_pendientes || 0}</h3>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 xl:grid-cols-3 gap-8 md:gap-10">
                    
                    {/* SECCIÓN MÓDULOS: AHORA SE EXPANDE INTELIGENTEMENTE */}
                    <div className={`space-y-6 fade-in theme-surface border-2 theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-sm transition-colors duration-300 ${can('ver_auditoria') ? 'xl:col-span-2' : 'xl:col-span-3'}`}>
                        <div className="flex items-center gap-3">
                            <LayoutDashboard className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">Módulos de Administración_</h2>
                        </div>
                        
                        {accesosDisponibles.length > 0 ? (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                {accesosDisponibles.map((item, idx) => (
                                    <Link 
                                        key={idx} 
                                        href={item.href} 
                                        className="theme-element border-2 theme-border p-6 rounded-[1.5rem] shadow-sm transition-all group relative overflow-hidden flex flex-col hover:border-[var(--color-primario)] outline-none"
                                    >
                                        {item.isPrimary ? (
                                            <>
                                                <div className="w-12 h-12 rounded-2xl flex items-center justify-center mb-6 border transition-all duration-500 z-10 bg-transparent group-hover:bg-[var(--color-primario)]" style={{ borderColor: 'var(--color-primario)' }}>
                                                    <item.icon className="w-5 h-5 transition-colors duration-500 group-hover:text-white dark:group-hover:text-black" style={{ color: 'var(--color-primario)' }} />
                                                </div>
                                                <h3 className="text-lg font-black uppercase italic mb-2 tracking-tighter theme-text-main flex items-center gap-2 z-10">{item.titulo}</h3>
                                                <p className="text-[11px] font-bold theme-text-muted italic leading-relaxed z-10">{item.desc}</p>
                                                <div className="absolute -bottom-4 -right-4 opacity-[0.02] group-hover:opacity-[0.05] group-hover:scale-110 transition-all duration-700 pointer-events-none">
                                                    <item.icon className="w-32 h-32" style={{ color: 'var(--color-primario)' }} />
                                                </div>
                                            </>
                                        ) : (
                                            <>
                                                <div className={`w-12 h-12 ${item.bgColor} border ${item.borderColor} rounded-2xl flex items-center justify-center mb-6 group-${item.hoverBg} transition-colors duration-500 z-10`}>
                                                    <item.icon className={`w-5 h-5 ${item.color} group-hover:text-white dark:group-hover:text-black transition-colors duration-500`} />
                                                </div>
                                                <h3 className="text-lg font-black uppercase italic mb-2 tracking-tighter theme-text-main flex items-center gap-2 z-10">{item.titulo}</h3>
                                                <p className="text-[11px] font-bold theme-text-muted italic leading-relaxed z-10">{item.desc}</p>
                                                <div className="absolute -bottom-4 -right-4 opacity-[0.02] group-hover:opacity-[0.05] group-hover:scale-110 transition-all duration-700 pointer-events-none">
                                                    <item.icon className={`w-32 h-32 ${item.color}`} />
                                                </div>
                                            </>
                                        )}
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <div className="p-10 border-2 border-dashed theme-border rounded-[1.5rem] text-center theme-element">
                                <p className="text-xs font-bold theme-text-muted uppercase tracking-widest">
                                    Utiliza el panel lateral para acceder a tus módulos operativos (Ej. Solicitudes).
                                </p>
                            </div>
                        )}
                    </div>

                    {/* SECCIÓN AUDITORÍA */}
                    {can('ver_auditoria') && (
                        <div className="fade-in theme-surface border-2 theme-border rounded-[2rem] md:rounded-[2.5rem] p-6 md:p-8 shadow-sm flex flex-col gap-6 transition-colors duration-300 xl:col-span-1">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <History className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">Live Audit Trail_</h2>
                                </div>
                                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md border border-emerald-500/20">
                                    <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-ping"></span> Live
                                </span>
                            </div>
                            
                            <div className="space-y-6 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-zinc-200 dark:before:via-[#333] before:to-transparent">
                                {actividadReciente.map((log) => (
                                    <div key={log.id} className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                                        <div className={`flex items-center justify-center w-10 h-10 rounded-full border-4 theme-surface ${log.bg} shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 shadow-sm z-10 transition-colors duration-300`}>
                                            <log.icon className={`w-4 h-4 ${log.color}`} />
                                        </div>
                                        <div className="w-[calc(100%-3rem)] md:w-[calc(50%-2.5rem)] theme-element border-2 theme-border p-4 rounded-2xl shadow-sm transition-colors duration-300">
                                            <div className="flex items-center justify-between mb-1">
                                                <h4 className="font-black text-[10px] uppercase theme-text-main">{log.usuario}</h4>
                                                <time className="text-[9px] font-bold theme-text-muted italic flex items-center gap-1"><Clock className="w-3 h-3"/> {log.tiempo}</time>
                                            </div>
                                            <p className="text-xs font-bold theme-text-muted">{log.accion}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}