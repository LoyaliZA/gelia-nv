import React, { useEffect, useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import Sidebar from '../Components/Sidebar';
import { animate } from 'animejs/animation';

export default function AppLayout({ children }) {
    // Extraemos la 'url' para saber cuándo cambiamos realmente de página
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

    // ESCUCHADOR DE EVENTOS GLOBALES (El puente de comunicación)
    useEffect(() => {
        const syncThemeState = () => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) setIsDarkMode(savedTheme === 'dark');
        };
        
        window.addEventListener('theme-changed', syncThemeState);
        return () => window.removeEventListener('theme-changed', syncThemeState);
    }, []);

    useEffect(() => {
        const root = document.documentElement;
        const tema = auth?.tema_visual || {};
        
        const savedColorName = typeof window !== 'undefined' ? localStorage.getItem('theme_color') : null;
        const activeColorName = savedColorName || tema.color_nombre?.toLowerCase() || 'rosa';
        const activeAccent = accentColors[activeColorName] || accentColors.rosa;
        
        root.style.setProperty('--color-primario', activeAccent);

        if (isDarkMode) {
            root.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            root.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }

        if (tema.fondo_url) {
            root.style.setProperty('--bg-image', `url(${tema.fondo_url})`);
        } else {
            root.style.setProperty('--bg-image', 'none');
        }

    }, [isDarkMode, auth?.tema_visual]);

    // ✅ AQUÍ ESTÁ LA SOLUCIÓN DEL FLASHAZO
    useEffect(() => {
        animate('.page-reveal', {
            y: [15, 0],
            opacity: [0, 1],
        }, {
            duration: 800,
            easing: 'easeOutExpo'
        });
    }, [url]); // <--- Ahora SOLO se anima cuando cambia la URL, jamás al teclear

    // Función del Sidebar mejorada (Avisa a otras vistas y guarda en BD)
    const toggleTheme = () => {
        const newMode = !isDarkMode;
        setIsDarkMode(newMode);
        localStorage.setItem('theme', newMode ? 'dark' : 'light');
        
        window.dispatchEvent(new Event('theme-changed'));
        
        // Guarda silenciosamente al tocar la luna/sol del Sidebar
        router.post(route('tema.update'), { modo: newMode ? 'dark' : 'light' }, { preserveScroll: true, preserveState: true });
    };

    return (
        <div 
            className="min-h-screen text-gray-950 dark:text-gray-100"
            style={{ 
                backgroundColor: 'var(--bg-app)', 
                backgroundImage: 'var(--bg-image)',
                backgroundSize: 'cover',
                backgroundPosition: 'center',
                backgroundAttachment: 'fixed'
            }}
        >
            <Sidebar 
                isDarkMode={isDarkMode} 
                toggleTheme={toggleTheme} 
                user={auth?.user}
                permissions={auth?.user?.permissions || []}
            />
            
            <main className={`pt-24 pb-20 px-6 max-w-7xl mx-auto min-h-screen transition-all ${
                auth?.tema_visual?.fondo_url 
                ? 'backdrop-blur-md bg-white/10 dark:bg-black/40' 
                : 'bg-transparent'
            }`}>
                <div className="page-reveal">
                    {children}
                </div>
            </main>

            <style>{`
                :root { --bg-app: #FFFFFF; }
                .dark { --bg-app: #000000; }
                ::-webkit-scrollbar { width: 8px; }
                ::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }
                .dark ::-webkit-scrollbar-thumb { background: #222; }
                ::-webkit-scrollbar-thumb:hover { background: var(--color-primario); }
            `}</style>
        </div>
    );
}