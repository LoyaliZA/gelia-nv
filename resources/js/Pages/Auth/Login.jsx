import React, { useEffect, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { User, Lock, LogIn, Sun, Moon, Sparkles } from 'lucide-react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        login: '',
        password: '',
        remember: true, 
    });

    // Estado Reactivo 
    const [isDarkMode, setIsDarkMode] = useState(false);

    useEffect(() => {
        // Leemos la memoria del navegador 
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        const isDark = savedTheme === 'dark' || (!savedTheme && systemPrefersDark);
        setIsDarkMode(isDark);

        // Cargamos el color de acento
        const savedColor = localStorage.getItem('theme_color') || 'rosa';
        const accentColors = {
            rosa: '#ec4899',
            azul: '#3b82f6',
            verde: '#10b981',
            amarillo: '#f59e0b'
        };
        document.documentElement.style.setProperty('--color-primario', accentColors[savedColor] || accentColors.rosa);
    }, []);

    // Función de cambio manual
    const toggleTheme = () => {
        const newMode = !isDarkMode;
        setIsDarkMode(newMode);
        localStorage.setItem('theme', newMode ? 'dark' : 'light');
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <div className={`min-h-screen flex flex-col items-center justify-center p-6 transition-colors duration-500 relative overflow-hidden ${isDarkMode ? 'bg-[#0a0a0a] text-white' : 'bg-zinc-50 text-zinc-900'}`}>
            <Head title="Iniciar Sesión - GELIA" />

            {/* BOTÓN FLOTANTE DE TEMA */}
            <button 
                onClick={toggleTheme}
                type="button"
                className={`absolute top-6 right-6 p-4 backdrop-blur-md rounded-2xl shadow-sm border transition-all z-50 hover:scale-105 ${isDarkMode ? 'bg-[#141414]/60 border-zinc-800 text-zinc-400 hover:text-[var(--color-primario)]' : 'bg-white/60 border-zinc-200 text-zinc-500 hover:text-[var(--color-primario)]'}`}
                title="Cambiar Modo de Visualización"
            >
                {isDarkMode ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
            </button>

            {/* DECORACIÓN DE FONDO */}
            <div 
                className={`absolute top-0 right-0 w-[600px] h-[600px] rounded-full blur-[120px] pointer-events-none translate-x-1/3 -translate-y-1/3 transition-colors duration-700 ${isDarkMode ? 'opacity-20' : 'opacity-10'}`}
                style={{ backgroundColor: 'var(--color-primario)' }}
            ></div>
            <div 
                className={`absolute bottom-0 left-0 w-[400px] h-[400px] rounded-full blur-[100px] pointer-events-none -translate-x-1/3 translate-y-1/3 transition-colors duration-700 ${isDarkMode ? 'opacity-10' : 'opacity-[0.05]'}`}
                style={{ backgroundColor: 'var(--color-primario)' }}
            ></div>

            <div className="relative z-10 w-full max-w-md">
                {/* Cabecera institucional */}
                <div className="flex items-center justify-center gap-8 mb-10">
                    <img 
                        src="/Images/Logos/aromas_logo_negro.png" 
                        alt="Logo Aromas" 
                        className={`h-20 md:h-24 object-contain transition-all hover:scale-105 duration-300 ${isDarkMode ? 'invert' : ''}`} 
                    />
                    <div className={`h-16 w-px ${isDarkMode ? 'bg-zinc-800' : 'bg-zinc-300'}`}></div>
                    <img 
                        src="/Images/Logos/bellaroma_logo_negro.png" 
                        alt="Logo Bellaroma" 
                        className={`h-20 md:h-24 object-contain transition-all hover:scale-105 duration-300 ${isDarkMode ? 'invert' : ''}`} 
                    />
                </div>

                {/* Contenedor del formulario */}
                <div className={`backdrop-blur-xl p-8 md:p-10 rounded-[2.5rem] border transition-colors duration-300 ${isDarkMode ? 'bg-[#141414]/80 shadow-2xl border-zinc-800/50' : 'bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border-white/20'}`}>
                    <div className="mb-10 text-center">
                        <h1 className="text-3xl font-black italic tracking-tighter mb-2">Bienvenido a GELIA</h1>
                        <p className={`text-xs font-bold ${isDarkMode ? 'text-zinc-400' : 'text-zinc-500'}`}>Ingresa con tu correo, usuario o nombre.</p>
                    </div>
                    
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Input de Usuario */}
                        <div className="space-y-2">
                            <label className={`text-[10px] font-black uppercase tracking-widest italic ml-2 block ${isDarkMode ? 'text-zinc-400' : 'text-zinc-500'}`}>
                                Usuario, Correo o Nombre_
                            </label>
                            <div className="relative group">
                                <User className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-zinc-400 group-focus-within:text-[var(--color-primario)] transition-colors" />
                                <input 
                                    type="text" 
                                    value={data.login || ''}
                                    onChange={e => setData('login', e.target.value)}
                                    className={`w-full pl-12 pr-4 py-4 border-2 border-transparent rounded-2xl text-sm font-bold outline-none transition-all ${isDarkMode ? 'bg-[#1A1A1A] focus:bg-black text-white' : 'bg-zinc-50/50 focus:bg-white text-zinc-900'}`}
                                    onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'}
                                    onBlur={(e) => e.target.style.borderColor = 'transparent'}
                                    required
                                />
                            </div>
                            {errors.login && <p className="text-red-500 text-[10px] font-bold mt-1 ml-2 uppercase">{errors.login}</p>}
                        </div>

                        {/* Input de Contraseña */}
                        <div className="space-y-2">
                            <label className={`text-[10px] font-black uppercase tracking-widest italic ml-2 block ${isDarkMode ? 'text-zinc-400' : 'text-zinc-500'}`}>
                                Contraseña_
                            </label>
                            <div className="relative group">
                                <Lock className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-zinc-400 group-focus-within:text-[var(--color-primario)] transition-colors" />
                                <input 
                                    type="password" 
                                    value={data.password || ''}
                                    onChange={e => setData('password', e.target.value)}
                                    className={`w-full pl-12 pr-4 py-4 border-2 border-transparent rounded-2xl text-sm font-bold outline-none transition-all ${isDarkMode ? 'bg-[#1A1A1A] focus:bg-black text-white' : 'bg-zinc-50/50 focus:bg-white text-zinc-900'}`}
                                    onFocus={(e) => e.target.style.borderColor = 'var(--color-primario)'}
                                    onBlur={(e) => e.target.style.borderColor = 'transparent'}
                                    required
                                />
                            </div>
                            {errors.password && <p className="text-red-500 text-[10px] font-bold mt-1 ml-2 uppercase">{errors.password}</p>}
                        </div>

                        {/* Botón de Submit */}
                        <button 
                            type="submit" 
                            disabled={processing}
                            className="w-full py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all flex justify-center items-center gap-3 mt-8 hover:scale-[1.02] shadow-lg disabled:opacity-50 disabled:hover:scale-100"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            {processing ? <Sparkles className="w-4 h-4 animate-pulse" /> : <LogIn className="w-4 h-4" />}
                            {processing ? 'Verificando Identidad...' : 'Iniciar Sesión'}
                        </button>
                    </form>
                </div>

                <footer className={`mt-12 text-center transition-colors ${isDarkMode ? 'text-zinc-600' : 'text-zinc-400'}`}>
                    <p className="text-[10px] font-black uppercase tracking-[0.2em]">
                        &copy; {new Date().getFullYear()} GELIA.
                    </p>
                    <p className="mt-1 text-[10px] font-bold italic">
                        Creado por Gabriel Zárate.
                    </p>
                </footer>
            </div>
        </div>
    );
}