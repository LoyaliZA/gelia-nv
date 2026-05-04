import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs';
import { 
    Users, UserPlus, Search, Edit3, Trash2, 
    ShieldCheck, X, Briefcase, Key
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Usuarios({ auth, usuarios = [] }) {
    const [busqueda, setBusqueda] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [usuarioEditando, setUsuarioEditando] = useState(null);

    // 1. SIMULACIÓN DE CATÁLOGOS
    const catalogoRoles = [
        { id: 2, name: 'Administrador' },
        { id: 3, name: 'Vendedor' },
        { id: 4, name: 'Encargado de TAGS' },
        { id: 5, name: 'Auxiliar' },
        { id: 6, name: 'Contador' }
    ];

    const catalogoPermisos = [
        { id: 'verificar_solicitud', label: 'Verificar Solicitudes' },
        { id: 'gestionar_tags', label: 'Ejecutar TAGS' },
        { id: 'confirmar_pago', label: 'Confirmar Pagos' },
        { id: 'cargar_clientes_masivo', label: 'Carga Masiva Wizerp' },
        { id: 'configurar_comisiones', label: 'Ajustar Tabuladores' }
    ];

    // 2. DATOS SIMULADOS
    const datosSimulados = usuarios.length > 0 ? usuarios : [
        {
            id: 1,
            name: 'Monica Camacho',
            email: 'admin@moondev.com',
            telefono: '9931234567',
            roles: [5, 6],
            permisos: ['cargar_clientes_masivo', 'configurar_comisiones']
        },
        {
            id: 2,
            name: 'Gabriel Admin',
            email: 'realloyal1a@gmail.com',
            telefono: '0000000000',
            roles: [2],
            permisos: ['confirmar_pago']
        }
    ];

    const { data, setData, post, put, processing, reset } = useForm({
        name: '',
        email: '',
        telefono: '',
        roles: [],
        permisos: [],
    });

    useEffect(() => {
        animate('.fade-up', {
            translateY: [20, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 700,
            delay: (el, i) => i * 100
        });
    }, [busqueda]);

    const openModal = (usuario = null) => {
        if (usuario) {
            setUsuarioEditando(usuario);
            setData({
                name: usuario.name,
                email: usuario.email,
                telefono: usuario.telefono || '',
                roles: usuario.roles || [],
                permisos: usuario.permisos || [],
            });
        } else {
            setUsuarioEditando(null);
            reset();
        }
        setShowModal(true);
    };

    const handleArrayChange = (campo, id) => {
        const nuevoArray = data[campo].includes(id)
            ? data[campo].filter(item => item !== id)
            : [...data[campo], id];
        setData(campo, nuevoArray);
    };

    const submit = (e) => {
        e.preventDefault();
        setShowModal(false);
    };

    const usuariosFiltrados = datosSimulados.filter(u => 
        u.name.toLowerCase().includes(busqueda.toLowerCase()) || 
        u.email.toLowerCase().includes(busqueda.toLowerCase())
    );

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de Usuarios | GELIANV" />

            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-12">
                <header className="fade-up space-y-4 flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <div className="flex items-center space-x-3">
                            <span className="h-1.5 w-12 bg-pink-500 rounded-full"></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] text-pink-600">Matriz de Acceso</p>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase text-zinc-900 leading-tight">
                            GESTIÓN DE <span className="text-pink-500">USUARIOS</span>
                        </h1>
                    </div>
                    <button onClick={() => openModal()} className="flex items-center gap-3 px-8 py-4 bg-zinc-900 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] shadow-xl hover:scale-105 transition-all">
                        <UserPlus className="w-4 h-4" /> Registrar Nuevo_
                    </button>
                </header>

                <div className="fade-up relative w-full md:w-1/3">
                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-400" />
                    <input type="text" placeholder="Buscar..." value={busqueda} onChange={e => setBusqueda(e.target.value)} className="w-full pl-12 pr-4 py-4 bg-white border border-zinc-200 rounded-2xl text-zinc-900 font-bold focus:border-pink-500 focus:ring-2 focus:ring-pink-500/20 outline-none transition-all text-xs" />
                </div>

                {/* LISTADO DE USUARIOS - 100% BLANCO */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {usuariosFiltrados.map((u) => (
                        <div key={u.id} className="fade-up bg-white border border-zinc-200 rounded-[3rem] p-8 hover:border-pink-500 transition-all flex flex-col shadow-sm">
                            <div className="flex items-start justify-between mb-6">
                                <div className="w-16 h-16 bg-pink-50 rounded-2xl border border-pink-100 flex items-center justify-center font-black text-xl italic text-pink-500 uppercase">
                                    {u.name.substring(0, 2)}
                                </div>
                                <div className="flex gap-2">
                                    <button onClick={() => openModal(u)} className="p-3 bg-zinc-100 rounded-xl hover:text-pink-500 hover:bg-pink-50 transition-colors">
                                        <Edit3 className="w-4 h-4 text-zinc-600" />
                                    </button>
                                    <button className="p-3 bg-zinc-100 rounded-xl hover:text-red-500 hover:bg-red-50 transition-colors">
                                        <Trash2 className="w-4 h-4 text-zinc-600" />
                                    </button>
                                </div>
                            </div>
                            
                            <h3 className="text-xl font-black italic text-zinc-900 uppercase tracking-tighter truncate">{u.name}</h3>
                            <p className="text-[10px] text-zinc-500 font-bold italic mb-6 truncate">{u.email}</p>
                            
                            <div className="space-y-5 mt-auto">
                                <div>
                                    <p className="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-2">Roles Asignados_</p>
                                    <div className="flex flex-wrap gap-2">
                                        {u.roles.length > 0 ? u.roles.map(rId => {
                                            const roleName = catalogoRoles.find(r => r.id === rId)?.name;
                                            return <span key={`r-${rId}`} className="px-3 py-1.5 bg-pink-500 text-white text-[9px] font-black uppercase tracking-widest rounded-lg">{roleName}</span>
                                        }) : <span className="text-[10px] italic text-zinc-400">Sin roles</span>}
                                    </div>
                                </div>

                                <div>
                                    <p className="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-2">Permisos Extra_</p>
                                    <div className="flex flex-wrap gap-2">
                                        {u.permisos.length > 0 ? u.permisos.map(pId => {
                                            const permName = catalogoPermisos.find(p => p.id === pId)?.label;
                                            return <span key={`p-${pId}`} className="px-3 py-1 border border-emerald-500/30 bg-emerald-50 text-emerald-600 text-[9px] font-bold uppercase tracking-wider rounded-lg">{permName}</span>
                                        }) : <span className="text-[10px] italic text-zinc-400">Sin permisos</span>}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* MODAL CORREGIDO - SIN OSCUROS, ALINEADO PERFECTO */}
                {showModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/40 backdrop-blur-sm">
                        <div className="bg-white border border-zinc-200 rounded-[3rem] w-full max-w-6xl max-h-[90vh] flex flex-col shadow-2xl fade-up overflow-hidden">
                            
                            {/* HEADER */}
                            <div className="flex-shrink-0 p-8 border-b border-zinc-100 flex justify-between items-center bg-white">
                                <div>
                                    <h2 className="text-2xl font-black italic text-zinc-900 uppercase tracking-tighter flex items-center">
                                        <ShieldCheck className="w-6 h-6 mr-3 text-pink-500" /> Matriz de Identidad
                                    </h2>
                                    <p className="text-[10px] font-bold text-zinc-500 uppercase tracking-widest mt-1 ml-9">
                                        Estructura Muchos a Muchos: Roles y Permisos
                                    </p>
                                </div>
                                <button onClick={() => setShowModal(false)} className="p-3 bg-zinc-50 rounded-2xl hover:text-pink-500 transition-colors">
                                    <X className="w-6 h-6 text-zinc-500" />
                                </button>
                            </div>

                            {/* BODY Y FOOTER DENTRO DEL FORM PARA EVITAR DESCUADRE */}
                            <form onSubmit={submit} className="flex flex-col min-h-0 h-full">
                                
                                {/* CONTENEDOR SCROLLABLE */}
                                <div className="flex-1 overflow-y-auto bg-white p-10">
                                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-10 text-left">
                                        
                                        {/* COLUMNA 1 */}
                                        <div className="space-y-6">
                                            <h3 className="text-xs font-black text-zinc-400 uppercase tracking-[0.2em] mb-4 flex items-center"><Users className="w-4 h-4 mr-2"/> Perfil_</h3>
                                            <div className="space-y-5">
                                                <div className="space-y-2">
                                                    <label className="text-[10px] font-black uppercase text-zinc-500 ml-1 italic">Nombre_</label>
                                                    <input value={data.name} onChange={e => setData('name', e.target.value)} type="text" className="w-full p-4 bg-zinc-50 border border-zinc-200 rounded-xl text-zinc-900 font-bold outline-none focus:border-pink-500 focus:ring-2 focus:ring-pink-500/20 transition-all text-sm" />
                                                </div>
                                                <div className="space-y-2">
                                                    <label className="text-[10px] font-black uppercase text-zinc-500 ml-1 italic">Email_</label>
                                                    <input value={data.email} onChange={e => setData('email', e.target.value)} type="email" className="w-full p-4 bg-zinc-50 border border-zinc-200 rounded-xl text-zinc-900 font-bold outline-none focus:border-pink-500 focus:ring-2 focus:ring-pink-500/20 transition-all text-sm" />
                                                </div>
                                            </div>
                                        </div>

                                        {/* COLUMNA 2 */}
                                        <div className="space-y-6">
                                            <h3 className="text-xs font-black text-zinc-400 uppercase tracking-[0.2em] mb-4 flex items-center"><Briefcase className="w-4 h-4 mr-2"/> Roles de Sistema_</h3>
                                            <div className="space-y-3">
                                                {catalogoRoles.map((rol) => (
                                                    <label key={`r-${rol.id}`} className={`flex items-center gap-4 p-4 border rounded-xl cursor-pointer transition-all select-none ${data.roles.includes(rol.id) ? 'border-pink-500 bg-pink-50' : 'border-zinc-200 bg-white hover:border-pink-300'}`}>
                                                        <input 
                                                            type="checkbox" 
                                                            checked={data.roles.includes(rol.id)}
                                                            onChange={() => handleArrayChange('roles', rol.id)}
                                                            className="w-5 h-5 rounded border-zinc-300 text-pink-500 focus:ring-pink-500 cursor-pointer"
                                                        />
                                                        <span className={`text-xs font-black uppercase tracking-tighter ${data.roles.includes(rol.id) ? 'text-pink-600' : 'text-zinc-700'}`}>{rol.name}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        </div>

                                        {/* COLUMNA 3 */}
                                        <div className="space-y-6">
                                            <h3 className="text-xs font-black text-zinc-400 uppercase tracking-[0.2em] mb-4 flex items-center"><Key className="w-4 h-4 mr-2"/> Permisos Extra_</h3>
                                            <div className="space-y-3">
                                                {catalogoPermisos.map((perm) => (
                                                    <label key={`p-${perm.id}`} className={`flex items-center gap-4 p-4 border rounded-xl cursor-pointer transition-all select-none ${data.permisos.includes(perm.id) ? 'border-emerald-500 bg-emerald-50' : 'border-zinc-200 bg-white hover:border-emerald-300'}`}>
                                                        <input 
                                                            type="checkbox" 
                                                            checked={data.permisos.includes(perm.id)}
                                                            onChange={() => handleArrayChange('permisos', perm.id)}
                                                            className="w-5 h-5 rounded border-zinc-300 text-emerald-500 focus:ring-emerald-500 cursor-pointer"
                                                        />
                                                        <span className={`text-xs font-black uppercase tracking-tighter ${data.permisos.includes(perm.id) ? 'text-emerald-600' : 'text-zinc-700'}`}>{perm.label}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                {/* FOOTER ANCLADO ABAJO */}
                                <div className="flex-shrink-0 p-8 border-t border-zinc-100 flex justify-end gap-4 bg-zinc-50 rounded-b-[3rem]">
                                    <button type="button" onClick={() => setShowModal(false)} className="px-8 py-4 text-[10px] font-black uppercase tracking-widest text-zinc-500 hover:text-zinc-900 transition-colors">
                                        Cancelar_
                                    </button>
                                    <button type="submit" disabled={processing} className="px-10 py-4 bg-zinc-900 text-white rounded-xl font-black uppercase tracking-widest text-[10px] shadow-lg hover:bg-black transition-colors">
                                        Sincronizar Matriz_
                                    </button>
                                </div>

                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}