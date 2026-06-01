import React from 'react';
import { geliaCardClass } from '../utils/geliaTheme';

/**
 * Tarjeta de título estándar GELIA (mismo patrón que Catálogos, Clientes, RH, etc.).
 */
export default function GeliaTituloCard({
    eyebrow,
    title,
    titleHighlight,
    description,
    icon: Icon,
    aside,
    children,
    className = '',
    style,
}) {
    const cardClass = geliaCardClass(
        `gelia-titulo-card p-6 md:p-10 lg:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6 md:gap-8 relative z-10 w-full max-w-full min-w-0 box-border ${className}`.trim()
    );

    return (
        <header className={cardClass} style={style}>
            <div className="min-w-0 flex-1 space-y-3">
                {eyebrow && (
                    <div className="flex items-center gap-3">
                        <span
                            className="h-1.5 w-8 md:w-12 rounded-full shrink-0"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                            aria-hidden
                        />
                        <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm m-0">
                            {eyebrow}
                        </span>
                    </div>
                )}

                <h1 className="text-3xl sm:text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0">
                    {title}
                    {titleHighlight != null && titleHighlight !== '' && (
                        <>
                            {' '}
                            <span style={{ color: 'var(--color-primario)' }}>{titleHighlight}</span>
                        </>
                    )}
                </h1>

                {description && (
                    <p className="text-[10px] md:text-[11px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0 max-w-2xl leading-relaxed">
                        {description}
                    </p>
                )}

                {children}
            </div>

            {(Icon || aside) && (
                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 shrink-0 w-full md:w-auto">
                    {aside}
                    {Icon && (
                        <div
                            className="p-4 rounded-2xl theme-element border theme-border flex items-center justify-center shrink-0 self-start md:self-center"
                            aria-hidden
                        >
                            <Icon className="w-7 h-7 md:w-8 md:h-8" style={{ color: 'var(--color-primario)' }} />
                        </div>
                    )}
                </div>
            )}
        </header>
    );
}
