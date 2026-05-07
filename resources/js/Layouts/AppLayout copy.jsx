import React, { useEffect, useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import Sidebar from '../Components/Sidebar';
import { animate } from 'animejs/animation';

export default function AppLayout({ children }) {
    const { props: { auth }, url } = usePage();

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

    // --- ESCUCHADOR DE EVENTOS GLOBALES ---
    useEffect(() => {
        const syncThemeState = () => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) setIsDarkMode(savedTheme === 'dark');
        };
        
        window.addEventListener('theme-changed', syncThemeState);
        return () => window.removeEventListener('theme-changed', syncThemeState);
    }, []);

    // --- MOTOR DE PERSONALIZACIÓN (COLORES, FONDOS, FUENTES) ---
    useEffect(() => {
        const root = document.documentElement;
        const tema = auth?.tema_visual || {};
        
        // 1. Color de Énfasis
        const savedColorName = typeof window !== 'undefined' ? localStorage.getItem('theme_color') : null;
        const activeColorName = savedColorName || tema.color_nombre?.toLowerCase() || 'rosa';
        const activeAccent = activeColorName.startsWith('#') ? activeColorName : (accentColors[activeColorName] || accentColors.rosa);
        
        root.style.setProperty('--color-primario', activeAccent);

        // 2. Modo Oscuro
        if (isDarkMode) {
            root.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            root.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }

        // 3. Lógica de Fondos Multiformato
        const nombreFondo = tema.fondo_base || (typeof window !== 'undefined' ? localStorage.getItem('bg_base') : null) || 'none';

        if (nombreFondo === 'none') {
            root.style.setProperty('--bg-image-pc', 'none');
            root.style.setProperty('--bg-image-movil', 'none');
        } else if (nombreFondo.startsWith('#')) {
            root.style.setProperty('--bg-image-pc', `linear-gradient(to right, ${nombreFondo}, ${nombreFondo})`);
            root.style.setProperty('--bg-image-movil', `linear-gradient(to right, ${nombreFondo}, ${nombreFondo})`);
        } else if (nombreFondo.startsWith('data:image')) {
            root.style.setProperty('--bg-image-pc', `url(${nombreFondo})`);
            root.style.setProperty('--bg-image-movil', `url(${nombreFondo})`);
        } else {
            root.style.setProperty('--bg-image-pc', `url('/assets/backgrounds/${nombreFondo}_pc.svg')`);
            root.style.setProperty('--bg-image-movil', `url('/assets/backgrounds/${nombreFondo}_movil.svg')`);
        }

        // 4. Lógica de Tipografía Escalable
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

    }, [isDarkMode, auth?.tema_visual]);

    // --- ANIMACIÓN DE TRANSICIÓN DE PÁGINA ---
    useEffect(() => {
        animate('.page-reveal', {
            y: [15, 0],
            opacity: [0, 1],
        }, {
            duration: 800,
            easing: 'easeOutExpo'
        });
    }, [url]);

    const toggleTheme = () => {
        const newMode = !isDarkMode;
        setIsDarkMode(newMode);
        localStorage.setItem('theme', newMode ? 'dark' : 'light');
        
        window.dispatchEvent(new Event('theme-changed'));
        // router.post(route('tema.update'), { modo: newMode ? 'dark' : 'light' }, { preserveScroll: true, preserveState: true });
    };

    return (
        <div 
            className="min-h-screen text-gray-950 dark:text-gray-100 transition-colors duration-500"
            style={{ 
                backgroundColor: 'var(--bg-app)', 
                backgroundImage: 'var(--bg-actual)',
                backgroundSize: 'cover',
                backgroundPosition: 'center',
                backgroundAttachment: 'fixed',
                backgroundRepeat: 'no-repeat'
            }}
        >
            <Sidebar 
                isDarkMode={isDarkMode} 
                toggleTheme={toggleTheme} 
                user={auth?.user}
                permissions={auth?.user?.permissions || []}
            />
            
            <main className="pt-24 pb-20 px-6 max-w-7xl mx-auto min-h-screen transition-all bg-transparent">
                <div className="page-reveal">
                    {children}
                </div>
            </main>

            {/* SISTEMA DE DISEÑO GLOBAL GELIANV */}
            <style>{`
                /* --- VARIABLES CORE Y FUENTE --- */
                :root { 
                    --bg-app: #FFFFFF; 
                    --bg-actual: var(--bg-image-movil, none); 
                }
                .dark { 
                    --bg-app: #0a0a0a; 
                }
                @media (min-width: 768px) {
                    :root { --bg-actual: var(--bg-image-pc, none); }
                }

                html, body, input, select, textarea, button { 
                    font-family: var(--font-principal) !important; 
                }

                /* --- COLORES Y SUPERFICIES --- */
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #e4e4e7; }
                
                .dark .theme-surface { background-color: #121212; border-color: #222222; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #2A2A2A; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #27272a; }

                /* --- UI COMPONENTS (SWITCHES, SEGMENTS, INDICATORS) --- */
                .gelia-switch {
                    width: 3rem; height: 1.5rem; border-radius: 9999px; padding: 0.25rem;
                    background-color: #d4d4d8; transition: background-color 0.3s ease;
                    cursor: pointer; display: flex; align-items: center; border: none; outline: none;
                }
                .dark .gelia-switch { background-color: #3f3f46; }
                .gelia-switch[data-active="true"] { background-color: var(--color-primario); }
                
                .gelia-switch-thumb {
                    width: 1rem; height: 1rem; border-radius: 9999px; background-color: white;
                    box-shadow: 0 1px 2px rgba(0,0,0,0.1); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }
                .gelia-switch[data-active="true"] .gelia-switch-thumb { transform: translateX(1.5rem); }

                .gelia-segment {
                    display: flex; background-color: #f4f4f5; padding: 0.25rem; border-radius: 0.75rem; border: 1px solid #e4e4e7;
                }
                .dark .gelia-segment { background-color: #18181b; border-color: #27272a; }
                
                .gelia-segment-btn {
                    flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
                    padding: 0.5rem 1rem; font-size: 0.75rem; font-weight: 700; border-radius: 0.5rem;
                    transition: all 0.3s ease; color: #71717a; background: transparent; border: none; outline: none; cursor: pointer;
                }
                .gelia-segment-btn[data-active="true"] {
                    background-color: #ffffff; color: var(--color-primario); box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                }
                .dark .gelia-segment-btn[data-active="true"] { background-color: #27272a; }

                .gelia-leyenda { font-size: 10px; font-weight: 700; letter-spacing: 0.05em; color: var(--theme-text-muted); }
                .gelia-leyenda-azul { color: #3b82f6; font-weight: 900; margin: 0 4px; }
                .gelia-leyenda-naranja { color: #f97316; font-weight: 900; margin: 0 4px; }

                .gelia-estado-heredado { border-color: rgba(59, 130, 246, 0.3) !important; background-color: rgba(59, 130, 246, 0.1) !important; color: #2563eb !important; cursor: not-allowed; }
                .gelia-estado-directo { border-color: #f97316 !important; background-color: rgba(249, 115, 22, 0.1) !important; color: #ea580c !important; }

                /* --- SCROLLBARS Y MODALES --- */
                ::-webkit-scrollbar { width: 8px; }
                ::-webkit-scrollbar-thumb { background-color: rgba(156, 163, 175, 0.4); border-radius: 20px; }
                ::-webkit-scrollbar-thumb:hover { background-color: var(--color-primario); }
                
                @keyframes scaleUp {
                    0% { opacity: 0; transform: scale(0.98); }
                    100% { opacity: 1; transform: scale(1); }
                }
                .modal-pop { animation: scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
            `}</style>
        </div>
    );
}