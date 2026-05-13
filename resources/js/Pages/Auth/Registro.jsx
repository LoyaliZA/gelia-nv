import React, { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { 
    UserPlus, Upload, ShieldCheck, User, Mail, 
    Lock, Phone, Calendar, ChevronDown, CheckCircle2,
    Briefcase, AlertTriangle // <-- AGREGADO: Icono para errores críticos
} from 'lucide-react';

export default function Registro({ rol_asignado, catalogos }) {
    // --- SECCIÓN 1: ESTADO Y FORMULARIO ---
    const { data, setData, post, processing, errors, transform } = useForm({
        name: '',
        apellido_paterno: '',
        apellido_materno: '',
        username: '',
        email: '',
        password: '',
        telefono: '',
        fecha_nacimiento: '', 
        catalogo_sexo_id: '',
        foto_perfil: null,
    });

    const [previewUrl, setPreviewUrl] = useState(null);
    const [isDarkMode, setIsDarkMode] = useState(false);
    
    // Detección de tema del sistema
    useEffect(() => {
        if (typeof window !== 'undefined') {
            const isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            setIsDarkMode(isDark);
        }
    }, []);

    // --- SECCIÓN 2: MANEJADORES DE EVENTOS ---
    const handleFileChange = (e) => {
        const file = e.target.files[0];
        setData('foto_perfil', file);
        if (file) {
            setPreviewUrl(URL.createObjectURL(file));
        } else {
            setPreviewUrl(null);
        }
    };

    /**
     * Procesa y envía el formulario de registro.
     * Aplica el Principio de Responsabilidad Única delegando la sanitización a transform()
     */
    const handleSubmit = (e) => {
        e.preventDefault();
        
        // 1. SANITIZACIÓN TÁCTICA (Zero Trust)
        // Interceptamos los datos antes de enviarlos para evitar errores de tipado en DB
        transform((currentData) => {
            const sanitizedData = { ...currentData };
            
            // Convertimos strings vacíos a null real para la base de datos
            if (sanitizedData.catalogo_sexo_id === '') sanitizedData.catalogo_sexo_id = null;
            if (sanitizedData.apellido_materno === '') sanitizedData.apellido_materno = null;
            if (sanitizedData.telefono === '') sanitizedData.telefono = null;
            
            // Si no hay foto, eliminamos la propiedad para forzar un envío JSON en lugar de Multipart
            if (!sanitizedData.foto_perfil) {
                delete sanitizedData.foto_perfil;
            }
            
            return sanitizedData;
        });

        // 2. ENVÍO SEGURO
        // Enviamos a la URL exacta actual para preservar la firma criptográfica intacta
        post(window.location.href);
    };

    // --- SECCIÓN 3: RENDERIZADO UX/UI ---
    return (
        <div className={`min-h-screen flex flex-col items-center justify-center p-4 sm:p-6 lg:p-8 transition-colors duration-500 relative overflow-hidden ${isDarkMode ? 'dark bg-[#0a0a0a]' : 'bg-[#FAFAFA]'}`}>
            <Head title="Registro de Identidad | GELIA" />

            <div className="w-full max-w-4xl space-y-6 relative z-10">
                
                {/* --- HEADER ESTILO GELIA --- */}
                <div className="animate-page-reveal theme-surface border-2 theme-border p-6 md:p-10 rounded-[2rem] md:rounded-[3rem] shadow-sm flex flex-col items-center text-center">
                    <div className="flex items-center space-x-3 mb-3">
                        <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>ONBOARDING SEGURO</p>
                    </div>
                    <h1 className="text-3xl md:text-5xl font-black theme-text-main flex items-center justify-center gap-3 italic uppercase tracking-tighter m-0">
                        <ShieldCheck className="w-8 h-8 md:w-12 md:h-12 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                        REGISTRO <span style={{ color: 'var(--color-primario)' }}>GELIA</span>
                    </h1>
                    
                    <div className="mt-6 flex items-center justify-center gap-2 bg-black/5 dark:bg-white/5 border theme-border px-5 py-2.5 rounded-xl">
                        <Briefcase className="w-4 h-4 theme-text-muted" />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Nivel Asignado:</span>
                        <span className="text-[11px] font-black uppercase tracking-widest theme-text-main">{rol_asignado}</span>
                    </div>
                </div>

                {/* --- ALERTA DE ERRORES CRÍTICOS DEL SISTEMA --- */}
                {errors.error && (
                    <div className="animate-page-reveal w-full max-w-4xl mt-4 bg-red-500/10 border border-red-500/20 p-5 rounded-[2rem] flex items-start gap-4 z-10 relative">
                        <AlertTriangle className="w-6 h-6 text-red-500 shrink-0 mt-0.5" />
                        <div>
                            <h4 className="text-[11px] font-black uppercase tracking-widest text-red-500 mb-1">Fallo en la Matriz Base</h4>
                            <p className="text-xs font-bold text-red-400">{errors.error}</p>
                        </div>
                    </div>
                )}

                {/* --- FORMULARIO ESTILO GELIA --- */}
                <form onSubmit={handleSubmit} className="animate-page-reveal theme-surface border-2 theme-border p-6 md:p-10 rounded-[2rem] md:rounded-[3rem] shadow-sm space-y-10" style={{ animationDelay: '100ms' }}>
                    
                    {/* Sub-sección 1: Datos Personales */}
                    <div className="space-y-6">
                        <h3 className="text-sm font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-2 border-b theme-border pb-4">
                            <User className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> 
                            1. Identidad Operativa
                        </h3>
                        
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">Nombre(s)_</label>
                                <input type="text" value={data.name} onChange={e => setData('name', e.target.value)} required placeholder="Ej. Juan Carlos" className="w-full px-5 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 shadow-sm theme-placeholder" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                {errors.name && <p className="text-[10px] font-bold text-red-500 uppercase ml-1">{errors.name}</p>}
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">Apellido Paterno_</label>
                                <input type="text" value={data.apellido_paterno} onChange={e => setData('apellido_paterno', e.target.value)} required placeholder="Ej. Pérez" className="w-full px-5 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 shadow-sm theme-placeholder" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                {errors.apellido_paterno && <p className="text-[10px] font-bold text-red-500 uppercase ml-1">{errors.apellido_paterno}</p>}
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">Apellido Materno_</label>
                                <input type="text" value={data.apellido_materno} onChange={e => setData('apellido_materno', e.target.value)} placeholder="Opcional" className="w-full px-5 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 shadow-sm theme-placeholder" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                {errors.apellido_materno && <p className="text-[10px] font-bold text-red-500 uppercase ml-1">{errors.apellido_materno}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">Teléfono_</label>
                                <div className="relative">
                                    <Phone className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                                    <input type="text" value={data.telefono} onChange={e => setData('telefono', e.target.value)} placeholder="10 dígitos" className="w-full pl-11 pr-4 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 shadow-sm theme-placeholder" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                </div>
                                {errors.telefono && <p className="text-[10px] font-bold text-red-500 uppercase ml-1">{errors.telefono}</p>}
                            </div>
                            
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">Fecha Nacimiento_</label>
                                <div className="relative">
                                    <Calendar className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                                    <input type="date" value={data.fecha_nacimiento} onChange={e => setData('fecha_nacimiento', e.target.value)} required className="w-full pl-11 pr-4 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 shadow-sm theme-placeholder appearance-none" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                </div>
                                {errors.fecha_nacimiento && <p className="text-[10px] font-bold text-red-500 uppercase ml-1">{errors.fecha_nacimiento}</p>}
                            </div>
                            
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">Sexo_</label>
                                <div className="relative">
                                    <select value={data.catalogo_sexo_id} onChange={e => setData('catalogo_sexo_id', e.target.value)} required className="w-full px-5 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 shadow-sm appearance-none cursor-pointer" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''}>
                                        <option value="" className="font-bold">SELECCIONE...</option>
                                        {catalogos?.sexos?.map(sexo => (
                                            <option key={sexo.id} value={sexo.id} className="font-bold uppercase">{sexo.nombre}</option>
                                        ))}
                                    </select>
                                    <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                                </div>
                                {errors.catalogo_sexo_id && <p className="text-[10px] font-bold text-red-500 uppercase ml-1">{errors.catalogo_sexo_id}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Sub-sección 2: Credenciales */}
                    <div className="space-y-6 pt-4 border-t theme-border">
                        <h3 className="text-sm font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-2 pb-2">
                            <Lock className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> 
                            2. Credenciales de Acceso
                        </h3>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">TAG (Usuario)_</label>
                                <div className="relative">
                                    <span className="absolute left-4 top-1/2 -translate-y-1/2 theme-text-muted font-black pointer-events-none">@</span>
                                    <input type="text" value={data.username} onChange={e => setData('username', e.target.value.toLowerCase().replace(/\s/g, ''))} required placeholder="usuario_operativo" className="w-full pl-10 pr-4 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 shadow-sm theme-placeholder" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                </div>
                                {errors.username && <p className="text-[10px] font-bold text-red-500 uppercase ml-1">{errors.username}</p>}
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">Correo Electrónico_</label>
                                <div className="relative">
                                    <Mail className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                                    <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} required placeholder="correo@ejemplo.com" className="w-full pl-11 pr-4 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 shadow-sm theme-placeholder" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                                </div>
                                {errors.email && <p className="text-[10px] font-bold text-red-500 uppercase ml-1">{errors.email}</p>}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1">Contraseña Segura_</label>
                            <div className="relative">
                                <Lock className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                                <input type="password" value={data.password} onChange={e => setData('password', e.target.value)} required placeholder="Mínimo 8 caracteres, 1 mayúscula, 1 número" className="w-full pl-11 pr-4 py-4 theme-element border theme-border rounded-xl theme-text-main text-sm font-bold outline-none transition-all focus:ring-2 shadow-sm theme-placeholder" style={{ '--tw-ring-color': 'var(--color-primario)' }} onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} onBlur={e => e.target.style.borderColor = ''} />
                            </div>
                            {errors.password && <p className="text-[10px] font-bold text-red-500 uppercase ml-1">{errors.password}</p>}
                        </div>
                    </div>

                    {/* Sub-sección 3: Avatar */}
                    <div className="space-y-4 pt-4 border-t theme-border">
                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-main flex items-center gap-2">
                            <UserPlus className="w-4 h-4" style={{ color: 'var(--color-primario)' }}/> Foto de Perfil (Opcional)
                        </label>
                        <label className={`flex flex-col items-center justify-center w-full px-4 py-10 theme-element border-2 border-dashed ${previewUrl ? 'border-[var(--color-primario)]' : 'theme-border'} rounded-[2rem] cursor-pointer hover:shadow-md transition-all group`}>
                            {previewUrl ? (
                                <div className="flex flex-col items-center gap-3">
                                    <img src={previewUrl} alt="Preview" className="w-20 h-20 object-cover rounded-[1.25rem] shadow-md border-2 border-white dark:border-zinc-800" />
                                    <span className="text-[10px] font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>Cambiar Imagen</span>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center">
                                    <div className="w-14 h-14 theme-surface border theme-border rounded-2xl flex items-center justify-center mb-4 shadow-sm group-hover:scale-110 transition-transform">
                                        <Upload className="w-6 h-6 theme-text-muted" />
                                    </div>
                                    <span className="text-xs font-black uppercase tracking-widest theme-text-main">Examinar Archivos</span>
                                    <span className="text-[9px] font-bold text-gray-400 mt-2 uppercase tracking-widest">JPG, PNG, WEBP (Max. 2MB)</span>
                                </div>
                            )}
                            <input type="file" className="hidden" accept="image/*" onChange={handleFileChange} />
                        </label>
                        {errors.foto_perfil && <p className="text-[10px] font-bold text-red-500 uppercase ml-1 text-center mt-2">{errors.foto_perfil}</p>}
                    </div>

                    <button 
                        type="submit" 
                        disabled={processing} 
                        className="w-full py-5 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] transition-transform shadow-xl hover:scale-105 disabled:opacity-50 disabled:scale-100 outline-none flex justify-center items-center gap-3 mt-8" 
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        {processing ? (
                            'Sincronizando Identidad...'
                        ) : (
                            <>
                                <CheckCircle2 className="w-5 h-5" /> Completar Protocolo de Registro
                            </>
                        )}
                    </button>
                </form>

                <footer className="mt-8 text-center animate-page-reveal" style={{ animationDelay: '200ms' }}>
                    <p className="text-[10px] font-black theme-text-muted uppercase tracking-widest">&copy; {new Date().getFullYear()} GELIA ERP.</p>
                </footer>
            </div>

            {/* --- ESTILOS NATIVOS INYECTADOS --- */}
            <style>{`
                :root {
                    --color-primario: #ec4899; 
                }
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: rgba(250, 250, 250, 1); border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #e4e4e7; }
                .theme-placeholder::placeholder { color: #a1a1aa; }
                
                .dark .theme-surface { background-color: #121212; border-color: #222222; }
                .dark .theme-element { background-color: rgba(30, 30, 30, 1); border-color: #2A2A2A; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #27272a; }
                .dark .theme-placeholder::placeholder { color: #52525b; }

                input[type="date"]::-webkit-calendar-picker-indicator {
                    cursor: pointer;
                    opacity: 0.6;
                    transition: 0.2s;
                }
                input[type="date"]::-webkit-calendar-picker-indicator:hover {
                    opacity: 1;
                }
                .dark input[type="date"]::-webkit-calendar-picker-indicator {
                    filter: invert(1);
                }

                @keyframes pageReveal {
                    from { opacity: 0; transform: translateY(15px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .animate-page-reveal { 
                    animation: pageReveal 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
                    opacity: 0;
                }
            `}</style>
        </div>
    );
}