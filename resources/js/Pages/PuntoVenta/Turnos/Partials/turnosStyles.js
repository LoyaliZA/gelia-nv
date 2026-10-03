import { THEME_INPUT } from '../../Resguardos/Partials/resguardosStyles';

export { THEME_INPUT };

export const BTN_SEGMENTO =
    'flex-1 min-h-[44px] px-3 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest border transition-colors';

export const BTN_SEGMENTO_ACTIVO =
    'border-[var(--color-primario)] bg-[var(--color-primario)]/10 theme-text-main';

export const BTN_SEGMENTO_INACTIVO =
    'theme-border theme-element theme-text-muted hover:theme-text-main';

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
