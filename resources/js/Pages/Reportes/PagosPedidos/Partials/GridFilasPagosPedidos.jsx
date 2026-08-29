import React from 'react';
import { SEM_BADGE, fmtIncidencias, ETIQUETA_FIN, META_DETALLE, IMPORTE_FIN } from './pagosPedidosStyles';

/** Cuadrícula compartida: info principal + 4 columnas financieras alineadas (encabezado diario). */
export const GRID_FILA_PAGOS =
    'w-full grid grid-cols-[auto_minmax(0,1fr)] md:grid-cols-[auto_minmax(0,1fr)_minmax(6.25rem,7.5rem)_minmax(6.25rem,7.5rem)_minmax(6.25rem,7.5rem)_minmax(7.75rem,10rem)] gap-x-4 gap-y-3 md:gap-x-6 items-center text-left';

/** Móvil: última columna más ancha para el badge de incidencias. */
export const GRID_FINANCIERO_MOVIL =
    'col-span-2 grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(6.75rem,1.5fr)] gap-x-4 md:contents';

const tonoValorClass = (tono, { confirmado = false, destacado = false } = {}) => {
    if (confirmado || tono === 'exito') return 'text-emerald-600 dark:text-emerald-400';
    if (tono === 'advertencia' || destacado) return 'text-amber-700 dark:text-amber-400';
    if (tono === 'critico') return 'text-red-600 dark:text-red-400';
    if (tono === 'info') return 'text-sky-700 dark:text-sky-400';
    return 'theme-text-main';
};

export function CeldaFinanciera({
    label,
    valor,
    tono = null,
    /** @deprecated Usar tono="advertencia" */
    destacado = false,
    confirmado = false,
}) {
    return (
        <div className="flex flex-col items-end justify-center text-right tabular-nums min-w-0 w-full h-full gap-1 pr-0.5">
            <span className={`${ETIQUETA_FIN} leading-snug whitespace-nowrap`}>{label}</span>
            <span
                className={[
                    IMPORTE_FIN,
                    'leading-tight whitespace-nowrap',
                    tonoValorClass(tono, { confirmado, destacado }),
                ].join(' ')}
            >
                {valor}
            </span>
        </div>
    );
}

export function CeldaIncidencias({ count }) {
    const n = Number(count ?? 0);
    const hayIncidencias = n > 0;

    return (
        <div className="flex flex-col items-end justify-center text-right min-w-[6.75rem] w-full h-full gap-2 pl-3 md:pl-4 shrink-0">
            <span className={`${ETIQUETA_FIN} leading-snug whitespace-nowrap w-full`}>Incidencias</span>
            {hayIncidencias ? (
                <span className={`${SEM_BADGE.advertencia} shadow-sm max-w-full`}>
                    {fmtIncidencias(n)}
                </span>
            ) : (
                <span className={`${META_DETALLE} leading-snug whitespace-nowrap`}>
                    Sin incidencias
                </span>
            )}
        </div>
    );
}

export function pedidoTieneIncidencia(pedido) {
    return ['parcial', 'con_excedente', 'sin_pago'].includes(pedido?.estado_cobertura);
}

/** Fila desplegable de pedido — móvil / md / lg. */
export const GRID_FILA_PEDIDO = [
    'w-full grid gap-x-3 gap-y-3 text-left items-center',
    'grid-cols-[32px_minmax(0,1fr)]',
    'min-h-[100px] px-4 py-3',
    'md:px-6 md:grid-cols-[32px_minmax(0,1fr)_auto_auto] md:grid-rows-[auto_auto] md:items-start md:py-4',
    'lg:grid-cols-[32px_minmax(0,1.1fr)_minmax(16rem,1.25fr)_auto_auto]',
    'lg:grid-rows-1 lg:items-center lg:min-h-[100px] lg:max-h-[115px] lg:py-3',
].join(' ');

export const PEDIDO_CHEVRON =
    'col-start-1 row-start-1 w-8 h-8 shrink-0 flex items-center justify-center rounded-lg self-center pointer-events-none';

export const PEDIDO_IDENTIDAD =
    'col-start-2 row-start-1 min-w-0 self-center space-y-0.5 md:row-start-1 lg:col-start-2 lg:row-start-1';

export const PEDIDO_FIN_GRID = [
    'col-span-2 row-start-2',
    'grid grid-cols-2 gap-x-3 gap-y-2 w-full min-w-0',
    'md:col-span-4 md:row-start-2 md:grid-cols-4 md:gap-x-4',
    'lg:col-start-3 lg:row-start-1 lg:col-span-1 lg:grid lg:grid-cols-4 lg:gap-x-3 lg:gap-y-0 lg:self-center lg:min-w-0',
].join(' ');

export const PEDIDO_DOCS = [
    'col-span-2 row-start-3',
    'flex flex-col gap-2 w-full min-w-0',
    'md:col-start-3 md:row-start-1 md:col-span-1 md:w-auto md:flex-row md:items-center md:gap-2 md:self-center',
    'lg:col-start-4 lg:row-start-1 lg:col-span-1 lg:flex-row lg:shrink-0 lg:self-center',
].join(' ');

export const PEDIDO_BADGE =
    'col-span-2 row-start-4 w-fit shrink-0 md:col-start-4 md:row-start-1 md:justify-self-end md:self-center lg:col-start-5 lg:row-start-1 lg:justify-self-end lg:self-center';

export const PEDIDO_BTN_DOC =
    'inline-flex items-center justify-center gap-1.5 min-w-9 min-h-9 h-9 px-2 lg:px-3 rounded-lg border theme-border theme-element theme-text-main text-xs font-semibold shadow-sm hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] hover:bg-[color-mix(in_srgb,var(--color-primario)_8%,transparent)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--theme-surface)] transition-all disabled:opacity-40 disabled:cursor-not-allowed w-full md:w-auto shrink-0';

export const PEDIDO_CABECERA =
    'cursor-pointer hover:bg-black/[0.03] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--color-primario)]';

/** @deprecated Usar PEDIDO_FIN_GRID */
export const BLOQUE_COBERTURA_PEDIDO = PEDIDO_FIN_GRID;

/** @deprecated Usar PEDIDO_DOCS */
export const GRID_ACCIONES_PEDIDO = PEDIDO_DOCS;

/** @deprecated Usar CeldaFinanciera en cuadrícula */
export function LineaFinanciera({ label, valor, tono = null, destacado = false, confirmado = false }) {
    return (
        <p className="m-0 flex flex-wrap justify-end gap-x-1.5 leading-snug text-xs md:text-[13px] tabular-nums">
            <span className="font-medium theme-text-muted">{label}:</span>
            <span className={['font-semibold', tonoValorClass(tono, { confirmado, destacado })].join(' ')}>
                {valor}
            </span>
        </p>
    );
}
