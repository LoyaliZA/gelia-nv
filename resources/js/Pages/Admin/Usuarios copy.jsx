import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { 
    Users, UserPlus, Search, Edit3, Trash2, 
    ShieldCheck, X, Briefcase, Key, Check, MapPin, 
    Mail, User, ShieldAlert, ChevronRight, Lock, Smartphone, Save
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Usuarios({ auth, usuarios = [], departamentos = [], roles = [], todosLosPermisos = [] }) {
    // --- SECCIÓN: ESTADOS LOCALES ---
    const [busqueda, setBusqueda] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [usuarioEditando, setUsuarioEditando] = useState(null);

    // --- SECCIÓN: FORMULARIO DE INERTIA ---
    const { data, setData, post, put, processing, reset } = useForm({
        name: '',
        apellido_paterno: '',
        apellido_materno: '',
        username: '',
        email: '',
        password: '',
        telefono: '',
        area_id: '',
        roles_asignados: [], 
        permisos_individuales: [] 
    });
    
    // --- LÓGICA DE PERMISOS BLINDADA CONTRA CRASHEOS ---
    const permisoHeredado = (permisoName) => {
        const asignados = data.roles_asignados || [];
        return (roles || [])
            .filter(r => asignados.includes(r.name))
            .some(r => (r.permissions || []).some(p => p.name === permisoName));
    };

    const permisosAgrupados = (todosLosPermisos || []).reduce((acc, p) => {
        const modulo = p?.name?.split('.')[0] || 'Otros';
        if (!acc[modulo]) acc[modulo] = [];
        acc[modulo].push(p);
        return acc;
    }, {});
    
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

    // --- FILTRADO BLINDADO ---
    const usuariosFiltrados = (usuarios || []).filter(user => 
        (user.name || '').toLowerCase().includes(busqueda.toLowerCase()) ||
        (user.email || '').toLowerCase().includes(busqueda.toLowerCase()) ||
        (user.username && user.username.toLowerCase().includes(busqueda.toLowerCase()))
    );

    const rolesJerarquia = (roles || []).filter(rol => rol?.name && !rol.name.includes('Grupo:'));
    const rolesGrupos = (roles || []).filter(rol => rol?.name && rol.name.includes('Grupo:'));

    // --- MANEJADORES DE EVENTOS ---
    const abrirModal = (usuario = null) => {
        setUsuarioEditando(usuario);
        if (usuario) {
            setData({
                name: usuario.name || '',
                apellido_paterno: usuario.apellido_paterno || '',
                apellido_materno: usuario.apellido_materno || '',
                username: usuario.username || '',
                email: usuario.email || '',
                password: '', 
                telefono: usuario.telefono || '',
                area_id: usuario.area_id || '',
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

    const toggleRol = (rolName) => {
        const actuales = data.roles_asignados || [];
        const nuevosRoles = actuales.includes(rolName)
            ? actuales.filter(r => r !== rolName)
            : [...actuales, rolName];
        setData('roles_asignados', nuevosRoles);
    };

    const togglePermisoIndividual = (permisoName) => {
        if (permisoHeredado(permisoName)) return;

        const actuales = data.permisos_individuales || [];
        const nuevosPermisos = actuales.includes(permisoName)
            ? actuales.filter(p => p !== permisoName)
            : [...actuales, permisoName];
        setData('permisos_individuales', nuevosPermisos);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        
        // Limpiamos los permisos redundantes
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
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 theme-surface border border-zinc-200 dark:border-zinc-800 p-6 md:p-8 rounded-[2rem] shadow-sm fade-in-user">
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
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                            <input
                                type="text"
                                placeholder="Buscar por nombre o correo..."
                                className="w-full pl-12 pr-4 py-3 rounded-2xl theme-element border theme-border text-[11px] font-bold uppercase tracking-wider theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent"
                                style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'}
                                onBlur={(e) => e.target.style.borderColor = ''}
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

                {/* --- LISTADO DE USUARIOS --- */}
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
                                onMouseEnter={(e) => e.currentTarget.style.borderColor = 'var(--color-primario)'}
                                onMouseLeave={(e) => e.currentTarget.style.borderColor = ''}
                            >
                                <div className="flex items-center gap-5 w-full md:w-auto">
                                    <div className="w-14 h-14 rounded-full flex items-center justify-center border-[3px] shadow-sm transition-colors bg-white dark:bg-[#1A1A1A] group-hover:scale-105" style={{ borderColor: 'var(--color-primario)' }}>
                                        <span className="text-xl font-black italic" style={{ color: 'var(--color-primario)' }}>
                                            {(usuario.name || 'U').charAt(0).toUpperCase()}
                                        </span>
                                    </div>
                                    <div>
                                        <h3 className="theme-text-main font-black text-sm uppercase italic tracking-tighter">
                                            {usuario.name} {usuario.apellido_paterno}
                                        </h3>
                                        <div className="flex flex-wrap items-center gap-4 mt-1">
                                            <span className="flex items-center gap-1 theme-text-muted text-[10px] font-bold uppercase tracking-widest">
                                                <MapPin className="w-3 h-3" style={{ color: 'var(--color-primario)' }}/> {usuario.area?.departamento?.nombre || 'Sin Depto'} / {usuario.area?.nombre || 'Sin Área'}
                                            </span>
                                            <span className="flex items-center gap-1 theme-text-muted text-[10px] font-bold tracking-wider">
                                                <Mail className="w-3 h-3" style={{ color: 'var(--color-primario)' }}/> {usuario.email}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex flex-wrap gap-2 justify-center md:justify-end w-full md:w-auto flex-1">
                                    {(usuario.roles || []).map(rol => (
                                        <span key={rol.id} className="text-[9px] font-black uppercase tracking-widest bg-black/5 dark:bg-white/5 px-3 py-1.5 rounded-xl theme-text-main border theme-border">
                                            {rol.name}
                                        </span>
                                    ))}
                                    {usuario.permissions && usuario.permissions.length > 0 && (
                                        <span className="text-[9px] font-black uppercase tracking-widest bg-orange-500/10 text-orange-600 px-3 py-1.5 rounded-xl border border-orange-200 dark:border-orange-800 flex items-center gap-1">
                                            <ShieldAlert className="w-3 h-3" /> +{usuario.permissions.length} Especial
                                        </span>
                                    )}
                                </div>

                                <div className="flex gap-2 w-full md:w-auto justify-end">
                                    <button type="button" onClick={() => abrirModal(usuario)} className="p-3 rounded-xl theme-element theme-text-main hover:text-white transition-colors border theme-border hover:border-transparent group/btn" onMouseEnter={(e) => e.currentTarget.style.backgroundColor = 'var(--color-primario)'} onMouseLeave={(e) => e.currentTarget.style.backgroundColor = ''}>
                                        <Edit3 className="w-5 h-5 group-hover/btn:text-white" />
                                    </button>
                                    <button type="button" className="p-3 rounded-xl theme-element theme-text-main hover:text-white transition-colors border theme-border hover:bg-red-500 hover:border-transparent">
                                        <Trash2 className="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                {/* --- MODAL BLINDADO --- */}
                {showModal && (
                    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 backdrop-blur-md bg-black/40">
                        <div className="absolute inset-0" onClick={cerrarModal}></div>
                        
                        <div 
                            className="relative w-full max-w-4xl theme-surface rounded-[2.5rem] border theme-border shadow-2xl overflow-hidden flex flex-col modal-pop"
                            style={{ maxHeight: 'calc(100vh - 4rem)' }}
                        >
                            <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center bg-transparent shrink-0">
                                <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3">
                                    <ShieldCheck className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                                    {usuarioEditando ? 'Ajustar Perfil Completo' : 'Alta de Nuevo Colaborador'}
                                </h2>
                                <button type="button" onClick={cerrarModal} className="theme-text-muted hover:theme-text-main transition-colors p-2 rounded-full hover:bg-black/5 dark:hover:bg-white/5">
                                    <X className="w-6 h-6" />
                                </button>
                            </div>

                            <div className="p-6 md:p-8 overflow-y-auto custom-scrollbar flex-1 min-h-0 space-y-10">
                                
                                {/* 1. IDENTIDAD LABORAL */}
                                <div>
                                    <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                        <User className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> 
                                        Identidad Personal
                                    </h3>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div className="space-y-1.5">
                                            <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Nombre(s) *</label>
                                            <div className="relative">
                                                <User className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                                <input value={data.name || ''} onChange={e => setData('name', e.target.value)} type="text" className="w-full pl-10 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none focus:ring-1 focus:ring-transparent transition-all" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} onBlur={(e) => e.target.style.borderColor = ''} />
                                            </div>
                                        </div>
                                        <div className="space-y-1.5">
                                            <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Ap. Paterno *</label>
                                            <input value={data.apellido_paterno || ''} onChange={e => setData('apellido_paterno', e.target.value)} type="text" className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} onBlur={(e) => e.target.style.borderColor = ''} />
                                        </div>
                                        <div className="space-y-1.5">
                                            <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Ap. Materno</label>
                                            <input value={data.apellido_materno || ''} onChange={e => setData('apellido_materno', e.target.value)} type="text" className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} onBlur={(e) => e.target.style.borderColor = ''} />
                                        </div>
                                    </div>
                                    
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <div className="space-y-1.5">
                                            <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Teléfono / WhatsApp</label>
                                            <div className="relative">
                                                <Smartphone className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                                <input value={data.telefono || ''} onChange={e => setData('telefono', e.target.value)} type="text" className="w-full pl-10 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} onBlur={(e) => e.target.style.borderColor = ''} />
                                            </div>
                                        </div>
                                        <div className="space-y-1.5">
                                            <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Área Organizacional *</label>
                                            <select value={data.area_id || ''} onChange={e => setData('area_id', e.target.value)} className="w-full px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all focus:ring-1 focus:ring-transparent appearance-none" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} onBlur={(e) => e.target.style.borderColor = ''}>
                                                <option value="">Selecciona Área...</option>
                                                {(departamentos || []).map(depto => (
                                                    <optgroup key={depto.id} label={depto.nombre}>
                                                        {(depto.areas || []).map(area => (
                                                            <option key={area.id} value={area.id}>{area.nombre}</option>
                                                        ))}
                                                    </optgroup>
                                                ))}
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {/* 2. CREDENCIALES DE ACCESO */}
                                <div>
                                    <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                        <Lock className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> 
                                        Credenciales
                                    </h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <div className="space-y-1.5">
                                            <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Correo Electrónico *</label>
                                            <div className="relative">
                                                <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                                <input value={data.email || ''} onChange={e => setData('email', e.target.value)} type="email" className="w-full pl-10 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all" onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} onBlur={(e) => e.target.style.borderColor = ''} />
                                            </div>
                                        </div>
                                        <div className="space-y-1.5">
                                            <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Contraseña</label>
                                            <div className="relative">
                                                <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                                <input value={data.password || ''} onChange={e => setData('password', e.target.value)} type="password" placeholder={usuarioEditando ? "Dejar en blanco para conservar actual" : "Asignar contraseña"} className="w-full pl-10 pr-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold theme-text-main outline-none transition-all theme-placeholder" onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'} onBlur={(e) => e.target.style.borderColor = ''} />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* 3. ROLES Y JERARQUÍAS */}
                                <div>
                                    <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                        <Briefcase className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> 
                                        Roles y Jerarquías
                                    </h3>
                                    <div className="space-y-4">
                                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-2">Asignación Principal_</p>
                                        <div className="flex flex-wrap gap-3">
                                            {rolesJerarquia.map(rol => (
                                                <button
                                                    key={rol.id} type="button" onClick={() => toggleRol(rol.name)}
                                                    className={`px-4 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest border-2 transition-all flex items-center gap-2 ${data.roles_asignados?.includes(rol.name) ? 'shadow-md' : 'theme-element theme-border theme-text-muted hover:theme-text-main hover:border-gray-400'}`}
                                                    style={data.roles_asignados?.includes(rol.name) ? { borderColor: 'var(--color-primario)', backgroundColor: 'var(--color-primario)', color: 'white' } : {}}
                                                >
                                                    {data.roles_asignados?.includes(rol.name) && <Check className="w-3 h-3" />}
                                                    {rol.name}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                {/* 4. PERMISOS ATÓMICOS */}
                                {todosLosPermisos && todosLosPermisos.length > 0 && (
                                    <div>
                                        <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                                            <Key className="w-4 h-4" style={{ color: 'var(--color-primario)' }} /> 
                                            Permisos Atómicos
                                        </h3>
                                        <p className="text-[10px] theme-text-muted mb-4 font-bold tracking-widest">
                                            INDICADORES: <span className="text-blue-500 mx-1">AZUL</span> herencia de rol (Lectura). <span className="text-orange-500 mx-1">NARANJA</span> excepción directa (Asignado).
                                        </p>

                                        <div className="space-y-2">
                                            {Object.entries(permisosAgrupados).map(([modulo, permisosDeModulo]) => (
                                                <details key={modulo} className="group theme-element rounded-2xl overflow-hidden border theme-border">
                                                    <summary className="p-4 cursor-pointer flex justify-between items-center select-none hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none">
                                                        <div className="flex items-center gap-3">
                                                            <ShieldCheck className="w-4 h-4 theme-text-muted" />
                                                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-main italic">
                                                                Módulo: {modulo}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center gap-3 theme-text-muted">
                                                            <span className="text-[9px] font-bold tracking-widest">
                                                                {(permisosDeModulo || []).length} Funciones
                                                            </span>
                                                            <ChevronRight className="w-4 h-4 group-open:rotate-90 transition-transform duration-300" />
                                                        </div>
                                                    </summary>
                                                    
                                                    <div className="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 bg-transparent border-t theme-border">
                                                        {(permisosDeModulo || []).map(permiso => {
                                                            const isHeredado = permisoHeredado(permiso.name);
                                                            const isDirecto = (data.permisos_individuales || []).includes(permiso.name);
                                                            const accionNombre = permiso.name.split('.')[1] || permiso.name;

                                                            return (
                                                                <button 
                                                                    key={permiso.id} 
                                                                    type="button" 
                                                                    disabled={isHeredado}
                                                                    onClick={() => togglePermisoIndividual(permiso.name)} 
                                                                    className={`
                                                                        flex justify-between items-center px-4 py-3 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all text-left
                                                                        ${isHeredado 
                                                                            ? 'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400 cursor-not-allowed opacity-90' 
                                                                            : isDirecto 
                                                                                ? 'border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 shadow-sm' 
                                                                                : 'theme-border bg-white dark:bg-[#121212] theme-text-muted hover:border-gray-400 hover:theme-text-main'
                                                                        }
                                                                    `}
                                                                >
                                                                    <span>{accionNombre.replace('_', ' ')}</span>
                                                                    {isHeredado && <ShieldCheck className="w-3 h-3 text-blue-500 shrink-0" />}
                                                                    {isDirecto && !isHeredado && <Check className="w-3 h-3 text-orange-500 shrink-0" />}
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                </details>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                            
                            <div className="p-6 md:p-8 border-t theme-border bg-transparent shrink-0">
                                <button type="button" onClick={handleSubmit} disabled={processing} className="w-full text-white dark:text-black py-4 rounded-[1.5rem] text-[11px] font-black uppercase tracking-[0.2em] italic hover:scale-[1.02] transition-transform shadow-xl flex items-center justify-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    <Save className="w-5 h-5" />
                                    {processing ? 'Procesando Datos...' : 'Confirmar Guardado Completo'}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <style>{`
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #e4e4e7; }
                .theme-placeholder::placeholder { color: #a1a1aa; }
                
                .dark .theme-surface { background-color: #121212; border-color: #222222; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #2A2A2A; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #27272a; }
                .dark .theme-placeholder::placeholder { color: #52525b; }

                .custom-scrollbar::-webkit-scrollbar { width: 8px; }
                .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(156, 163, 175, 0.4); border-radius: 20px; }
                .custom-scrollbar::-webkit-scrollbar-thumb:hover { background-color: var(--color-primario); }
                
                details > summary { list-style: none; }
                details > summary::-webkit-details-marker { display: none; }

                @keyframes scaleUp {
                    0% { opacity: 0; transform: scale(0.98); }
                    100% { opacity: 1; transform: scale(1); }
                }
                .modal-pop {
                    animation: scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
            `}</style>
        </AppLayout>
    );
}