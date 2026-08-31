/** Semántica compartida de las cuatro fechas en reportes de pagos. */

export const SIN_INFORMACION = 'Sin información';

export const TIPO_PEDIDO = 'pedido';
export const TIPO_PAGO = 'pago';
export const TIPO_REPORTADA = 'reportada';
export const TIPO_VALIDACION = 'validacion';

/** @param {string|null|undefined} value */
export function fmtFechaReporte(value) {
    if (!value) return SIN_INFORMACION;
    const d = new Date(String(value));
    if (Number.isNaN(d.getTime())) return SIN_INFORMACION;
    return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
}

/** @param {string|null|undefined} value */
export function fmtFechaHoraReporte(value) {
    if (!value) return SIN_INFORMACION;
    const d = new Date(String(value));
    if (Number.isNaN(d.getTime())) return SIN_INFORMACION;
    const fecha = d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
    const hora = d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false });
    return `${fecha}, ${hora}`;
}

/** Calendar day YYYY-MM-DD in local timezone (aligned with PHP Carbon::toDateString). */
export function diaCalendarioLocal(value) {
    const d = value instanceof Date ? value : new Date(String(value));
    if (Number.isNaN(d.getTime())) return null;
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

/** @param {string|null|undefined} fechaPago @param {string|null|undefined} reportadaAt */
export function reportadoPosteriormente(fechaPago, reportadaAt) {
    if (!fechaPago || !reportadaAt) return false;
    const diaPago = diaCalendarioLocal(fechaPago);
    const diaReportada = diaCalendarioLocal(reportadaAt);
    if (!diaPago || !diaReportada) return false;
    return diaReportada > diaPago;
}

if (import.meta.env?.DEV) {
    console.assert(
        reportadoPosteriormente('2026-08-29T12:00:00', '2026-08-30T10:00:00') === true,
        'reportadoPosteriormente: día posterior',
    );
    console.assert(
        reportadoPosteriormente('2026-08-29T12:00:00', '2026-08-29T18:00:00') === false,
        'reportadoPosteriormente: mismo día',
    );
    console.assert(fmtFechaReporte(null) === SIN_INFORMACION, 'fmtFechaReporte:null');
}
