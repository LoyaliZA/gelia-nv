import React, { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Sidebar from '../Components/Sidebar';

export default function AppLayout({ children }) {
    const { auth } = usePage().props;
    const [isDarkMode, setIsDarkMode] = useState(false);

    useEffect(() => {
        const root = document.documentElement;
        
        // Obtenemos el JSON de configuración del usuario
        const tema = auth?.tema_visual || {};

        // Inyección de variables CSS Dinámicas
        root.style.setProperty('--color-primario', tema.color_primario || '#ec4899');
        root.style.setProperty('--color-hover', tema.color_hover || '#be185d');
        root.style.setProperty('--color-alerta', tema.color_alerta || '#ef4444');
        root.style.setProperty('--color-confirmacion', tema.color_confirmacion || '#10b981');
        
        // Manejo de fondo (Color sólido o Imagen)
        if (tema.fondo_url) {
            root.style.setProperty('--bg-app', `url(${tema.fondo_url})`);
            root.style.backgroundSize = 'cover';
            root.style.backgroundPosition = 'center';
            root.style.backgroundAttachment = 'fixed';
        } else {
            root.style.setProperty('--bg-app', tema.color_fondo || '#FDFDFD');
            root.style.backgroundImage = 'none'; // Limpiamos por si había imagen
        }

        // Manejo del modo oscuro
        if (tema.modo === 'dark') {
            setIsDarkMode(true);
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    }, [auth?.tema_visual]);

    const toggleTheme = () => {
        // Aquí deberías hacer una petición Axios/Inertia al backend para guardar el cambio de modo oscuro
        const root = document.documentElement;
        setIsDarkMode(!isDarkMode);
        root.classList.toggle('dark');
    };

    return (
        <div 
            className="min-h-screen text-gray-800 transition-colors duration-400"
            style={{ background: 'var(--bg-app)' }}
        >
            <Sidebar isDarkMode={isDarkMode} toggleTheme={toggleTheme} user={auth?.user} />
            
            {/* Contenedor principal con efecto glassmorphism para que el texto sea legible si hay imagen de fondo */}
            <main className="pt-24 pb-20 px-6 max-w-6xl mx-auto backdrop-blur-sm bg-white/30 dark:bg-black/30 min-h-screen rounded-lg mt-4 shadow-lg">
                {children}
            </main>
        </div>
    );
}