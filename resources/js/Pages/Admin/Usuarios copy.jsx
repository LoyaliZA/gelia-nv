import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs';
import { 
    Users, Search, MoreVertical, 
    Shield, Mail, Calendar, UserX, 
    ChevronDown, Edit2, Key, BarChart3, X, Sparkles
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Usuarios({ auth }) {
    const [busqueda, setBusqueda] = useState('');
    const [filtroRol, setFiltroRol] = useState('Todos');
    const [menuAbierto, setMenuAbierto] = useState(null);
    const [modalEditar, setModalEditar] = useState(null);

    // Datos de prueba solicitados
    const usuariosSistema = [
        { id: 1, nombre: 'Mónica Camacho', email: 'monica@gelianv.com', rol: 'Administrador', status: 'Activo', fecha: '12 Feb 2026' },
        { id: 2, nombre: 'Rosa Rosales', email: 'Rosa@colab.com', rol: 'Vendedor', status: 'Activo', fecha: '20 Mar 2026' },
        { id: 3, nombre: 'Queso Badota', email: 'Queso@auxiliar.com', rol: 'Auxiliar', status: 'Suspendido', fecha: '05 Abr 2026' },
    ];

    const { data, setData, post, processing } = useForm({
        rol: '',
        status: ''
    });

    useEffect(() => {
        animate('.fade-up', {
            translateY: [15, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 100
        });
    }, []);

    const abrirEdicion = (user) => {
        setMenuAbierto(null);
        setModalEditar(user);
        setData({
            rol: user.rol,
            status: user.status
        });
    };

    const obtenerBadgeRol = (rol) => {
        const estilos = {
            'Administrador': 'bg-pink-500/10 text-pink-500',
            'Vendedor': 'bg-blue-500/10 text-blue-500',
            'Auxiliar': 'bg-purple-500/10 text-purple-500',
            'Encargado de TAGS': 'bg-emerald-500/10 text-emerald-500'
        };
        return estilos[rol] || 'bg-zinc-500/10 text-zinc-500';
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de Usuarios | GELIANV" />

            {/* --- MODAL DE ACCIONES ADMINISTRATIVAS --- */}
            {modalEditar && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center theme-overlay p-4 transition-all">
                    <div className="theme-surface border-2 theme-border rounded-[3rem] p-8 md:p-10 shadow-2xl max-w-lg w-full relative tab-content m-auto">
                        
                        <button onClick={() => setModalEditar(null)} className="absolute top-6 right-6 p-3 theme-element theme-hover-bg theme-text-muted hover:text-pink-500 rounded-2xl transition-all">
                            <X className="w-5 h-5" />
                        </button>

                        <div className="space-y-8">
                            <header className="flex items-center gap-4">
                                <div className="w-16 h-16 theme-element border-2 theme-border rounded-2xl flex items-center justify-center shadow-inner">
                                    <Shield className="w-8 h-8 text-pink-500" />
                                </div>
                                <div>
                                    <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter transition-colors">
                                        Gestionar <span className="text-pink-500">Acceso</span>
                                    </h2>
                                    <p className="text-xs font-bold theme-text-muted italic uppercase">{modalEditar.nombre}</p>
                                </div>
                            </header>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="space-y-3">
                                    <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Nivel de Rango_</label>
                                    <div className="relative group">
                                        <select 
                                            value={data.rol} 
                                            onChange={e => setData('rol', e.target.value)}
                                            className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none appearance-none cursor-pointer transition-all pr-12"
                                        >
                                            <option value="Administrador">ADMINISTRADOR</option>
                                            <option value="Vendedor">VENDEDOR</option>
                                            <option value="Auxiliar">AUXILIAR</option>
                                            <option value="Contador">CONTADOR</option>
                                        </select>
                                        <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted pointer-events-none group-hover:text-pink-500 transition-colors" />
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Estado de Cuenta_</label>
                                    <div className="relative group">
                                        <select 
                                            value={data.status} 
                                            onChange={e => setData('status', e.target.value)}
                                            className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none appearance-none cursor-pointer transition-all pr-12"
                                        >
                                            <option value="Activo">ACTIVO</option>
                                            <option value="Suspendido">SUSPENDIDO</option>
                                        </select>
                                        <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted pointer-events-none group-hover:text-pink-500 transition-colors" />
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 pt-4">
                                <button className="flex items-center justify-between p-4 theme-element border theme-border rounded-2xl hover:border-pink-500 transition-all group">
                                    <div className="flex items-center gap-3">
                                        <Key className="w-5 h-5 theme-text-muted group-hover:text-pink-500" />
                                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Resetear Contraseña</span>
                                    </div>
                                    <Sparkles className="w-4 h-4 text-pink-500 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </button>
                                
                                <button className="flex items-center justify-between p-4 theme-element border theme-border rounded-2xl hover:border-blue-500 transition-all group">
                                    <div className="flex items-center gap-3">
                                        <BarChart3 className="w-5 h-5 theme-text-muted group-hover:text-blue-500" />
                                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Ver Auditoría de Folios</span>
                                    </div>
                                    <Sparkles className="w-4 h-4 text-blue-500 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </button>
                            </div>

                            <div className="pt-6">
                                <button className="w-full py-5 bg-pink-600 hover:bg-pink-500 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all shadow-xl shadow-pink-500/20">
                                    Actualizar Credenciales
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-12">
                <header className="space-y-4 fade-up">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 bg-pink-500 rounded-full"></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] text-pink-600">Recursos Humanos</p>
                    </div>
                    <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight transition-colors">
                        CONTROL DE <span className="text-pink-500">USUARIOS</span>
                    </h1>
                </header>

                <div className="fade-up flex flex-col md:flex-row gap-4 items-center justify-between theme-surface border-2 theme-border p-4 rounded-[2rem] shadow-sm">
                    <div className="relative w-full md:w-96">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                        <input 
                            type="text" 
                            placeholder="Buscar usuario..." 
                            value={busqueda}
                            onChange={e => setBusqueda(e.target.value)}
                            className="w-full pl-12 pr-4 py-3 theme-element border theme-border rounded-xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all theme-placeholder text-xs"
                        />
                    </div>
                    
                    <div className="relative w-full md:w-auto">
                        <select 
                            value={filtroRol}
                            onChange={e => setFiltroRol(e.target.value)}
                            className="w-full pl-6 pr-12 py-3 theme-element border theme-border rounded-xl theme-text-main font-black uppercase tracking-widest text-[9px] appearance-none cursor-pointer outline-none focus:border-pink-500 transition-all"
                        >
                            <option value="Todos">TODOS LOS ROLES</option>
                            <option value="Administrador">ADMINS</option>
                            <option value="Vendedor">VENDEDORES</option>
                        </select>
                        <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 w-3.5 h-3.5 theme-text-muted pointer-events-none" />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {usuariosSistema.map((user) => (
                        <div key={user.id} className="fade-up theme-surface border-2 theme-border rounded-[2.5rem] p-8 hover:border-pink-500 transition-all group relative overflow-hidden">
                            <div className="absolute top-0 right-0 p-6 opacity-5 group-hover:opacity-10 transition-opacity">
                                <Users className="w-20 h-20 theme-text-main" />
                            </div>

                            <div className="relative z-10 space-y-6">
                                <div className="flex justify-between items-start">
                                    <div className="w-14 h-14 theme-element border theme-border rounded-2xl flex items-center justify-center shadow-inner">
                                        <Shield className="w-6 h-6 text-pink-500" />
                                    </div>
                                    
                                    <div className="relative">
                                        <button 
                                            onClick={() => setMenuAbierto(menuAbierto === user.id ? null : user.id)}
                                            className="p-2 theme-hover-bg rounded-xl transition-colors"
                                        >
                                            <MoreVertical className="w-4 h-4 theme-text-muted" />
                                        </button>
                                        
                                        {menuAbierto === user.id && (
                                            <>
                                                <div className="fixed inset-0 z-10" onClick={() => setMenuAbierto(null)}></div>
                                                <div className="absolute right-0 top-10 theme-surface border theme-border shadow-xl rounded-2xl p-2 z-20 w-36">
                                                    <button 
                                                        onClick={() => abrirEdicion(user)}
                                                        className="flex items-center gap-3 px-3 py-2.5 bg-amber-500/10 text-amber-500 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors w-full text-left"
                                                    >
                                                        <Edit2 className="w-3.5 h-3.5" /> Editar
                                                    </button>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <h3 className="text-xl font-black italic theme-text-main uppercase tracking-tighter">{user.nombre}</h3>
                                    <div className="flex items-center gap-2 mt-1">
                                        <Mail className="w-3 h-3 text-pink-500" />
                                        <span className="text-xs font-bold theme-text-muted">{user.email}</span>
                                    </div>
                                </div>

                                <div className="flex flex-wrap gap-2 pt-2">
                                    <span className={`px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest ${obtenerBadgeRol(user.rol)}`}>
                                        {user.rol}
                                    </span>
                                    <span className={`px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest ${user.status === 'Activo' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'}`}>
                                        {user.status}
                                    </span>
                                </div>

                                <div className="pt-4 border-t theme-border flex justify-between items-center">
                                    <div className="flex items-center gap-2">
                                        <Calendar className="w-3 h-3 theme-text-muted" />
                                        <span className="text-[10px] font-bold theme-text-muted">Ingreso: {user.fecha}</span>
                                    </div>
                                    <button className="text-[10px] font-black uppercase tracking-widest text-red-500 hover:text-red-600 transition-colors flex items-center gap-1">
                                        <UserX className="w-3 h-3" /> Suspender
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <style>{`
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #f4f4f5; }
                .theme-hover-bg:hover { background-color: #f4f4f5; }
                .theme-placeholder::placeholder { color: #a1a1aa; }
                .theme-overlay { background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
                
                select { -webkit-appearance: none; -moz-appearance: none; appearance: none; }

                .dark .theme-surface { background-color: #141414; border-color: #2A2A2A; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #2A2A2A; }
                .dark .theme-hover-bg:hover { background-color: #2A2A2A; }
                .dark .theme-placeholder::placeholder { color: #52525b; }
                .dark .theme-overlay { background-color: rgba(0, 0, 0, 0.6); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
            `}</style>
        </AppLayout>
    );
}