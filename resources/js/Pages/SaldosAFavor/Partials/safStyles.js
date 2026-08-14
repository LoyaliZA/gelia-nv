import {
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_SELECT,
    THEME_TEXTAREA,
} from '../../../utils/geliaTheme';

export const BTN_PRIMARY = `${THEME_BTN_PRIMARY} theme-btn-primary--compact inline-flex items-center justify-center gap-2`;
export const BTN_SECONDARY = `${THEME_BTN_SECONDARY} theme-btn-secondary--compact inline-flex items-center justify-center gap-2`;
export const BTN_BACK =
    'inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-main theme-surface rounded-xl px-3 py-2 border theme-border hover:opacity-90 transition-opacity';
export const BTN_KEBAB =
    'p-2.5 theme-element border theme-border hover:border-[var(--color-primario)] rounded-xl transition-all shadow-sm outline-none inline-flex items-center justify-center';
export const BTN_ICON =
    'inline-flex items-center gap-2 px-3 py-2 rounded-xl border theme-border theme-element text-[10px] font-black uppercase tracking-widest transition-all shadow-sm hover:border-[var(--color-primario)] outline-none';
export const MENU_PANEL =
    'fixed z-[1000] theme-surface border theme-border shadow-2xl rounded-2xl p-2 flex flex-col gap-1 backdrop-blur-xl w-56';

const MENU_TONES = {
    primary: 'hover:bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)] text-[var(--color-primario)]',
    amber: 'hover:bg-amber-50 dark:hover:bg-amber-500/10 text-amber-600 dark:text-amber-400',
    emerald: 'hover:bg-emerald-50 dark:hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    sky: 'hover:bg-sky-50 dark:hover:bg-sky-500/10 text-sky-600 dark:text-sky-400',
    rose: 'hover:bg-rose-50 dark:hover:bg-rose-500/10 text-rose-600 dark:text-rose-400',
    neutral: 'hover:bg-black/5 dark:hover:bg-white/5 theme-text-main',
};

/** @param {keyof typeof MENU_TONES} tone */
export const MENU_ITEM = (tone = 'neutral') =>
    `flex items-center gap-3 px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors ${MENU_TONES[tone] || MENU_TONES.neutral}`;

export { THEME_INPUT, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL };

export const fmtMoneda = (n) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n || 0));

/** @param {string|null|undefined} value */
export const fmtFecha = (value) => {
    if (!value) return '—';
    // ponytail: date-only Y-m-d parsed as UTC midnight can shift day in MX; upgrade if timezone rules get stricter
    const raw = String(value);
    const dateOnly = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    const d = dateOnly
        ? new Date(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]))
        : new Date(raw);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
};

export const FLASH_OK =
    'rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-bold text-emerald-700 dark:text-emerald-300';
export const FLASH_ERR =
    'rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-bold text-rose-700 dark:text-rose-300';

export const TH =
    'px-2 py-2 text-[10px] font-black uppercase tracking-widest theme-text-muted text-left';
export const TD = 'px-2 py-2 text-sm theme-text-main border-t theme-border break-words';

export const LABEL_ESTADO_FIN = {
    disponible: 'Disponible',
    parcialmente_aplicado: 'Disponible',
    reservado: 'Reservado',
    aplicado: 'Aplicado',
    vencido: 'Vencido',
    cancelado: 'Cancelado',
};

export const LABEL_ESTADO_REV = {
    pendiente: 'Pendiente',
    en_revision: 'En revisión',
    verificado: 'Verificado',
    con_observaciones: 'Con observaciones',
    rechazado: 'Rechazado',
    confirmado: 'Verificado',
    revisado: 'Revisado',
    con_diferencia: 'Con observaciones',
    requiere_correccion: 'Requiere corrección',
    ajustado: 'Ajustado',
};

export const LABEL_CANAL = {
    bellaroma: 'Bellaroma',
    call_center_local: 'Call Center local',
    call_center_foraneo: 'Call Center foráneo',
    punto_venta: 'Punto de Venta',
};

export const LABEL_CATEGORIA_MOTIVO = {
    diferencias_pago: 'Diferencias de pago',
    ajustes_mercancia: 'Ajustes de mercancía',
    ajustes_envio: 'Ajustes de envío',
    errores: 'Errores operativos',
    sistema: 'Sistema',
};

export const FORMAS_PAGO_LABEL = {
    transferencia: 'Transferencia',
    deposito: 'Depósito',
    efectivo: 'Efectivo',
    tarjeta: 'Tarjeta',
    otro: 'Otro',
};

export function groupMotivosByCategoria(motivos = []) {
    const map = new Map();
    motivos.forEach((m) => {
        const key = m.categoria || 'otros';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(m);
    });
    return [...map.entries()];
}
