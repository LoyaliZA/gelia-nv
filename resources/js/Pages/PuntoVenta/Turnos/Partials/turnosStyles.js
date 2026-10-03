import { THEME_INPUT } from '../../Resguardos/Partials/resguardosStyles';

export { THEME_INPUT };

export const BTN_SEGMENTO =
    'flex-1 min-h-[44px] px-3 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest border transition-colors';

export const BTN_SEGMENTO_ACTIVO =
    'border-[var(--color-primario)] bg-[var(--color-primario)]/10 theme-text-main';

export const BTN_SEGMENTO_INACTIVO =
    'theme-border theme-element theme-text-muted hover:theme-text-main';

const TONOS_LISTA_TURNO = new Set(['bronce', 'plata', 'oro', 'diamante']);

export function tonoListaTurno(turno) {
    const tono = String(turno?.lista_tono || '').toLowerCase();
    if (TONOS_LISTA_TURNO.has(tono)) return tono;
    if (turno?.prioridad_diamante) return 'diamante';
    return null;
}

export function claseModalListaTurno(turno) {
    const tono = tonoListaTurno(turno);
    return tono ? `pdv-llamado-${tono}` : '';
}

export function badgePrioridadTurno(etiqueta) {
    const mapa = {
        Diamante: 'pdv-llamado-diamante',
        VIP: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        'Adulto mayor': 'pdv-llamado-mayor',
        Discapacidad: 'pdv-llamado-discapacidad',
    };
    return mapa[etiqueta] || 'bg-black/5 dark:bg-white/10 theme-text-muted';
}

export function badgeEstadoTurno(estado) {
    const mapa = {
        EN_COLA: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        ASIGNADO: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    };
    return mapa[estado] || 'bg-black/5 dark:bg-white/10 theme-text-muted';
}
