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

export const BTN_PRIMARY = `${THEME_BTN_PRIMARY} theme-btn-primary--compact`;
export const BTN_SECONDARY = `${THEME_BTN_SECONDARY} theme-btn-secondary--compact`;
export { THEME_INPUT, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL };

export const fmtMoneda = (n) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n || 0));

export const FLASH_OK =
    'rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-bold text-emerald-700 dark:text-emerald-300';
export const FLASH_ERR =
    'rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-bold text-rose-700 dark:text-rose-300';

export const TH =
    'px-3 py-2.5 text-[10px] font-black uppercase tracking-widest theme-text-muted text-left';
export const TD = 'px-3 py-2.5 text-sm theme-text-main border-t theme-border';

export const LABEL_ESTADO_FIN = {
    disponible: 'Disponible',
    parcialmente_aplicado: 'Parcial',
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
