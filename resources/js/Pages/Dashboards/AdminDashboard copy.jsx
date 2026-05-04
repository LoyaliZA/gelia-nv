import React, { useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import { animate } from 'animejs';
import { 
    LayoutDashboard, UserPlus, FolderTree, 
    Users, Activity, ShieldCheck, ArrowUpRight 
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function AdminDashboard({ auth, estadísticas = { usuarios: 3, enlaces: 12 } }) {
    
    useEffect(() => {
        animate('.fade-in', {
            opacity: [0, 1],
            translateY: [20, 0],
            delay: (el, i) => i * 150,
            easing: 'easeOutExpo',
            duration: 800
        });
    }, []);

    const accesosRapidos = [
        { 
            titulo: 'Generar Accesos', 
            desc: 'Crear nuevos enlaces de registro seguros.', 
            icon: UserPlus, 
            href: route('admin.enlaces'),
            color: 'text-pink-500'
        },
        { 
            titulo: 'Gestionar Catálogos', 
            desc: 'Configurar procesos y tabuladores.', 
            icon: FolderTree, 
            href: route('admin.catalogos'),
            color: 'text-blue-500'
        },
        { 
            titulo: 'Control de Usuarios', 
            desc: 'Administrar personal y permisos.', 
            icon: Users, 
            href: route('admin.usuarios'),
            color: 'text-emerald-500'
        }
    ];

    return (
        <AppLayout auth={auth}>
            <Head title="Admin Dashboard | GELIANV" />

            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-12">
                
                {/* SALUDO E INDICADOR DE ESTADO */}
                <header className="fade-in space-y-4">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 bg-pink-500 rounded-full"></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] text-pink-600">Protocolo de Mando</p>
                    </div>
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight">
                            BIENVENIDO, <span className="text-pink-500">{auth?.user?.name ? auth.user.name.split(' ')[0] : 'ADMIN'}</span>
                        </h1>
                        <div className="flex items-center gap-4 p-4 theme-surface border theme-border rounded-2xl shadow-sm">
                            <div className="w-10 h-10 bg-emerald-500/10 rounded-xl flex items-center justify-center">
                                <Activity className="w-5 h-5 text-emerald-500 animate-pulse" />
                            </div>
                            <div>
                                <p className="text-[9px] font-black theme-text-muted uppercase tracking-widest">Estado de Núcleo_</p>
                                <p className="text-xs font-black theme-text-main italic uppercase text-emerald-500">Sistema Operativo</p>
                            </div>
                        </div>
                    </div>
                </header>

                {/* ACCESOS RÁPIDOS */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                    {accesosRapidos.map((item, idx) => (
                        <Link 
                            key={idx} 
                            href={item.href}
                            className="fade-in theme-surface border-2 theme-border p-10 rounded-[3rem] shadow-sm hover:border-pink-500 transition-all group relative overflow-hidden"
                        >
                            <div className="w-14 h-14 theme-element border theme-border rounded-2xl flex items-center justify-center mb-8 shadow-inner group-hover:bg-zinc-900 dark:group-hover:bg-white transition-colors duration-500">
                                <item.icon className={`w-7 h-7 ${item.color} group-hover:text-white dark:group-hover:text-black transition-colors`} />
                            </div>
                            <h3 className="text-2xl font-black uppercase italic mb-2 tracking-tighter theme-text-main flex items-center gap-2">
                                {item.titulo} <ArrowUpRight className="w-5 h-5 opacity-0 group-hover:opacity-100 transition-opacity" />
                            </h3>
                            <p className="text-sm font-bold theme-text-muted italic leading-relaxed">{item.desc}</p>
                            
                            <div className="absolute -bottom-6 -right-6 opacity-5 group-hover:opacity-10 transition-opacity">
                                <item.icon className="w-32 h-32 theme-text-main" />
                            </div>
                        </Link>
                    ))}
                </div>

                {/* RESUMEN DE INFRAESTRUCTURA */}
                <div className="fade-in theme-surface border-2 theme-border rounded-[3.5rem] p-8 md:p-12 relative overflow-hidden">
                    <div className="absolute top-0 right-0 p-12 opacity-5 pointer-events-none">
                        <ShieldCheck className="w-64 h-64 theme-text-main" />
                    </div>
                    
                    <div className="relative z-10 space-y-8">
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-widest flex items-center">
                            <LayoutDashboard className="w-6 h-6 mr-3 text-pink-500" /> Resumen de Infraestructura
                        </h2>

                        <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
                            <div className="space-y-1">
                                <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest">Colaboradores_</p>
                                <p className="text-3xl font-black italic theme-text-main">{estadísticas.usuarios}</p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest">Tokens Activos_</p>
                                <p className="text-3xl font-black italic theme-text-main">{estadísticas.enlaces}</p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest">Nivel de Carga_</p>
                                <p className="text-3xl font-black italic text-pink-500">Normal</p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest">Seguridad_</p>
                                <p className="text-3xl font-black italic theme-text-main">TLS 1.3</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <style>{`
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #f4f4f5; }
                
                .dark .theme-surface { background-color: #141414; border-color: #2A2A2A; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #2A2A2A; }
            `}</style>
        </AppLayout>
    );
}