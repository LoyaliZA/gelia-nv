import React from 'react';

/**
 * Aviso operativo alineado a secciones del modal Control Pedidos:
 * label uppercase + valor grande y legible.
 */
export default function AvisoOperativoPedido({
    label,
    children,
    tono = 'neutral',
    icon: Icon = null,
    className = '',
}) {
    const tonos = {
        neutral: 'theme-element border theme-border',
        info: 'bg-sky-500/10 border-sky-500/35',
        blue: 'bg-blue-500/10 border-blue-500/40',
        success: 'bg-emerald-500/10 border-emerald-500/35',
        warning: 'bg-amber-500/10 border-amber-500/35',
        danger: 'bg-orange-500/10 border-orange-500/35',
    };

    const textoTono = {
        neutral: 'theme-text-main',
        info: 'text-sky-700 dark:text-sky-300',
        blue: 'text-blue-700 dark:text-blue-300',
        success: 'text-emerald-700 dark:text-emerald-400',
        warning: 'text-amber-700 dark:text-amber-400',
        danger: 'text-orange-700 dark:text-orange-400',
    };

    return (
        <div className={`p-4 rounded-xl border ${tonos[tono] || tonos.neutral} ${className}`}>
            {label && (
                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1.5">
                    {label}
                </p>
            )}
            <div className={`flex items-start gap-2.5 ${textoTono[tono] || textoTono.neutral}`}>
                {Icon && <Icon className="w-5 h-5 shrink-0 mt-0.5" aria-hidden />}
                <div className="min-w-0 flex-1 text-base md:text-lg font-black leading-snug break-words">
                    {children}
                </div>
            </div>
        </div>
    );
}
