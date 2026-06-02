import React from 'react';

/**
 * COMPONENTE: GeliaLogoBroken
 * DESCRIPCIÓN: Variante aislada del isotipo de Gelia que ejecuta la animación 'shatter' (roto/desarmado).
 * Diseñado específicamente para pantallas de Error (404, 403, 500) para no sobrecargar el logo principal.
 */
export default function GeliaLogoBroken({ 
    className = "w-32 h-32", 
    accentColor = "var(--color-primario)" 
}) {
    // Matriz de coordenadas y vectores de dispersión del logo original
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

    return (
            <svg viewBox="0 0 100 100" className={`${className}`} xmlns="http://www.w3.org/2000/svg">
                <g fill={accentColor} className="animate-shatter">
                    {polygonData.map((p, i) => (
                        <polygon 
                            key={i} 
                            points={p.points} 
                            opacity={p.opacity} 
                            // Inyección de variables CSS para la trayectoria de fragmentación individual
                            style={{ 
                                '--tx': p.tx, 
                                '--ty': p.ty, 
                                '--rot': p.rot, 
                                '--delay': p.delay 
                            }} 
                        />
                    ))}
                </g>
            </svg>
    );
}