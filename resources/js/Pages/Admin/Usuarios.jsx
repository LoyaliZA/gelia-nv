import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { createPortal } from 'react-dom';
import { 
    Users, UserPlus, Search, Edit3, Trash2, 
    ShieldCheck, X, Briefcase, Check, MapPin, 
    Mail, User, Lock, Smartphone, Save, Network
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import PermisosAtomicos from './Partials/PermisosAtomicos'; // <-- Importamos tu nuevo Partial

export default function Usuarios({ auth, usuarios = [], departamentos = [], posiblesGerentes = [], roles = [], todosLosPermisos = [], sexos = [] }) {
    // --- ESTADOS LOCALES ---
    const [busqueda, setBusqueda] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [usuarioEditando, setUsuarioEditando] = useState(null);

    // --- FORMULARIO MATRICIAL ---
    const { data, setData, post, put, processing, reset } = useForm({
        name: '',
        apellido_paterno: '',
        apellido_materno: '',
        username: '',
        email: '',
        password: '',
        telefono: '',
        fecha_nacimiento: '',
        catalogo_sexo_id: '',
        departamentos: [],
        areas: [],
        gerentes: [],
        roles_asignados: [], 
        permisos_individuales: [] 
    });
    
    // Función de validación (Mantenida aquí para limpiar los datos antes de enviarlos a Laravel)
    const permisoHeredado = (permisoName) => {
        const asignados = data.roles_asignados || [];
        return (roles || [])
            .filter(r => asignados.includes(r.name))
            .some(r => (r.permissions || []).some(p => p.name === permisoName));
    };

    // --- ANIMACIONES ---
    useEffect(() => {
        animate('.fade-in-user', {
            opacity: [0, 1],
            translateY: [10, 0],
            delay: (el, i) => i * 50,
            easing: 'easeOutExpo',
            duration: 600
        });
    }, [usuarios]);

    // --- FILTRADO ---
    const usuariosFiltrados = (usuarios || []).filter(user => 
        (user.name || '').toLowerCase().includes(busqueda.toLowerCase()) ||
        (user.email || '').toLowerCase().includes(busqueda.toLowerCase()) ||
        (user.username && user.username.toLowerCase().includes(busqueda.toLowerCase()))
    );

    const rolesJerarquia = (roles || []).filter(rol => rol?.name && !rol.name.includes('Grupo:'));

    // --- MANEJADORES DE EVENTOS ---
    const abrirModal = (usuario = null) => {
        setUsuarioEditando(usuario);
        if (usuario) {
            setData({
                name: (usuario.name || '').trim(),
                apellido_paterno: (usuario.apellido_paterno || '').trim(),
                apellido_materno: (usuario.apellido_materno || '').trim(),
                username: (usuario.username || '').trim(),
                email: (usuario.email || '').trim(),
                password: '', 
                telefono: (usuario.telefono || '').trim(),
                fecha_nacimiento: usuario.fecha_nacimiento || '',
                catalogo_sexo_id: usuario.catalogo_sexo_id || '',
                departamentos: usuario.departamentos ? usuario.departamentos.map(d => d.id) : [],
                areas: usuario.areas ? usuario.areas.map(a => a.id) : [],
                gerentes: usuario.gerentes ? usuario.gerentes.map(g => g.id) : [],
                roles_asignados: usuario.roles ? usuario.roles.map(r => r.name) : [],
                permisos_individuales: usuario.permissions ? usuario.permissions.map(p => p.name) : []
            });
        } else {
            reset();
        }
        setShowModal(true);
    };

    const cerrarModal = () => {
        setShowModal(false);
        setTimeout(() => reset(), 300); 
    };

    const toggleSelection = (campo, idOrName) => {
        const actuales = data[campo] || [];
        const nuevos = actuales.includes(idOrName)
            ? actuales.filter(item => item !== idOrName)
            : [...actuales, idOrName];
        setData(campo, nuevos);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        // Sanitización antes de enviar a Laravel
        const actuales = data.permisos_individuales || [];
        const permisosLimpios = actuales.filter(p => !permisoHeredado(p));
        const payload = { ...data, permisos_individuales: permisosLimpios };

        if (usuarioEditando) {
            put(route('admin.usuarios.update', usuarioEditando.id), payload, {
                onSuccess: () => cerrarModal()
            });
        } else {
            post(route('admin.usuarios.store'), payload, {
                onSuccess: () => cerrarModal()
            });
        }
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Directorio de Usuarios" />

            <div className="max-w-7xl mx-auto p-4 md:p-8 space-y-8">
                {/* --- HEADER --- */}
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 theme-surface border-2 theme-border p-6 md:p-8 rounded-[2rem] md:rounded-[3rem] shadow-sm fade-in-user">
                    <div>
                        <h1 className="text-2xl font-black theme-text-main flex items-center gap-3 italic uppercase tracking-tighter">
                            <Users className="w-6 h-6 md:w-8 md:h-8" style={{ color: 'var(--color-primario)' }} />
                            Directorio y Accesos
                        </h1>
                        <p className="theme-text-muted text-[10px] font-bold uppercase tracking-widest mt-1 opacity-80">
                            Gestión centralizada de personal, identidad y permisos operativos
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row gap-4 w-full md:w-auto">
                        <div className="relative flex-1 md:w-72">
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                            <input
                                type="text"
                                placeholder="BUSCAR POR NOMBRE O CORREO..."
                                className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element border theme-border text-[11px] font-bold uppercase tracking-wider theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent"
                                style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                            />
                        </div>
                        <button
                            type="button"
                            onClick={() => abrirModal()}
                            className="text-white dark:text-black px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all hover:scale-105 active:scale-95 flex justify-center items-center gap-2 shadow-lg"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <UserPlus className="w-4 h-4" /> Nuevo Ingreso
                        </button>
                    </div>
                </div>

                {/* --- LISTADO --- */}
                <div className="grid grid-cols-1 gap-4">
                    {usuariosFiltrados.length === 0 ? (
                        <div className="text-center py-16 theme-surface border-2 border-dashed theme-border rounded-[2rem]">
                            <Users className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                            <h3 className="text-lg font-black italic uppercase theme-text-main">No hay coincidencias</h3>
                        </div>
                    ) : (
                        usuariosFiltrados.map((usuario) => (
                            <div 
                                key={usuario.id} 
                                className="fade-in-user theme-surface rounded-[2rem] p-6 border-2 theme-border flex flex-col md:flex-row items-center justify-between gap-6 transition-all group hover:shadow-md"
                            >
                                <div className="flex items-center gap-5 w-full md:w-auto">
                                    <div className="w-14 h-14 rounded-full flex items-center justify-center border-[3px] shadow-sm transition-colors bg-white dark:bg-[#1A1A1A]" style={{ borderColor: 'var(--color-primario)' }}>
                                        <span className="text-xl font-black italic" style={{ color: 'var(--color-primario)' }}>
                                            {(usuario.name || 'U').charAt(0).toUpperCase()}
                                        </span>
                                    </div>
                                    <div>
                                        <h3 className="theme-text-main font-black text-sm uppercase italic tracking-tighter leading-none">
                                            {(usuario.name || '').trim()} {(usuario.apellido_paterno || '').trim()}
                                        </h3>
                                        <div className="flex flex-wrap items-center gap-4 mt-2">
                                            <span className="flex items-center gap-1 theme-text-muted text-[10px] font-bold uppercase tracking-widest">
                                                <MapPin className="w-3 h-3" style={{ color: 'var(--color-primario)' }}/> 
                                                <span className="truncate max-w-[200px]">
                                                    {usuario.departamentos?.map(d => d.nombre).join(', ') || 'Sin Depto'}
                                                </span>
                                            </span>
                                            <span className="flex items-center gap-1 theme-text-muted text-[10px] font-bold tracking-wider">
                                                <Mail className="w-3 h-3" style={{ color: 'var(--color-primario)' }}/> {usuario.email}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex flex-wrap gap-2 justify-center md:justify-end w-full md:w-auto flex-1">
                                    {(usuario.roles || []).map(rol => (
                                        <span key={rol.id} className="text-[9px] font-black uppercase tracking-widest theme-element px-3 py-1.5 rounded-xl theme-text-main border theme-border">
                                            {rol.name}
                                        </span>
                                    ))}
                                </div>

                                <div className="flex gap-2 w-full md:w-auto justify-end">
                                    <button type="button" onClick={() => abrirModal(usuario)} className="p-3 rounded-xl theme-element theme-text-main hover:text-white transition-colors border theme-border hover:border-transparent group/btn" onMouseEnter={(e) => e.currentTarget.style.backgroundColor = 'var(--color-primario)'} onMouseLeave={(e) => e.currentTarget.style.backgroundColor = ''}>
                                        <Edit3 className="w-5 h-5" />
                                    </button>
                                    <button type="button" className="p-3 rounded-xl theme-element theme-text-main hover:text-white transition-colors border theme-border hover:bg-red-500 hover:border-transparent">
                                        <Trash2 className="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {/* --- MODAL --- */}
                {showModal && createPortal(
                    <div className="fixed inset-0 z-[100] overflow-y-auto">
                        
                        {/* 1. Fondo oscuro independiente */}
                        <div className="fixed inset-0 backdrop-blur-md bg-black/40" onClick={cerrarModal}></div>
                        
                        {/* 2. CONTENEDOR FLEXIBLE: Este es el que faltaba. Centra el modal y permite scroll seguro */}
                        <div className="flex min-h-full items-center justify-center p-4 sm:p-6">
                            
                            {/* 3. La ventana de tu modal */}
                            <div className="relative w-full max-w-4xl theme-surface rounded-[2.5rem] border-2 theme-border shadow-2xl overflow-hidden flex flex-col modal-pop text-left" style={{ maxHeight: 'calc(100vh - 4rem)' }}>
                                
                                <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center bg-transparent shrink-0">
                                    <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 leading-none">
                                        <ShieldCheck className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                                        {usuarioEditando ? 'Ajustar Perfil Completo' : 'Alta de Nuevo Colaborador'}
                                    </h2>
                                    <button type="button" onClick={cerrarModal} className="theme-text-muted hover:theme-text-main transition-colors p-2 rounded-full hover:bg-black/5 dark:hover:bg-white/5">
                                        <X className="w-6 h-6" />
                                    </button>
                                </div>

                                <div className="p-6 md:p-8 overflow-y-auto custom-scrollbar flex-1 min-h-0 space-y-10">
                                    
                                    {/* 1. IDENTIDAD */}
                                    <div>
                                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                            <User className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> Identidad Personal
                                        </h3>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div className="space-y-1.5">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Nombre(s) *</label>
                                                <div className="relative">
                                                    <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                                    <input value={data.name} onChange={e => setData('name', e.target.value)} type="text" required className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none focus:ring-1 focus:ring-transparent transition-all" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                                </div>
                                            </div>
                                            <div className="space-y-1.5">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Ap. Paterno *</label>
                                                <input value={data.apellido_paterno} onChange={e => setData('apellido_paterno', e.target.value)} type="text" required className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" />
                                            </div>
                                            <div className="space-y-1.5">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Ap. Materno</label>
                                                <input value={data.apellido_materno} onChange={e => setData('apellido_materno', e.target.value)} type="text" className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                            <div className="space-y-1.5">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Teléfono / WhatsApp</label>
                                                <div className="relative">
                                                    <Smartphone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                                    <input value={data.telefono} onChange={e => setData('telefono', e.target.value)} type="text" className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" />
                                                </div>
                                            </div>
                                            <div className="space-y-1.5">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Sexo Biológico</label>
                                                <select value={data.catalogo_sexo_id || ''} onChange={e => setData('catalogo_sexo_id', e.target.value)} className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none appearance-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }}>
                                                    <option value="">Selecciona...</option>
                                                    {(sexos || []).map(s => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                                                </select>
                                            </div>
                                            <div className="space-y-1.5">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Fecha de Nacimiento</label>
                                                <div className="relative">
                                                    <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                                    <input value={data.fecha_nacimiento || ''} onChange={e => setData('fecha_nacimiento', e.target.value)} type="date" className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* 2. ORGANIZACIÓN Y LIDERAZGO */}
                                    <div>
                                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                            <Network className="w-4 h-4 text-purple-500" /> Organización y Liderazgo
                                        </h3>
                                        
                                        <div className="grid grid-cols-1 gap-6">
                                            <div className="space-y-2">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">1. Departamentos Asignados (Múltiple)</label>
                                                <div className="flex flex-wrap gap-2 p-3 border theme-border rounded-2xl theme-element bg-transparent">
                                                    {(departamentos || []).map(depto => (
                                                        <button
                                                            key={`depto-${depto.id}`} type="button" onClick={() => toggleSelection('departamentos', depto.id)}
                                                            className={`px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all flex items-center gap-1.5 ${data.departamentos.includes(depto.id) ? 'shadow-sm text-white' : 'theme-element theme-border theme-text-muted'}`}
                                                            style={data.departamentos.includes(depto.id) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                                        >
                                                            {data.departamentos.includes(depto.id) && <Check className="w-3 h-3" />}
                                                            {depto.nombre}
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">2. Áreas de Operación (Múltiple)</label>
                                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 p-4 border theme-border rounded-2xl theme-element bg-transparent max-h-48 overflow-y-auto custom-scrollbar">
                                                    {(departamentos || []).map(depto => (
                                                        <div key={`grupo-${depto.id}`} className="space-y-2">
                                                            <p className="text-[8px] font-black uppercase theme-text-muted opacity-70 italic border-b theme-border pb-1">{depto.nombre}</p>
                                                            <div className="flex flex-col gap-1.5 items-start">
                                                                {(depto.areas || []).map(area => (
                                                                    <button
                                                                        key={`area-${area.id}`} type="button" onClick={() => toggleSelection('areas', area.id)}
                                                                        className={`px-3 py-1.5 rounded-lg text-[9px] font-bold uppercase tracking-wider border transition-all flex items-center gap-1.5 text-left w-full ${data.areas.includes(area.id) ? 'shadow-sm text-white' : 'bg-transparent theme-border theme-text-muted hover:bg-black/5 dark:hover:bg-white/5'}`}
                                                                        style={data.areas.includes(area.id) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                                                    >
                                                                        {data.areas.includes(area.id) && <Check className="w-3 h-3 shrink-0" />}
                                                                        <span className="truncate">{area.nombre}</span>
                                                                    </button>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">3. Reporta a (Gerentes / Líderes)</label>
                                                <div className="flex flex-wrap gap-2 p-3 border theme-border rounded-2xl theme-element bg-transparent max-h-32 overflow-y-auto custom-scrollbar">
                                                    {(posiblesGerentes || []).length === 0 ? (
                                                        <span className="text-xs theme-text-muted italic px-2">No hay gerentes disponibles.</span>
                                                    ) : (
                                                        (posiblesGerentes || []).map(gerente => (
                                                            <button
                                                                key={`gerente-${gerente.id}`} type="button" onClick={() => toggleSelection('gerentes', gerente.id)}
                                                                className={`px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all flex items-center gap-1.5 ${data.gerentes.includes(gerente.id) ? 'shadow-sm text-white' : 'bg-transparent theme-border theme-text-muted hover:bg-black/5 dark:hover:bg-white/5'}`}
                                                                style={data.gerentes.includes(gerente.id) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                                            >
                                                                {data.gerentes.includes(gerente.id) && <Check className="w-3 h-3" />}
                                                                {gerente.name} {gerente.apellido_paterno}
                                                            </button>
                                                        ))
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* 3. CREDENCIALES */}
                                    <div>
                                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                            <Lock className="w-4 h-4 text-blue-500" /> Credenciales de Acceso
                                        </h3>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div className="space-y-1.5">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Username *</label>
                                                <div className="relative">
                                                    <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                                    <input value={data.username} onChange={e => setData('username', e.target.value)} type="text" required className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                                </div>
                                            </div>
                                            <div className="space-y-1.5">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Correo Electrónico *</label>
                                                <div className="relative">
                                                    <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                                    <input value={data.email} onChange={e => setData('email', e.target.value)} type="email" required className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                                </div>
                                            </div>
                                            <div className="space-y-1.5">
                                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Contraseña</label>
                                                <div className="relative">
                                                    <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                                    <input value={data.password} onChange={e => setData('password', e.target.value)} type="password" required={!usuarioEditando} placeholder={usuarioEditando ? "Dejar en blanco para conservar" : "Asignar contraseña"} className="w-full pl-11 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* 4. ROLES */}
                                    <div>
                                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                            <Briefcase className="w-4 h-4 text-orange-500" /> Roles y Jerarquías
                                        </h3>
                                        <div className="space-y-4">
                                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-2">Asignación Principal_</p>
                                            <div className="flex flex-wrap gap-3">
                                                {rolesJerarquia.map(rol => (
                                                    <button
                                                        key={rol.id} type="button" onClick={() => toggleSelection('roles_asignados', rol.name)}
                                                        className={`px-4 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest border-2 transition-all flex items-center gap-2 ${data.roles_asignados?.includes(rol.name) ? 'shadow-md text-white' : 'theme-element theme-border theme-text-muted'}`}
                                                        style={data.roles_asignados?.includes(rol.name) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)' } : {}}
                                                    >
                                                        {data.roles_asignados?.includes(rol.name) && <Check className="w-3 h-3" />}
                                                        {rol.name}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    {/* 5. PERMISOS ATÓMICOS MIGRADO AL PARTIAL */}
                                    <PermisosAtomicos 
                                        data={data}
                                        setData={setData}
                                        roles={roles}
                                        todosLosPermisos={todosLosPermisos}
                                    />
                                    
                                </div>
                                
                                <div className="p-6 md:p-8 border-t theme-border bg-transparent shrink-0">
                                    <button type="button" onClick={handleSubmit} disabled={processing} className="w-full text-white dark:text-black py-4 rounded-[1.5rem] text-[11px] font-black uppercase tracking-[0.2em] italic shadow-xl flex items-center justify-center gap-2 transition-transform hover:scale-[1.01]" style={{ backgroundColor: 'var(--color-primario)' }}>
                                        <Save className="w-5 h-5" />
                                        {processing ? 'Procesando Datos...' : 'Confirmar Guardado Completo'}
                                    </button>
                                </div>
                            </div>
                        </div> {/* <-- Aquí cerramos el nuevo contenedor flexible */}
                    </div>,
                    document.body
                )}
            </div>
        </AppLayout>
    );
}