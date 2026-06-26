import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import GeliaLogo from '@/Components/GeliaLogo';
import { Home, RefreshCcw } from 'lucide-react'; // Iconos para los botones

export default function Error({ status, returnUrl = null }) {
    const reintentar = () => {
        const destino = returnUrl || (typeof document !== 'undefined' ? document.referrer : '') || '/dashboard';
        if (destino && destino !== window.location.href) {
            router.visit(destino);
            return;
        }
        if (window.history.length > 1) {
            window.history.back();
            return;
        }
        router.visit('/dashboard');
    };
    // 1. Mapeo de Títulos y Mensajes solicitados
    const titleMap = {
        503: '503: Servicio No Disponible',
        500: '500: Error Interno del Servidor',
        404: '404: Página No Encontrada',
        403: '403: Acceso Prohibido',
    };

    const descriptionMap = {
        503: 'Lo siento, estamos haciendo mantenimiento. Por favor, vuelve pronto.',
        500: 'Lo siento, algo salió mal en nuestros servidores. Estamos trabajando en ello.',
        // Mensaje solicitado por el usuario
        404: 'Lo siento, la página que buscas no existe o está en otro sitio.', 
        // Mensaje solicitado por el usuario
        403: 'Lo siento, no tienes acceso a esa función.', 
    };

    const title = titleMap[status] || `Error ${status}`;
    const description = descriptionMap[status] || 'Lo siento, ha ocurrido un error inesperado.';

    return (
        <>
            <Head title={title} />
            
            {/* Contenedor principal: Fondo tenue (oscuro si el tema es oscuro) */}
            <div className="min-h-screen flex items-center justify-center p-4 bg-zinc-950/90 transition-colors duration-300">
                
                {/* "Modal" o Card de Error - Estilo Gelia */}
                <div 
                    className="w-full max-w-lg bg-zinc-900 border border-zinc-800 rounded-3xl p-8 shadow-2xl transform transition-all animate-fade-in-scale"
                    style={{
                        '--primary-color': 'var(--color-primario, #818cf8)', // Usamos el color primario del tema
                        '--hover-color': 'var(--color-primario-hover, #6366f1)'
                    }}
                >
                    <div className="flex flex-col items-center text-center space-y-6">
                        
                        {/* 1. La animación del logo roto */}
                        <GeliaLogo variant="shatter" className="w-48 h-48 -mt-10" />

                        {/* 2. Título (Código de error) */}
                        <h1 className="text-5xl font-extrabold text-white tracking-tight">
                            {status}
                        </h1>

                        {/* 3. Descripción (Mensaje profesional solicitado) */}
                        <p className="text-xl text-zinc-300 font-medium px-4">
                            {description}
                        </p>

                        <div className="w-full border-t border-zinc-800 my-4"></div>

                        {/* 4. Botones de Acción */}
                        <div className="flex flex-col sm:flex-row gap-4 w-full justify-center pt-2">
                            {/* Botón Volver al Inicio */}
                            <Link
                                href="/dashboard"
                                className="flex items-center justify-center gap-2.5 px-6 py-3.5 bg-zinc-800 hover:bg-zinc-700 text-white rounded-xl font-semibold transition-all duration-200"
                            >
                                <Home className="w-5 h-5 text-zinc-400" />
                                Ir al Tablero Principal
                            </Link>

                            {/* Botón Reintentar / Recargar (útil para 500/503) */}
                            <button
                                type="button"
                                onClick={reintentar}
                                className="flex items-center justify-center gap-2.5 px-6 py-3.5 rounded-xl font-semibold transition-all duration-200"
                                style={{
                                    backgroundColor: 'var(--primary-color)',
                                    color: 'white',
                                }}
                                onMouseOver={(e) => e.currentTarget.style.backgroundColor = 'var(--hover-color)'}
                                onMouseOut={(e) => e.currentTarget.style.backgroundColor = 'var(--primary-color)'}
                            >
                                <RefreshCcw className="w-5 h-5" />
                                Reintentar Acción
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}