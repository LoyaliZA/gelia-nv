/** Acento del módulo: hereda el tema global de AppLayout (--color-primario). */
export const ACCENT = 'var(--color-primario)';

export const ESTADO_BADGE = {
    1: 'bg-amber-500/15 text-amber-600 border-amber-500/30',
    2: 'bg-emerald-500/15 text-emerald-600 border-emerald-500/30',
    3: 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30',
    4: 'bg-red-500/15 text-red-600 border-red-500/30',
};

export const ESTADO_LABELS = {
    Pendiente: 'Pendiente',
    Respondida: 'Respondida',
    Verificada: 'Verificada',
    Incorrecta: 'Incorrecta',
    Cancelada: 'Cancelada',
};

import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../utils/geliaTheme';

export const BTN_PRIMARY = `${THEME_BTN_PRIMARY} theme-btn-primary--compact`;
export const BTN_SECONDARY = `${THEME_BTN_SECONDARY} theme-btn-primary--compact`;

/** @deprecated Usar ACCENT */
export const FACTURA_ACCENT = ACCENT;

export function urlArchivoFactura(facturaId, tipo, indice = 0) {
    const base = route('facturas.archivo', { factura: facturaId, tipo });
    if (tipo === 'voucher') {
        return `${base}?indice=${indice}`;
    }
    return base;
}

export function esPdfVoucher(voucher) {
    if (!voucher) return false;
    if (voucher.mime?.includes('pdf')) return true;
    if (voucher.mime?.startsWith('image/')) return false;
    const ref = (voucher.nombre_original || voucher.path || '').toLowerCase();
    return ref.endsWith('.pdf');
}

export function esImagenVoucher(voucher) {
    if (!voucher) return false;
    if (voucher.mime?.startsWith('image/')) return true;
    const ref = (voucher.nombre_original || voucher.path || '').toLowerCase();
    return /\.(webp|jpe?g|png|gif)$/.test(ref);
}
