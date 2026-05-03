import React, { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Sidebar from '../Components/Sidebar';

export default function AppLayout({ children }) {
    const { auth } = usePage().props;
    const [isDarkMode, setIsDarkMode] = useState(false);

    useEffect(() => {
        const root = document.documentElement;
        
        /* Modificación: Uso de encadenamiento opcional para prevenir el fallo */
        const tema = auth?.tema_visual || {};

        if (tema.color_primario) {
            root.style.setProperty('--color-primario', tema.color_primario);
        } else {
            root.style.setProperty('--color-primario', '#ec4899');
        }

        if (tema.color_fondo) {
            root.style.setProperty('--color-fondo', tema.color_fondo);
        } else {
            root.style.setProperty('--color-fondo', '#FDFDFD');
        }

        if (tema.modo === 'dark') {
            setIsDarkMode(true);
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    }, [auth?.tema_visual]); /* Modificación en la dependencia del hook */

    const toggleTheme = () => {
        const root = document.documentElement;
        setIsDarkMode(!isDarkMode);
        root.classList.toggle('dark');
    };

    return (
        <div 
            className="min-h-screen text-gray-800 transition-colors duration-400"
            style={{ backgroundColor: 'var(--color-fondo)' }}
        >
            {/* Modificación: Protección de la variable user enviada al Sidebar */}
            <Sidebar isDarkMode={isDarkMode} toggleTheme={toggleTheme} user={auth?.user} />

            <main className="pt-24 pb-20 px-6 max-w-6xl mx-auto">
                {children}
            </main>
        </div>
    );
}