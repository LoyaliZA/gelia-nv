import React from 'react';
import { createPortal } from 'react-dom';
import GeliaLogo from './GeliaLogo'; // <-- Importamos nuestro Smart Component

export default function GeliaLoader({ isVisible, message = "Sincronizando datos_" }) {
    if (!isVisible) return null;

    return createPortal(
        <div className="fixed inset-0 z-[99999] flex flex-col items-center justify-center p-4 bg-black/80 backdrop-blur-xl transition-opacity animate-fade-in">
            
            <div className="theme-surface border border-white/20 dark:border-zinc-700/50 p-8 sm:p-10 rounded-[2.5rem] shadow-[0_0_50px_rgba(0,0,0,0.5)] flex flex-col items-center gap-6 relative overflow-hidden max-w-sm w-full">
                
                {/* Resplandor de fondo dinámico */}
                <div 
                    className="absolute inset-0 opacity-20 animate-pulse pointer-events-none" 
                    style={{ background: 'radial-gradient(circle at center, var(--color-primario) 0%, transparent 70%)' }}
                />

                {/* Logo G animado centralizado (Variante: Brillo Diamante) */}
                <GeliaLogo variant="sparkle" className="w-20 h-20 relative z-10 drop-shadow-2xl" />
                
                <div className="text-center space-y-2 relative z-10">
                    <h3 className="text-xl font-black uppercase italic tracking-tighter theme-text-main m-0 drop-shadow-sm">
                        {message}
                    </h3>
                    <p className="text-[10px] font-bold theme-text-muted tracking-widest uppercase">
                        Por favor, no cierres esta ventana
                    </p>
                </div>

            </div>
            
        </div>,
        document.body
    );
}