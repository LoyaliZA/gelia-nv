import { createPortal } from 'react-dom';
import {
    geliaCardClass,
    isGlassEffectEnabled,
    THEME_INPUT,
    THEME_SELECT,
    THEME_TEXTAREA,
    THEME_LABEL,
    THEME_BTN_ICON,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../../utils/geliaTheme';

export const INPUT_CLASS = THEME_INPUT;
export const SELECT_CLASS = THEME_SELECT;
export const TEXTAREA_CLASS = THEME_TEXTAREA;
export const LABEL_CLASS = THEME_LABEL;
export const BTN_ICON_CLASS = THEME_BTN_ICON;
export const MODAL_OVERLAY_CLASS = THEME_MODAL_OVERLAY;
export const MODAL_SHELL_CLASS = THEME_MODAL_SHELL;
export const BTN_PRIMARY_CLASS = THEME_BTN_PRIMARY;
export const BTN_SECONDARY_CLASS = THEME_BTN_SECONDARY;

export const ESTADO_BADGE = {
    disponible: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    asignado: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    mantenimiento: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    baja: 'bg-zinc-200 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400',
};

export const ESTADO_LABELS = {
    disponible: 'Disponible',
    asignado: 'Asignado',
    mantenimiento: 'Mantenimiento',
    baja: 'Baja',
};

/** Etiquetas de tipo, departamento, chips, etc. — contraste explícito en modo oscuro */
export const METADATA_BADGE = 'px-2 py-1 rounded-lg text-[10px] font-black uppercase bg-zinc-100 text-zinc-800 dark:bg-zinc-800/90 dark:text-zinc-100 border border-zinc-200 dark:border-zinc-600';

export const CHIP_BADGE = 'px-3 py-1 rounded-xl text-[10px] font-black uppercase bg-zinc-100 text-zinc-800 dark:bg-zinc-800/90 dark:text-zinc-100 border border-zinc-200 dark:border-zinc-600';

export { isGlassEffectEnabled, geliaCardClass };

/** Acepta string o legacy `{ extra: '...' }` — delega en geliaCardClass del tema global. */
export function getActivosCardClass(extra = '') {
    const suffix =
        typeof extra === 'object' && extra !== null && typeof extra.extra === 'string'
            ? extra.extra
            : String(extra || '');
    return geliaCardClass(suffix);
}

export function renderActivosModal(content) {
    if (typeof document === 'undefined') return null;
    return createPortal(content, document.body);
}

export const BTN_TOUCH_CLASS = `${THEME_BTN_PRIMARY} min-h-[44px] text-base sm:text-sm`;
export const FAB_CLASS = 'fixed bottom-6 right-6 z-40 flex items-center justify-center gap-2 min-h-[52px] px-5 rounded-2xl text-white font-black uppercase text-xs shadow-lg lg:hidden';
