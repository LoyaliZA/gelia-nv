import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { Palette, Volume2, ImageIcon, Layers } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import TablaTonos from './Partials/Personalizacion/TablaTonos';
import TablaFondos from './Partials/Personalizacion/TablaFondos';
import TablaTemas from './Partials/Personalizacion/TablaTemas';

export default function GestorDePersonalizacion({ tonos = [], fondos = [], temas = [] }) {
    const [tabActiva, setTabActiva] = useState('tonos');
    const [glassEffect, setGlassEffect] = useState(() => {
        if (typeof window === 'undefined') return true;
        const saved = localStorage.getItem('theme_glass');
        return saved !== null ? saved === 'true' : true;
    });

    useEffect(() => {
        const syncGlass = () => {
            const saved = localStorage.getItem('theme_glass');
            if (saved !== null) setGlassEffect(saved === 'true');
        };
        window.addEventListener('theme-changed', syncGlass);
        return () => window.removeEventListener('theme-changed', syncGlass);
    }, []);

    useEffect(() => {
        animate(
            '.fade-up',
            { translateY: [15, 0], opacity: [0, 1] },
            { easing: 'easeOutExpo', duration: 600, delay: (el, i) => i * 80 }
        );
    }, [tabActiva]);

    const baseCardClass   = 'fade-up theme-surface rounded-[2.5rem] relative z-10 transition-all duration-300';
    const glassCardClass  = 'theme-surface theme-border border shadow-[0_12px_40px_rgba(0,0,0,0.12)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.6)]';
    const solidCardClass  = 'bg-white dark:bg-[#121212] border border-zinc-200 dark:border-zinc-800 shadow-[0_12px_40px_rgba(0,0,0,0.08)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.5)]';
    const activeCardClass = `${baseCardClass} ${glassEffect ? glassCardClass : solidCardClass}`;

    const tabs = [
        { id: 'tonos',  label: 'Tonos de Alerta', icon: Volume2 },
        { id: 'fondos', label: 'Fondos',          icon: ImageIcon },
        { id: 'temas',  label: 'Temas',           icon: Layers },
    ];

    return (
        <AppLayout>
            <Head title="Personalización | GELIANV" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-8`}>
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm">
                                Experiencia de Usuario_
                            </span>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0">
                            GESTOR DE <span style={{ color: 'var(--color-primario)' }}>PERSONALIZACIÓN</span>
                        </h1>
                        <p className="text-sm font-bold theme-text-muted max-w-2xl leading-relaxed m-0">
                            Administra tonos, fondos y temas predefinidos que se muestran en el perfil de cada colaborador.
                        </p>
                    </div>
                    <div className="p-4 rounded-2xl theme-element border theme-border">
                        <Palette className="w-8 h-8" style={{ color: 'var(--color-primario)' }} />
                    </div>
                </header>

                <div className={`${activeCardClass} p-3 md:p-4`}>
                    <div className="gelia-segment w-full p-1 shadow-sm flex flex-wrap gap-1">
                        {tabs.map((tab) => (
                            <button
                                key={tab.id}
                                type="button"
                                onClick={() => setTabActiva(tab.id)}
                                className="gelia-segment-btn flex-1 py-3.5 md:py-4 rounded-[1.25rem] min-w-[120px] sm:min-w-[140px] text-[10px] font-black uppercase tracking-widest"
                                data-active={tabActiva === tab.id}
                            >
                                <tab.icon className="w-4 h-4" /> {tab.label}
                            </button>
                        ))}
                    </div>
                </div>

                <section className={`${activeCardClass} overflow-hidden`}>
                    {tabActiva === 'tonos' && <TablaTonos datos={tonos} />}
                    {tabActiva === 'fondos' && <TablaFondos datos={fondos} />}
                    {tabActiva === 'temas' && <TablaTemas datos={temas} fondos={fondos} glassEffect={glassEffect} />}
                </section>
            </div>
        </AppLayout>
    );
}
