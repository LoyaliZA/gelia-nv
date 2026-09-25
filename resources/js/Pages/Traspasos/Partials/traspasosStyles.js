/**
 * Colores y superficies del módulo Traspasos (tokens en resources/css/gelia/tokens.css).
 */

export const ICON_PRIMARIO = { color: 'var(--color-primario)' };
export const ICON_PELIGRO = { color: 'var(--color-peligro)' };
export const ICON_EXITO = { color: 'var(--color-exito)' };
export const ICON_AVISO = { color: 'var(--color-aviso)' };

export const TEXTO_ERROR = 'text-xs font-bold theme-text-peligro';
export const TEXTO_AVISO = 'text-[10px] font-bold theme-text-aviso';

export const MODAL_HEADER = 'p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0';
export const MODAL_BODY = 'gelia-modal-body p-5 md:p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0 space-y-5';
export const BTN_CERRAR_MODAL = 'p-2 theme-text-muted rounded-full hover:bg-black/5 dark:hover:bg-white/5 outline-none';

export const PANEL_RESPUESTA_EXITO =
    'p-3 rounded-2xl border flex flex-col gap-2 border-[color-mix(in_srgb,var(--color-exito)_40%,transparent)] bg-[color-mix(in_srgb,var(--color-exito)_12%,transparent)]';
export const PANEL_RESPUESTA_PELIGRO =
    'p-3 rounded-2xl border flex flex-col gap-2 border-[color-mix(in_srgb,var(--color-peligro)_40%,transparent)] bg-[color-mix(in_srgb,var(--color-peligro)_12%,transparent)]';
export const PANEL_INCIDENCIA =
    'p-3 rounded-2xl border border-[color-mix(in_srgb,var(--color-aviso)_40%,transparent)] bg-[color-mix(in_srgb,var(--color-aviso)_12%,transparent)] flex flex-col gap-2';

export const TITULO_SECCION_EXITO = 'text-[9px] font-black uppercase tracking-widest theme-text-exito';
export const TITULO_SECCION_PELIGRO = 'text-[9px] font-black uppercase tracking-widest theme-text-peligro';
export const TITULO_SECCION_AVISO = 'text-[9px] font-black uppercase tracking-widest theme-text-aviso';

export const BADGE_INCIDENCIA =
    'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest border border-[color-mix(in_srgb,var(--color-aviso)_30%,transparent)] bg-[color-mix(in_srgb,var(--color-aviso)_10%,transparent)] theme-text-aviso';

export const META_BLOQUE_EXITO =
    'inline-flex flex-col gap-0.5 px-3 py-2 rounded-xl border border-[color-mix(in_srgb,var(--color-exito)_40%,transparent)] bg-[color-mix(in_srgb,var(--color-exito)_15%,transparent)] min-w-0';

/** Timeline bitácora */
export const BITACORA_TONO = {
    ok: {
        iconWrap: 'gelia-icon-box theme-text-exito border-[color-mix(in_srgb,var(--color-exito)_35%,transparent)]',
        rail: 'border-[color-mix(in_srgb,var(--color-exito)_30%,transparent)]',
        label: 'theme-text-exito',
    },
    error: {
        iconWrap: 'gelia-icon-box theme-text-peligro border-[color-mix(in_srgb,var(--color-peligro)_35%,transparent)]',
        rail: 'border-[color-mix(in_srgb,var(--color-peligro)_30%,transparent)]',
        label: 'theme-text-peligro',
    },
    neutro: {
        iconWrap: 'gelia-icon-box theme-text-muted',
        rail: 'theme-border',
        label: 'theme-text-muted',
    },
};
