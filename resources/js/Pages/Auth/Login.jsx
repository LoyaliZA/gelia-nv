import React, { useEffect, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { User, Lock, LogIn, Sun, Moon, Eye, EyeOff, Loader2 } from 'lucide-react';
import GeliaLogo from '../../Components/GeliaLogo';

const ACCENT_COLORS = {
    rosa: '#ec4899',
    azul: '#3b82f6',
    verde: '#10b981',
    amarillo: '#f59e0b',
};

function applyLoginTheme(isDark) {
    const root = document.documentElement;
    root.classList.toggle('dark', isDark);
    localStorage.setItem('theme', isDark ? 'dark' : 'light');

    const savedColor = localStorage.getItem('theme_color') || 'rosa';
    root.style.setProperty('--color-primario', ACCENT_COLORS[savedColor] || ACCENT_COLORS.rosa);

    const glass = localStorage.getItem('theme_glass');
    if (glass === 'true') root.classList.add('glass-active');
    else root.classList.remove('glass-active');
}

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        login: '',
        password: '',
        remember: true,
    });

    const [isDarkMode, setIsDarkMode] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    useEffect(() => {
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches;
        const isDark = savedTheme === 'dark' || (!savedTheme && systemPrefersDark);
        setIsDarkMode(isDark);
        applyLoginTheme(isDark);
    }, []);

    const toggleTheme = () => {
        const next = !isDarkMode;
        setIsDarkMode(next);
        applyLoginTheme(next);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <div className="gelia-login-split">
            <Head title="Iniciar sesión | GELIA" />

            {/* Panel visual — marca */}
            <aside className="gelia-login-split__brand" aria-label="GELIA">
                <div className="gelia-login-split__brand-glow gelia-login-split__brand-glow--1" aria-hidden />
                <div className="gelia-login-split__brand-glow gelia-login-split__brand-glow--2" aria-hidden />

                <div className="gelia-login-split__brand-inner">
                    <GeliaLogo variant="sparkle" className="w-20 h-20 md:w-24 md:h-24 drop-shadow-lg" accentColor="#ffffff" />

                    <div>
                        <p className="text-[10px] font-black uppercase tracking-[0.35em] text-white/70 m-0 mb-2">
                            Nueva Versión
                        </p>
                        <h1 className="text-3xl md:text-4xl lg:text-5xl font-black italic uppercase tracking-tighter m-0 leading-none text-white">
                            G.E.L.I.A.
                        </h1>
                    </div>

                    <div className="flex items-center justify-center gap-6 md:gap-8 w-full pt-2">
                        <img
                            src="/Images/Logos/aromas_logo_negro.png"
                            alt="Aromas del Valle"
                            className="h-16 sm:h-[4.5rem] md:h-20 lg:h-[5.5rem] max-w-[45%] object-contain invert brightness-110 opacity-90 hover:opacity-100 transition-opacity"
                        />
                        <div className="h-12 md:h-16 w-px bg-white/25 shrink-0" aria-hidden />
                        <img
                            src="/Images/Logos/bellaroma_logo_negro.png"
                            alt="Bellaroma"
                            className="h-16 sm:h-[4.5rem] md:h-20 lg:h-[5.5rem] max-w-[45%] object-contain invert brightness-110 opacity-90 hover:opacity-100 transition-opacity"
                        />
                    </div>
                </div>
            </aside>

            {/* Panel del formulario */}
            <main className="gelia-login-split__form">
                <button
                    type="button"
                    onClick={toggleTheme}
                    className="absolute top-5 right-5 lg:top-6 lg:right-6 z-10 p-3 rounded-2xl theme-element border theme-border theme-text-muted hover:text-[var(--color-primario)] transition-all hover:scale-105 outline-none shadow-sm"
                    title={isDarkMode ? 'Modo claro' : 'Modo oscuro'}
                    aria-label={isDarkMode ? 'Activar modo claro' : 'Activar modo oscuro'}
                >
                    {isDarkMode ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
                </button>

                <div className="gelia-login-split__form-inner">
                    <header className="mb-8 md:mb-10">
                        <h2 className="text-2xl md:text-3xl font-black italic uppercase tracking-tighter theme-text-main m-0 leading-tight">
                            Iniciar <span style={{ color: 'var(--color-primario)' }}>sesión</span>
                        </h2>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-3 m-0">
                            Usa tu correo, usuario o nombre de acceso
                        </p>
                    </header>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <label htmlFor="login" className="theme-label ml-1">
                                Usuario o correo
                            </label>
                            <div className="theme-field-with-icon mt-1.5">
                                <User className="theme-field-icon" aria-hidden />
                                <input
                                    id="login"
                                    type="text"
                                    autoComplete="username"
                                    value={data.login}
                                    onChange={(e) => setData('login', e.target.value)}
                                    className="theme-input py-3.5"
                                    placeholder="usuario@empresa.com"
                                    required
                                />
                            </div>
                            {errors.login && (
                                <p className="text-red-500 text-[10px] font-bold mt-1.5 ml-1 uppercase">{errors.login}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="password" className="theme-label ml-1">
                                Contraseña
                            </label>
                            <div className="theme-field-with-icon theme-field-with-icon--password mt-1.5">
                                <Lock className="theme-field-icon" aria-hidden />
                                <input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="theme-input py-3.5"
                                    placeholder="••••••••"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((v) => !v)}
                                    className="theme-field-with-icon__action theme-btn-icon p-2"
                                    aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                    tabIndex={-1}
                                >
                                    {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                </button>
                            </div>
                            {errors.password && (
                                <p className="text-red-500 text-[10px] font-bold mt-1.5 ml-1 uppercase">{errors.password}</p>
                            )}
                        </div>

                        <label className="flex items-center gap-3 cursor-pointer group py-1">
                            <input
                                type="checkbox"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                                className="w-4 h-4 rounded border theme-border accent-[var(--color-primario)] cursor-pointer"
                            />
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted group-hover:theme-text-main transition-colors">
                                Mantener sesión iniciada
                            </span>
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            className="theme-btn-primary w-full py-4 mt-2 disabled:hover:scale-100"
                        >
                            {processing ? (
                                <Loader2 className="w-4 h-4 animate-spin shrink-0" />
                            ) : (
                                <LogIn className="w-4 h-4 shrink-0" />
                            )}
                            {processing ? 'Verificando…' : 'Iniciar sesión'}
                        </button>
                    </form>

                    <p className="text-[10px] font-black uppercase tracking-[0.2em] theme-text-muted text-center mt-10 m-0">
                        &copy; {new Date().getFullYear()} GELIA
                    </p>
                </div>
            </main>
        </div>
    );
}
