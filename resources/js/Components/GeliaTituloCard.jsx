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
    compact = false,
    className = '',
    style,
}) {
    const densidad = compact
        ? {
              shell:
                  'gelia-titulo-card--compact p-4 md:p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 md:gap-4',
              bloque: 'space-y-1.5',
              titulo: 'text-2xl sm:text-3xl md:text-3xl',
              descripcion: 'text-[9px] md:text-[10px] mt-0.5',
              hijos: 'mt-2 md:mt-2.5',
              iconoCaja: 'p-2.5 rounded-xl',
              iconoTam: 'w-5 h-5 md:w-6 md:h-6',
              aside: 'gap-2',
          }
        : {
              shell:
                  'p-6 md:p-10 lg:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6 md:gap-8',
              bloque: 'space-y-3',
              titulo: 'text-3xl sm:text-4xl md:text-5xl',
              descripcion: 'text-[10px] md:text-[11px] mt-1',
              hijos: 'mt-4 md:mt-5',
              iconoCaja: 'p-4 rounded-2xl',
              iconoTam: 'w-7 h-7 md:w-8 md:h-8',
              aside: 'gap-3',
          };

    const cardClass = geliaCardClass(
        `gelia-titulo-card ${densidad.shell} relative z-10 w-full max-w-full min-w-0 box-border ${className}`.trim()
    );

    return (
        <header className={cardClass} style={style}>
            <div className="min-w-0 flex-1">
                <div className={densidad.bloque}>
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

                    <h1
                        className={`${densidad.titulo} font-black italic tracking-tighter uppercase theme-text-main leading-none m-0`}
                    >
                        {title}
                        {titleHighlight != null && titleHighlight !== '' && (
                            <>
                                {' '}
                                <span style={{ color: 'var(--color-primario)' }}>{titleHighlight}</span>
                            </>
                        )}
                    </h1>

                    {description && (
                        <p
                            className={`${densidad.descripcion} font-bold theme-text-muted uppercase tracking-widest m-0 max-w-2xl leading-snug`}
                        >
                            {description}
                        </p>
                    )}
                </div>

                {children && (
                    <div className={densidad.hijos}>
                        {children}
                    </div>
                )}
            </div>

            {(Icon || aside) && (
                <div
                    className={`flex flex-col sm:flex-row items-stretch sm:items-center ${densidad.aside} shrink-0 w-full sm:w-auto self-start sm:self-center`}
                >
                    {aside}
                    {Icon && (
                        <div
                            className={`${densidad.iconoCaja} theme-element border theme-border flex items-center justify-center shrink-0`}
                            aria-hidden
                        >
                            <Icon className={densidad.iconoTam} style={{ color: 'var(--color-primario)' }} />
                        </div>
                    )}
                </div>
            )}
        </header>
    );
}
