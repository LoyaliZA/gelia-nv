import React, { useEffect, useState, createContext, useContext } from 'react';
import { usePage, router } from '@inertiajs/react';
import Sidebar from '../Components/Sidebar';
// ELIMINADO: import { animate } from 'animejs/animation';
import { Bell, X } from 'lucide-react';

import NotificationService from '../Services/NotificationBrowserService';
import GeliaLoader from '../Components/GeliaLoader'; // <-- 1. IMPORTAMOS EL LOADER

const ModalContext = createContext();
export const useModal = () => useContext(ModalContext);

export default function AppLayout({ children }) {
    const { props: { auth }, url } = usePage();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalContent, setModalContent] = useState(null);

    // ESTADO PARA TOASTS
    const [toasts, setToasts] = useState([]);

    // --- 2. ESTADOS GLOBALES PARA EL LOADER ---
    const [isGlobalLoading, setIsGlobalLoading] = useState(false);
    const [globalProgress, setGlobalProgress] = useState(null);

    const addToast = (notif) => {
        const id = Date.now();
        setToasts(prev => [...prev, { id, ...notif }]);
        setTimeout(() => {
            setToasts(prev => prev.filter(t => t.id !== id));
        }, 5000);
    };

    // INICIALIZAR PERMISOS AL CARGAR LA APP
    useEffect(() => {
        NotificationService.requestDesktopPermissions();
    }, []);

    // --- ESCUCHADORES DE EVENTOS GLOBALES DE INERTIA ---
    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            // REGLA: Si la petición indica showProgress: false, ignoramos el loader
            if (event.detail.visit.showProgress === false) return;
            
            setIsGlobalLoading(true);
            setGlobalProgress(null);
        });

        const removeProgress = router.on('progress', (event) => {
            if (event.detail.visit.showProgress === false) return;
            
            if (event.detail.progress && event.detail.progress.percentage) {
                setGlobalProgress(event.detail.progress.percentage);
            }
        });

        const removeFinish = router.on('finish', () => {
            // Siempre apagamos por seguridad al terminar cualquier ciclo
            setIsGlobalLoading(false);
            setGlobalProgress(null);
        });

        return () => {
            removeStart();
            removeProgress();
            removeFinish();
        };
    }, []);

    // REFACTORIZACIÓN DEL ESCUCHADOR DE REVERB
    useEffect(() => {
        if (auth?.user && typeof window !== 'undefined' && window.Echo) {
            const channelName = `App.Models.User.${auth.user.id}`;
            
            window.Echo.leave(channelName);

            window.Echo.private(channelName)
                .notification((notification) => {
                    addToast({ mensaje: notification.mensaje });
                    NotificationService.triggerFullAlert('GELIA ERP', notification.mensaje);
                    router.reload({ only: ['auth'], preserveScroll: true, preserveState: true });
                });
        }

        return () => {
            if (auth?.user && typeof window !== 'undefined' && window.Echo) {
                window.Echo.leave(`App.Models.User.${auth.user.id}`);
            }
        };
    }, [auth?.user]);

    const accentColors = {
        rosa: '#ec4899',
        azul: '#3b82f6',
        verde: '#10b981',
        amarillo: '#f59e0b'
    };

    const [isDarkMode, setIsDarkMode] = useState(() => {
        if (typeof window !== 'undefined') {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) return savedTheme === 'dark';
        }
        return auth?.tema_visual?.modo === 'dark';
    });

    const [sidebarLayout, setSidebarLayout] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('theme_layout') || auth?.tema_visual?.layout_sidebar || 'floating_left';
        return 'floating_left';
    });

    const openModal = (content) => {
        setModalContent(content);
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setTimeout(() => setModalContent(null), 300);
    };

    useEffect(() => {
        if (isModalOpen) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = 'unset';
        return () => { document.body.style.overflow = 'unset'; };
    }, [isModalOpen]);

    useEffect(() => {
        const syncThemeState = () => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) setIsDarkMode(savedTheme === 'dark');

            const savedLayout = localStorage.getItem('theme_layout');
            if (savedLayout) setSidebarLayout(savedLayout);
        };
        window.addEventListener('theme-changed', syncThemeState);
        return () => window.removeEventListener('theme-changed', syncThemeState);
    }, []);

    useEffect(() => {
        const root = document.documentElement;
        const tema = auth?.tema_visual || {};

        const savedColorName = typeof window !== 'undefined' ? localStorage.getItem('theme_color') : null;
        const activeColorName = savedColorName || tema.color_nombre?.toLowerCase() || 'rosa';
        const activeAccent = activeColorName.startsWith('#') ? activeColorName : (accentColors[activeColorName] || accentColors.rosa);
        root.style.setProperty('--color-primario', activeAccent);

        if (isDarkMode) {
            root.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            root.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }

        const nombreFondo = tema.fondo_base || (typeof window !== 'undefined' ? localStorage.getItem('bg_base') : null) || 'none';
        if (nombreFondo === 'none') {
            root.style.setProperty('--bg-image-pc', 'none');
            root.style.setProperty('--bg-image-movil', 'none');
        } else if (nombreFondo.startsWith('#')) {
            root.style.setProperty('--bg-image-pc', `linear-gradient(to right, ${nombreFondo}, ${nombreFondo})`);
            root.style.setProperty('--bg-image-movil', `linear-gradient(to right, ${nombreFondo}, ${nombreFondo})`);
        } else if (nombreFondo.startsWith('data:image') || nombreFondo.startsWith('/storage')) {
            root.style.setProperty('--bg-image-pc', `url(${nombreFondo})`);
            root.style.setProperty('--bg-image-movil', `url(${nombreFondo})`);
        } else {
            root.style.setProperty('--bg-image-pc', `url('/assets/backgrounds/${nombreFondo}_pc.svg')`);
            root.style.setProperty('--bg-image-movil', `url('/assets/backgrounds/${nombreFondo}_movil.svg')`);
        }

        const savedFont = typeof window !== 'undefined' ? localStorage.getItem('theme_font') : null;
        const activeFont = savedFont || tema.fuente_principal || 'inter';
        const fontMap = {
            inter: "'Inter', sans-serif",
            montserrat: "'Montserrat', sans-serif",
            poppins: "'Poppins', sans-serif",
            nunito: "'Nunito', sans-serif",
            roboto: "'Roboto', sans-serif",
            mono: "'JetBrains Mono', monospace"
        };
        root.style.setProperty('--font-principal', fontMap[activeFont] || fontMap.inter);

        const savedGlass = typeof window !== 'undefined' ? localStorage.getItem('theme_glass') : null;
        const isGlassActive = savedGlass !== null
            ? savedGlass === 'true'
            : (String(tema.efecto_cristal) !== '0' && tema.efecto_cristal !== false);

        if (isGlassActive) root.classList.add('glass-active');
        else root.classList.remove('glass-active');

    }, [isDarkMode, auth?.tema_visual]);

    const toggleTheme = () => {
        const newMode = !isDarkMode;
        setIsDarkMode(newMode);
        localStorage.setItem('theme', newMode ? 'dark' : 'light');
        window.dispatchEvent(new Event('theme-changed'));
    };

    return (
        <ModalContext.Provider value={{ openModal, closeModal }}>
            <div
                className="min-h-dvh text-gray-950 dark:text-gray-100 transition-colors duration-500 w-full overflow-x-hidden"
                style={{
                    backgroundColor: 'var(--bg-app)',
                    backgroundImage: 'var(--bg-actual)',
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    backgroundAttachment: 'fixed',
                    backgroundRepeat: 'no-repeat'
                }}
            >
                {/* --- 4. INSTANCIA ÚNICA DEL LOADER --- */}
                <GeliaLoader 
                    isVisible={isGlobalLoading} 
                    progress={globalProgress} 
                    message="Procesando_" 
                />

                <Sidebar
                    isDarkMode={isDarkMode}
                    toggleTheme={toggleTheme}
                    user={auth?.user}
                    permissions={auth?.user?.permissions || []}
                    layout={sidebarLayout}
                />

                <main className={`transition-all duration-500 bg-transparent max-w-7xl mx-auto min-h-screen px-4 md:px-6 pb-32 md:pb-20 ${sidebarLayout === 'fixed' ? 'md:ml-24 pt-6 md:pt-12' : 'pt-6 md:pt-24'}`}>
                    {/* Al usar key={url}, React desmonta y monta este div en cada cambio de ruta, disparando la animación CSS nativa */}
                    <div key={url} className="animate-page-reveal">
                        {children}
                    </div>
                </main>

                {/* MODAL GLOBAL */}
                {isModalOpen && (
                    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-md animate-fade-in" onClick={closeModal}>
                        <div className="w-full max-w-lg bg-white dark:bg-[#121212] rounded-[2rem] shadow-2xl p-6 modal-pop border border-gray-200 dark:border-[#222222] max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                            {modalContent}
                        </div>
                    </div>
                )}

                {/* TOASTS FLOTANTES */}
                <div className="fixed top-6 right-6 z-[10000] flex flex-col gap-3 w-full max-w-sm pointer-events-none">
                    {toasts.map((toast) => (
                        <div key={toast.id} className="pointer-events-auto theme-surface border theme-border shadow-2xl rounded-2xl p-4 flex items-start gap-4 animate-slide-in-right overflow-hidden relative group">
                            <div className="absolute bottom-0 left-0 h-1 bg-[var(--color-primario)] animate-progress-shrink" />
                            <div className="p-2 rounded-xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                <Bell className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            </div>
                            <div className="flex-1">
                                <p className="text-[10px] font-black uppercase tracking-widest text-[var(--color-primario)] mb-0.5">Nueva Alerta_</p>
                                <p className="text-xs font-bold theme-text-main leading-snug">{toast.mensaje}</p>
                            </div>
                            <button onClick={() => setToasts(prev => prev.filter(t => t.id !== toast.id))} className="theme-text-muted hover:theme-text-main transition-colors outline-none">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                    ))}
                </div>

                <style>{`
                    :root { --bg-app: #FFFFFF; --bg-actual: var(--bg-image-movil, none); }
                    .dark { --bg-app: #0a0a0a; }
                    @media (min-width: 768px) { :root { --bg-actual: var(--bg-image-pc, none); } }
                    html, body, input, select, textarea, button { font-family: var(--font-principal) !important; }

                    .theme-surface { background-color: #ffffff; border-color: #f4f4f5; transition: background-color 0.3s, border-color 0.3s, backdrop-filter 0.3s; }
                    .theme-element { background-color: rgba(250, 250, 250, 1) !important; border-color: #e4e4e7 !important; transition: background-color 0.3s, border-color 0.3s, backdrop-filter 0.3s; }
                    .theme-text-main { color: #18181b; }
                    .theme-text-muted { color: #71717a; }
                    .theme-border { border-color: #e4e4e7; }
                    .theme-placeholder::placeholder { color: #a1a1aa; }
                    
                    .dark .theme-surface { background-color: #121212; border-color: #222222; }
                    .dark .theme-element { background-color: rgba(30, 30, 30, 1) !important; border-color: #2A2A2A !important; }
                    .dark .theme-text-main { color: #ffffff; }
                    .dark .theme-text-muted { color: #a1a1aa; }
                    .dark .theme-border { border-color: #27272a; }
                    .dark .theme-placeholder::placeholder { color: #52525b; }

                    html.glass-active .theme-surface, html.glass-active .theme-element, html.glass-active .sidebar-glass { backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
                    html.glass-active .theme-surface { background-color: rgba(255, 255, 255, 0.75); border-color: rgba(255, 255, 255, 0.8); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); }
                    html.glass-active .theme-element { background-color: rgba(250, 250, 250, 0.85) !important; border-color: rgba(255, 255, 255, 0.6) !important; }
                    html.dark.glass-active .theme-surface { background-color: rgba(18, 18, 18, 0.7); border-color: rgba(255, 255, 255, 0.08); box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.5); }
                    html.dark.glass-active .theme-element { background-color: rgba(30, 30, 30, 0.85) !important; border-color: rgba(255, 255, 255, 0.05) !important; }

                    .gelia-switch { width: 3rem; height: 1.5rem; border-radius: 9999px; padding: 0.25rem; background-color: #d4d4d8; transition: background-color 0.3s ease; cursor: pointer; display: flex; align-items: center; border: none; outline: none; }
                    .dark .gelia-switch { background-color: #3f3f46; }
                    .gelia-switch[data-active="true"] { background-color: var(--color-primario); }
                    .gelia-switch-thumb { width: 1rem; height: 1rem; border-radius: 9999px; background-color: white; box-shadow: 0 1px 2px rgba(0,0,0,0.1); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
                    .gelia-switch[data-active="true"] .gelia-switch-thumb { transform: translateX(1.5rem); }

                    .gelia-segment { display: flex; background-color: #f4f4f5; padding: 0.25rem; border-radius: 0.75rem; border: 1px solid #e4e4e7; }
                    .dark .gelia-segment { background-color: #18181b; border-color: #27272a; }
                    .gelia-segment-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem 1rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.5rem; transition: all 0.3s ease; color: #71717a; background: transparent; border: none; outline: none; cursor: pointer; }
                    .gelia-segment-btn[data-active="true"] { background-color: #ffffff; color: var(--color-primario); box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
                    .dark .gelia-segment-btn[data-active="true"] { background-color: #27272a; }

                    ::-webkit-scrollbar { width: 8px; }
                    ::-webkit-scrollbar-track { background: transparent; }
                    ::-webkit-scrollbar-thumb { background-color: rgba(156, 163, 175, 0.4); border-radius: 20px; }
                    ::-webkit-scrollbar-thumb:hover { background-color: var(--color-primario); }
                    
                    @keyframes scaleUp { 0% { opacity: 0; transform: scale(0.95); } 100% { opacity: 1; transform: scale(1); } }
                    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                    @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
                    @keyframes progressShrink { from { width: 100%; } to { width: 0%; } }
                    
                    /* --- ANIMACIÓN NATIVA DE TRANSICIÓN DE PÁGINA --- */
                    @keyframes pageReveal {
                        from { opacity: 0; transform: translateY(15px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    .animate-page-reveal { 
                        animation: pageReveal 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
                    }
                    
                    .modal-pop { animation: scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
                    .animate-fade-in { animation: fadeIn 0.2s ease-out forwards; }
                    .animate-slide-in-right { animation: slideInRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
                    .animate-progress-shrink { animation: progressShrink 5s linear forwards; }
                `}</style>
            </div>
        </ModalContext.Provider>
    );
}