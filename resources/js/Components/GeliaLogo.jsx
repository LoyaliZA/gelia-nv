/**
 * COMPONENTE: GeliaLogo
 * DESCRIPCIÓN: Isotipo oficial modular de Gelia con soporte para animaciones de estado y progreso.
 * * INSTRUCCIONES DE USO:
 * 1. Estático/Animación Simple: 
 * <GeliaLogo variant="sparkle" className="w-16 h-16" />
 * * 2. Como Indicador de Carga (Infinito):
 * <GeliaLogo variant="fluid-fill" className="w-20 h-20" />
 * * 3. Como Barra de Progreso Real:
 * <GeliaLogo variant="fluid-fill" progress={75} className="w-24 h-24" />
 * (El valor de 'progress' debe ser entre 0 y 100).
 * * VARIANTES: "default", "spin", "pulse", "float", "shatter", "sparkle", "fluid-fill".
 */

import React from 'react';

export default function GeliaLogo({ 
    className = "w-12 h-12", 
    accentColor = "var(--color-primario)",
    variant = "default",
    progress = null // null para animación infinita, 0-100 para control manual
}) {
    
    // --- LÓGICA DE ANIMACIÓN POR VARIANTE ---
    let groupAnimClass = "";
    switch (variant) {
        case 'spin':        groupAnimClass = "animate-spin-slow"; break;
        case 'pulse':       groupAnimClass = "animate-pulse-glow"; break;
        case 'float':       groupAnimClass = "animate-float"; break;
        case 'shatter':     groupAnimClass = "animate-shatter"; break;
        case 'sparkle':     groupAnimClass = "animate-sparkle"; break;
        case 'hover-scale': groupAnimClass = "transition-transform duration-300 hover:scale-110 cursor-pointer"; break;
        default:            groupAnimClass = "transition-all duration-300";
    }

    // --- MATRIZ DE POLÍGONOS (ADN VISUAL) ---
    const polygonData = [
        { points: "30,10 70,10 30,30", opacity: "1.0", tx: "0px", ty: "-30px", rot: "45deg", delay: "0s" },
        { points: "10,30 30,30 10,70", opacity: "1.0", tx: "-30px", ty: "0px", rot: "-45deg", delay: "0.1s" },
        { points: "30,90 30,70 70,90", opacity: "1.0", tx: "0px", ty: "30px", rot: "45deg", delay: "0.2s" },
        { points: "90,70 70,70 90,50", opacity: "1.0", tx: "30px", ty: "30px", rot: "90deg", delay: "0.3s" },
        { points: "70,50 70,70 50,50", opacity: "1.0", tx: "20px", ty: "-20px", rot: "-90deg", delay: "0.4s" },
        { points: "70,10 70,30 30,30", opacity: "0.6", tx: "30px", ty: "-30px", rot: "180deg", delay: "0.1s" },
        { points: "30,30 30,70 10,70", opacity: "0.6", tx: "-20px", ty: "20px", rot: "90deg", delay: "0.2s" },
        { points: "30,70 70,70 70,90", opacity: "0.6", tx: "0px", ty: "40px", rot: "-45deg", delay: "0.3s" },
        { points: "70,70 70,50 90,50", opacity: "0.6", tx: "40px", ty: "0px", rot: "45deg", delay: "0.4s" },
        { points: "70,10 90,30 70,30", opacity: "0.3", tx: "40px", ty: "-20px", rot: "135deg", delay: "0.2s" },
        { points: "30,10 30,30 10,30", opacity: "0.3", tx: "-40px", ty: "-20px", rot: "-135deg", delay: "0.3s" },
        { points: "10,70 30,70 30,90", opacity: "0.3", tx: "-40px", ty: "20px", rot: "-45deg", delay: "0.4s" },
        { points: "70,90 70,70 90,70", opacity: "0.3", tx: "40px", ty: "40px", rot: "45deg", delay: "0.5s" },
    ];

    // --- CÁLCULO DE NIVEL DE FLUIDO ---
    // Si progress tiene un valor (0-100), calculamos la posición Y del rectángulo de corte.
    // 100 es vacío (abajo), 0 es lleno (arriba).
    const fluidY = progress !== null ? 100 - Math.min(100, Math.max(0, progress)) : 0;

    return (
        <svg viewBox="0 0 100 100" className={`${className}`} xmlns="http://www.w3.org/2000/svg">
                {variant === 'fluid-fill' ? (
                    <>
                        <defs>
                            <clipPath id="fluid-clip">
                                {/* Si progress es null, aplicamos la clase de animación infinita.
                                    Si progress tiene valor, la transición será suave gracias a 'transition-all' */}
                                <rect 
                                    x="0" 
                                    y={fluidY} 
                                    width="100" 
                                    height="100" 
                                    className={`transition-all duration-500 ease-out ${progress === null ? 'animate-fluid-rise' : ''}`} 
                                />
                            </clipPath>
                        </defs>
                        
                        <g fill="#4b5563" className="opacity-20">
                            {polygonData.map((p, i) => (
                                <polygon key={`bg-${i}`} points={p.points} opacity={p.opacity} />
                            ))}
                        </g>

                        <g fill={accentColor} clipPath="url(#fluid-clip)" className="animate-fluid-sparkle">
                            {polygonData.map((p, i) => (
                                <polygon key={`fill-${i}`} points={p.points} opacity={p.opacity} style={{ '--delay': p.delay }} />
                            ))}
                        </g>
                    </>
                ) : (
                    <g fill={accentColor} className={groupAnimClass}>
                        {polygonData.map((p, i) => (
                            <polygon 
                                key={i} 
                                points={p.points} 
                                opacity={p.opacity} 
                                style={{ '--tx': p.tx, '--ty': p.ty, '--rot': p.rot, '--delay': p.delay }} 
                            />
                        ))}
                    </g>
                )}
            </svg>
    );
}