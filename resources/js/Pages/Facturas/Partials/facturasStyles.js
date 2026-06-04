import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../../utils/geliaTheme';

/** Acento del módulo: hereda el tema global de AppLayout (--color-primario). */
export const ACCENT = 'var(--color-primario)';

export const ESTADO_BADGE = {
    1: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
    2: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    3: 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-200 border-emerald-500/30',
    4: 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30',
};

export const ESTADO_LABELS = {
    Pendiente: 'Pendiente',
    Respondida: 'Respondida',
    Verificada: 'Verificada',
    Incorrecta: 'Incorrecta',
    Cancelada: 'Cancelada',
};

export const BTN_PRIMARY = `${THEME_BTN_PRIMARY} theme-btn-primary--compact`;
export const BTN_SECONDARY = `${THEME_BTN_SECONDARY} theme-btn-secondary--compact`;

/** @deprecated Usar ACCENT */
export const FACTURA_ACCENT = ACCENT;

export { GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK };

export function urlArchivoFactura(facturaId, tipo, indice = 0, options = {}) {
    const base = route('facturas.archivo', { factura: facturaId, tipo });
    const params = new URLSearchParams();
    if (tipo === 'voucher') {
        params.set('indice', String(indice));
    }
    if (options.descargar) {
        params.set('descargar', '1');
    }
    const qs = params.toString();
    return qs ? `${base}?${qs}` : base;
}

function numeroClienteFactura(factura) {
    const raw = factura?.cliente?.numero_cliente
        ?? factura?.datos_fiscales?.numero_cliente
        ?? null;
    if (!raw) return 'sin-cliente';
    return String(raw).replace(/[^\w-]+/g, '_') || 'sin-cliente';
}

function fechaFacturaPdf(factura) {
    const raw = factura?.respondida_at ?? factura?.created_at;
    if (!raw) {
        return new Date().toISOString().slice(0, 10);
    }
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) {
        return new Date().toISOString().slice(0, 10);
    }
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

/** Nombre de descarga: Factura_NumeroDelCliente_fecha.pdf */
export function nombreArchivoFacturaPdf(factura) {
    return `Factura_${numeroClienteFactura(factura)}_${fechaFacturaPdf(factura)}.pdf`;
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
