import React, { useEffect, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { 
    LayoutDashboard, UserPlus, FolderTree, 
    Users, Activity, ShieldCheck,
    Database, History, FileText,
    CheckCircle2, AlertTriangle, Clock,
    Settings2, X, Check
} from 'lucide-react';
import AppLayout from '../js/Layouts/AppLayout';

export default function AdminDashboard({ auth, estadisticas = {} }) {
    
    // Función centralizada de permisos (Seguridad)
    const can = (permiso) => auth?.user?.permissions?.includes(permiso);

    // Estado del modal de configuración
    const [showConfig, setShowConfig] = useState(false);

    // Formulario Inertia para guardar las preferencias dinámicas (Personalización)
    const { data, setData, put, processing } = useForm({
        ocultos: auth?.dashboard_prefs?.ocultos || []
    });

    // Motor de Animación
    useEffect(() => {
        animate('.fade-in', {
            opacity: [0, 1],
            translateY: [20, 0],
            delay: (el, i) => i * 100,
            easing: 'easeOutExpo',
            duration: 800
        });
    }, [data.ocultos]);

    // 1. MATRIZ BASE: Evaluamos con los NUEVOS nombres del RolesSeeder
    const todosLosAccesos = [];

    // Cambiado: de 'gestionar_usuarios' a 'usuarios.gestionar'
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
            hoverBg: 'hover:bg-[#1A1A1A] dark:hover:bg-white', 
            borderColor: 'theme-border' 
        });
    }

    // Cambiado: de 'configurar_comisiones' a 'configuracion.ver_auditoria' (o el permiso que hayas definido para catálogos)
    if (can('configuracion.ver_auditoria') || can('usuarios.gestionar')) {
        todosLosAccesos.push({ 
            id: 'card_catalogos', 
            titulo: 'Catálogos Centrales', 
            desc: 'Procesos y comisiones.', 
            icon: FolderTree, 
            href: route('admin.catalogos'), 
            color: 'text-blue-500', 
            bgColor: 'bg-blue-500/10', 
            hoverBg: 'hover:bg-blue-500', 
            borderColor: 'border-blue-500/20' 
        });
    }

    // Cambiado: de 'cargar_clientes_masivo' a 'clientes.carga_masiva'
    if (can('clientes.carga_masiva')) {
        todosLosAccesos.push({ 
            id: 'card_clientes', 
            titulo: 'Base de Clientes', 
            desc: 'Sincronización Wizerp.', 
            icon: Database, 
            href: route('admin.clientes'), 
            color: 'text-emerald-500', 
            bgColor: 'bg-emerald-500/10', 
            hoverBg: 'hover:bg-emerald-500', 
            borderColor: 'border-emerald-500/20' 
        });
    }

    // Cambiado: de 'ver_auditoria' a 'configuracion.ver_auditoria'
    if (can('configuracion.ver_auditoria')) {
        todosLosAccesos.push({ 
            id: 'card_auditoria', 
            titulo: 'Auditoría', 
            desc: 'Rastreo de sistema.', 
            icon: History, 
            href: '#', 
            color: 'text-amber-500', 
            bgColor: 'bg-amber-500/10', 
            hoverBg: 'hover:bg-amber-500', 
            borderColor: 'border-amber-500/20' 
        });
    }

    // 2. MATRIZ VISUAL: Filtramos los ocultos
    const accesosVisibles = todosLosAccesos.filter(acceso => !data.ocultos.includes(acceso.id));

    const toggleVisibilidad = (id) => {
        const nuevosOcultos = data.ocultos.includes(id)
            ? data.ocultos.filter(item => item !== id)
            : [...data.ocultos, id];
        setData('ocultos', nuevosOcultos);
    };

    const guardarPreferencias = () => {
        put(route('dashboard.preferencias'), {
            onSuccess: () => setShowConfig(false),
            preserveScroll: true
        });
    };

    const actividadReciente = [
        { id: 1, usuario: 'Carmen V.', accion: 'Creó la solicitud CLI-092', tiempo: 'Hace 5 min', icon: FileText, color: 'text-blue-500', bg: 'bg-blue-500/10' },
        { id: 2, usuario: 'Rosa M.', accion: 'Aprobó folio CLI-089', tiempo: 'Hace 12 min', icon: CheckCircle2, color: 'text-emerald-500', bg: 'bg-emerald-500/10' },
        { id: 3, usuario: 'Monica C.', accion: 'Reportó inconsistencia', tiempo: 'Hace 1 hora', icon: AlertTriangle, color: 'text-red-500', bg: 'bg-red-500/10' },
        { id: 4, usuario: 'Gabriel Admin', accion: 'Actualizó Tabulador', tiempo: 'Hace 2 horas', icon: ShieldCheck, color: 'text-amber-500', bg: 'bg-amber-500/10' },
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
                {can('configuracion.ver_auditoria') && (
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

                <div className="grid grid-cols-1 xl:grid-cols-3 gap-8 md:gap-10">
                    
                    {/* SECCIÓN MÓDULOS */}
                    <div className={`space-y-6 fade-in theme-surface border-2 theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] shadow-sm transition-colors duration-300 ${can('configuracion.ver_auditoria') ? 'xl:col-span-2' : 'xl:col-span-3'}`}>
                        
                        <div className="flex items-center justify-between border-b theme-border pb-4">
                            <div className="flex items-center gap-3">
                                <LayoutDashboard className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">Módulos de Administración_</h2>
                            </div>
                            
                            <button 
                                onClick={() => setShowConfig(true)}
                                className="flex items-center gap-2 px-3 py-1.5 rounded-xl theme-element border theme-border hover:border-[var(--color-primario)] transition-colors theme-text-muted hover:theme-text-main text-[9px] font-black uppercase tracking-widest shadow-sm"
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
                                    >
                                        <div className={`w-12 h-12 ${item.isPrimary ? 'bg-transparent group-hover:bg-[var(--color-primario)]' : item.bgColor} border ${item.isPrimary ? '' : item.borderColor} rounded-2xl flex items-center justify-center mb-6 group-${item.hoverBg} transition-colors duration-500 z-10`} style={item.isPrimary ? { borderColor: 'var(--color-primario)' } : {}}>
                                            <item.icon className={`w-5 h-5 ${item.isPrimary ? '' : item.color} group-hover:text-white dark:group-hover:text-black transition-colors duration-500`} style={item.isPrimary ? { color: 'var(--color-primario)' } : {}} />
                                        </div>
                                        <h3 className="text-lg font-black uppercase italic mb-2 tracking-tighter theme-text-main flex items-center gap-2 z-10">{item.titulo}</h3>
                                        <p className="text-[11px] font-bold theme-text-muted italic leading-relaxed z-10">{item.desc}</p>
                                        <div className="absolute -bottom-4 -right-4 opacity-[0.02] group-hover:opacity-[0.05] group-hover:scale-110 transition-all duration-700 pointer-events-none">
                                            <item.icon className="w-32 h-32" style={item.isPrimary ? { color: 'var(--color-primario)' } : { color: item.color ? item.color.replace('text-', '') : '' }} />
                                        </div>
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

                    {/* SECCIÓN AUDITORÍA */}
                    {can('configuracion.ver_auditoria') && (
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

            {/* MODAL PERSONALIZACIÓN PANEL */}
            {showConfig && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 fade-in">
                    <div className="w-full max-w-md theme-surface rounded-[2rem] border theme-border shadow-2xl overflow-hidden flex flex-col">
                        
                        <div className="p-6 border-b theme-border flex justify-between items-center bg-transparent shrink-0">
                            <h2 className="text-lg font-black uppercase italic theme-text-main flex items-center gap-3">
                                <Settings2 className="w-5 h-5" style={{ color: 'var(--color-primario)' }}/> 
                                Personalizar Panel
                            </h2>
                            <button onClick={() => setShowConfig(false)} className="theme-text-muted hover:theme-text-main transition-colors p-2 rounded-full hover:bg-black/5 dark:hover:bg-white/5">
                                <X className="w-5 h-5"/>
                            </button>
                        </div>

                        <div className="p-6 space-y-3 max-h-[60vh] overflow-y-auto custom-scrollbar">
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mb-4">
                                Configura las tarjetas operativas que deseas mantener visibles en tu entorno:
                            </p>
                            
                            {todosLosAccesos.map(acceso => {
                                const isVisible = !data.ocultos.includes(acceso.id);
                                return (
                                    <button 
                                        key={acceso.id}
                                        onClick={() => toggleVisibilidad(acceso.id)}
                                        className={`w-full flex items-center justify-between p-4 rounded-2xl border transition-all text-[11px] font-black uppercase tracking-widest ${isVisible ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/5 theme-text-main' : 'theme-border theme-element theme-text-muted'}`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <acceso.icon className="w-4 h-4" /> {acceso.titulo}
                                        </div>
                                        {isVisible && <Check className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />}
                                    </button>
                                );
                            })}
                        </div>

                        <div className="p-6 border-t theme-border bg-transparent shrink-0">
                            <button 
                                onClick={guardarPreferencias} 
                                disabled={processing}
                                className="w-full bg-black dark:bg-white text-white dark:text-black py-4 rounded-[1.5rem] text-[10px] font-black uppercase tracking-[0.2em] italic hover:scale-[1.02] transition-transform shadow-xl"
                            >
                                {processing ? 'Procesando...' : 'Aplicar Preferencias'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
            
        </AppLayout>
    );
}