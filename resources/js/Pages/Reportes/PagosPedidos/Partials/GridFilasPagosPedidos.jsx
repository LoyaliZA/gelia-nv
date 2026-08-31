import React from 'react';
import { SEM_BADGE, SEM_TEXTO, fmtIncidencias, ETIQUETA_FIN, META_DETALLE, IMPORTE_FIN, BTN_NEUTRAL } from './pagosPedidosStyles';

/** Cuadrícula compartida: info principal + 4 columnas financieras alineadas (encabezado diario). */
export const GRID_FILA_PAGOS =
    'w-full grid grid-cols-[auto_minmax(0,1fr)] md:grid-cols-[auto_minmax(0,1fr)_minmax(6.25rem,7.5rem)_minmax(6.25rem,7.5rem)_minmax(6.25rem,7.5rem)_minmax(7.75rem,10rem)] gap-x-4 gap-y-3 md:gap-x-6 items-center text-left';

/** Móvil: última columna más ancha para el badge de incidencias. */
export const GRID_FINANCIERO_MOVIL =
    'col-span-2 grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(6.75rem,1.5fr)] gap-x-4 md:contents';

const tonoValorClass = (tono, { confirmado = false, destacado = false } = {}) => {
    if (confirmado || tono === 'exito') return SEM_TEXTO.exito;
    if (tono === 'exitoSuave') return SEM_TEXTO.exitoSuave;
    if (tono === 'advertencia' || destacado) return SEM_TEXTO.advertencia;
    if (tono === 'critico') return SEM_TEXTO.critico;
    if (tono === 'info') return SEM_TEXTO.info;
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
        <div className="flex flex-col items-end justify-center text-right tabular-nums min-w-0 w-full h-full gap-1.5 pr-0.5">
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

/** Resumen superior del pedido — identificación, financiero, documentos y cobertura. */
export const GRID_RESUMEN_PEDIDO = [
    'w-full grid gap-x-4 gap-y-3 text-left',
    'grid-cols-[32px_minmax(0,1fr)]',
    'md:grid-cols-[32px_minmax(0,1fr)_minmax(0,1.2fr)]',
    'lg:grid-cols-[32px_minmax(0,1fr)_minmax(0,1.35fr)_auto]',
    'lg:items-center',
    'min-h-0 px-4 py-4 md:px-5',
].join(' ');

/** @deprecated Usar GRID_RESUMEN_PEDIDO */
export const GRID_FILA_PEDIDO = GRID_RESUMEN_PEDIDO;

export const PEDIDO_CHEVRON = [
    'col-start-1 row-start-1 w-8 h-8 shrink-0 flex items-center justify-center rounded-lg',
    'self-start mt-0.5 pointer-events-none',
    'lg:self-center lg:mt-0',
].join(' ');

export const PEDIDO_IDENTIDAD = [
    'col-start-2 row-start-1 min-w-0 space-y-1',
    'md:col-start-2 md:row-start-1',
    'lg:self-center',
].join(' ');

export const PEDIDO_FIN_GRID = [
    'col-span-2 row-start-2',
    'grid grid-cols-2 gap-x-4 gap-y-3 w-full min-w-0',
    'md:col-span-1 md:col-start-3 md:row-start-1 md:grid-cols-4 md:gap-x-3 md:self-center',
    'lg:col-start-3 lg:row-start-1',
].join(' ');

/** Documentos + cobertura: un solo bloque al extremo derecho para evitar solapamiento. */
export const PEDIDO_TRAIL = [
    'col-span-2 row-start-3',
    'flex flex-col gap-3 w-full min-w-0',
    'sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-4',
    'md:col-span-2 md:col-start-2 md:row-start-2',
    'lg:col-start-4 lg:row-start-1 lg:justify-end lg:gap-5 lg:w-auto lg:shrink-0',
].join(' ');

export const PEDIDO_DOCS =
    'flex flex-row flex-nowrap items-center gap-2 shrink-0';

export const PEDIDO_COBERTURA =
    'flex items-end shrink-0 sm:ml-auto lg:ml-0';

/** @deprecated Usar PEDIDO_COBERTURA */
export const PEDIDO_BADGE = PEDIDO_COBERTURA;

export const PEDIDO_FOOTER_ADMIN = [
    'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3',
    'px-4 py-3.5 md:px-5',
    'border-t theme-border',
    'bg-[color-mix(in_srgb,var(--theme-border)_18%,var(--theme-element-bg))]',
].join(' ');

export const PEDIDO_BTN_DOC = `${BTN_NEUTRAL} min-w-9 w-auto !min-h-8 !h-8 !px-2.5 !text-xs`;

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
