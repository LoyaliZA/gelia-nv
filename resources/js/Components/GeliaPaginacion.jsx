import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { geliaCardClass } from '../utils/geliaTheme';

function generarPaginas(paginaActual, totalPaginas) {
    const paginas = [];
    if (totalPaginas <= 7) {
        for (let i = 1; i <= totalPaginas; i++) paginas.push(i);
    } else {
        paginas.push(1);
        if (paginaActual > 3) paginas.push('...');
        for (let i = Math.max(2, paginaActual - 1); i <= Math.min(totalPaginas - 1, paginaActual + 1); i++) {
            paginas.push(i);
        }
        if (paginaActual < totalPaginas - 2) paginas.push('...');
        paginas.push(totalPaginas);
    }
    return paginas;
}

/**
 * Paginación estándar GELIA (clases .paginacion-btn en gelia-theme.css / AppLayout).
 * @param {object} paginator - { current_page, last_page, from, to, total }
 */
export default function GeliaPaginacion({ paginator, onIrAPagina, embedded = false, className = '' }) {
    const paginaActual = paginator?.current_page || 1;
    const totalPaginas = paginator?.last_page || 1;
    const desde = paginator?.from ?? 0;
    const hasta = paginator?.to ?? 0;
    const total = paginator?.total ?? 0;

    if (total === 0) return null;

    const resumen = (
        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted text-center sm:text-left">
            {desde > 0 && hasta > 0
                ? `Viendo ${desde} al ${hasta} de ${total.toLocaleString('es-MX')}`
                : `${total.toLocaleString('es-MX')} registros`}
            {totalPaginas > 1 && (
                <span className="block sm:inline sm:ml-2 mt-1 sm:mt-0 opacity-80">
                    · Página {paginaActual} de {totalPaginas}
                </span>
            )}
        </span>
    );

    const controles = totalPaginas > 1 && (
            <div className="flex items-center justify-center sm:justify-end gap-1.5 sm:gap-2 flex-wrap">
                <button
                    type="button"
                    onClick={() => onIrAPagina(paginaActual - 1)}
                    disabled={paginaActual <= 1}
                    className="paginacion-btn theme-surface border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]"
                    aria-label="Página anterior"
                >
                    <ChevronLeft className="w-4 h-4" />
                </button>
                {generarPaginas(paginaActual, totalPaginas).map((p, i) =>
                    p === '...' ? (
                        <span key={`dots-${i}`} className="w-8 sm:w-10 text-center text-[11px] font-black theme-text-muted">
                            …
                        </span>
                    ) : (
                        <button
                            key={p}
                            type="button"
                            onClick={() => onIrAPagina(p)}
                            className={`paginacion-btn ${
                                p === paginaActual
                                    ? 'paginacion-btn--active'
                                    : 'theme-surface theme-text-main border theme-border hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]'
                            }`}
                        >
                            {p}
                        </button>
                    )
                )}
                <button
                    type="button"
                    onClick={() => onIrAPagina(paginaActual + 1)}
                    disabled={paginaActual >= totalPaginas}
                    className="paginacion-btn theme-surface border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]"
                    aria-label="Página siguiente"
                >
                    <ChevronRight className="w-4 h-4" />
                </button>
            </div>
    );

    if (embedded) {
        return (
            <div className={`border-t theme-border p-4 flex flex-col sm:flex-row items-center justify-between gap-4 ${className}`}>
                {resumen}
                {controles}
            </div>
        );
    }

    return (
        <div className={`${geliaCardClass('rounded-[2rem]')} p-4 flex flex-col sm:flex-row items-center justify-between gap-4 ${className}`}>
            {resumen}
            {controles}
        </div>
    );
}
