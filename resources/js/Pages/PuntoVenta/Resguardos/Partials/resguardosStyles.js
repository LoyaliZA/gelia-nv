import { geliaCardClass } from '../../../../utils/geliaTheme';

export const BTN_SECONDARY =
    'px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest theme-element theme-border border outline-none disabled:opacity-50';

export const THEME_INPUT =
    'w-full rounded-2xl border theme-border theme-element px-4 py-3 text-sm font-semibold outline-none focus:border-[var(--color-primario)]';

export const THEME_SELECT = `${THEME_INPUT} appearance-none`;

export function formatearFechaOperativa(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '—';
    }
}

export function badgeEstadoResguardo(estado) {
    const mapa = {
        pendiente_recepcion: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        en_custodia: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
        entregado: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
        devuelto: 'bg-slate-500/15 text-slate-700 dark:text-slate-300',
    };
    return mapa[estado] || 'bg-black/5 dark:bg-white/10 theme-text-muted';
}

export function badgeAntiguedad(clave) {
    const mapa = {
        rezagado: 'bg-orange-500/15 text-orange-700 dark:text-orange-300',
        proximo_a_vencer: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        vencido: 'bg-red-500/15 text-red-700 dark:text-red-300',
    };
    return mapa[clave] || 'bg-black/5 dark:bg-white/10 theme-text-muted';
}

export function tarjetaResguardoClass(resguardo) {
    const base = `${geliaCardClass()} p-4 space-y-3`;
    if (resguardo?.clasificaciones?.vencido) return `${base} ring-1 ring-red-500/30`;
    if (resguardo?.clasificaciones?.proximo_a_vencer) return `${base} ring-1 ring-amber-500/30`;
    if (resguardo?.clasificaciones?.rezagado) return `${base} ring-1 ring-orange-500/30`;
    if ((resguardo?.incidencias_abiertas_count || 0) > 0) return `${base} ring-1 ring-purple-500/30`;
    return base;
}
