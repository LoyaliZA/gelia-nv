import React, { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Sidebar from '../Components/Sidebar';

export default function AppLayout({ children }) {
    const { auth } = usePage().props;

    // Leemos la memoria del navegador inmediatamente al cargar
    const [isDarkMode, setIsDarkMode] = useState(() => {
        if (typeof window !== 'undefined') {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) return savedTheme === 'dark';
        }
        return auth?.tema_visual?.modo === 'dark';
    });

    useEffect(() => {
        const root = document.documentElement;
        const tema = auth?.tema_visual || {};

        root.style.setProperty('--color-primario', tema.color_primario || '#ec4899');

        // Aplicamos el tema y lo guardamos en la memoria
        if (isDarkMode) {
            root.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            root.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }

        if (tema.fondo_url) {
            root.style.setProperty('--bg-app', `url(${tema.fondo_url})`);
            root.style.backgroundSize = 'cover';
            root.style.backgroundPosition = 'center';
            root.style.backgroundAttachment = 'fixed';
        } else {
            root.style.setProperty('--bg-app', isDarkMode ? '#0A0A0A' : '#FDFDFD');
            root.style.backgroundImage = 'none';
        }
    }, [isDarkMode, auth?.tema_visual]);

    const toggleTheme = () => setIsDarkMode(!isDarkMode);

    return (
        <div 
            className="min-h-screen text-gray-950 dark:text-white transition-colors duration-500 ease-in-out"
            style={{ background: 'var(--bg-app)' }}
        >
            <Sidebar 
                isDarkMode={isDarkMode} 
                toggleTheme={toggleTheme} 
                user={auth?.user}
                permissions={auth?.user?.permissions || []} 
            />
            
            <main className={`pt-24 pb-20 px-6 max-w-7xl mx-auto min-h-screen transition-all ${auth?.tema_visual?.fondo_url ? 'backdrop-blur-md bg-white/10 dark:bg-black/20' : 'bg-transparent'}`}>
                <div className="animate-reveal">
                    {children}
                </div>
            </main>

            <style>{`
                .animate-reveal {
                    animation: reveal 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
                @keyframes reveal {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            `}</style>
        </div>
    );
}