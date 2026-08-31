import { geliaCardClass } from '../../../../utils/geliaTheme';

/** Radios por jerarquía (sobreescribe theme-card 2.5rem). */
export const RADIUS_METRICA = '!rounded-[22px]';
export const RADIUS_DESGLOSE = '!rounded-[18px]';
export const RADIUS_CONTENEDOR_DIA = '!rounded-[18px]';
export const RADIUS_PEDIDO = 'rounded-xl';
export const RADIUS_PEDIDO_CARD = '!rounded-xl';

/** Tipografía reporte detalle pedido */
export const SECCION_TITULO = 'text-[13px] md:text-sm font-semibold theme-text-main m-0';
export const ETIQUETA_FIN = 'text-xs font-medium theme-text-main/75';
export const IMPORTE_FIN = 'text-sm font-semibold tabular-nums theme-text-main';
export const META_DETALLE = 'text-xs theme-text-main/65';
export const DETALLE_SECCION = 'space-y-4';
export const DETALLE_PAD = 'p-4 md:p-5';

export function cardReportePagos(extra = '', radius = RADIUS_METRICA) {
    return geliaCardClass(`${radius} ${extra}`.trim());
}

export const LABEL_COBERTURA = {
    cubierto: 'Cubierto',
    parcial: 'Parcialmente cubierto',
    con_excedente: 'Con excedente',
    sin_pago: 'Pendiente de pago',
};

/** Etiqueta de cobertura con variante SAF cuando aplica. */
export function labelEstadoCobertura(pedidoOrEstado, safAplicado = 0) {
    const estado = typeof pedidoOrEstado === 'string' ? pedidoOrEstado : pedidoOrEstado?.estado_cobertura;
    const saf = typeof pedidoOrEstado === 'object'
        ? Number(pedidoOrEstado?.saf_aplicado ?? 0)
        : Number(safAplicado ?? 0);

    if (estado === 'cubierto' && saf > 0.005) {
        return 'Cubierto con saldo a favor';
    }

    return LABEL_COBERTURA[estado] || estado || '—';
}

export const LABEL_ESTADO_REVISION = {
    pendiente: 'Pendiente',
    en_revision: 'En revisión',
    verificado: 'Verificado',
    con_observaciones: 'Con observaciones',
    rechazado: 'Rechazado',
    confirmado: 'Verificado',
    con_diferencia: 'Con observaciones',
};

/** Texto semántico (rosa = marca/interacción, no estados). */
export const SEM_TEXTO = {
    exito: 'text-emerald-600 dark:text-emerald-400',
    advertencia: 'text-amber-700 dark:text-amber-400',
    critico: 'text-red-600 dark:text-red-400',
    info: 'text-sky-700 dark:text-sky-400',
    neutro: 'theme-text-muted',
};

const BADGE_BASE = 'inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full border whitespace-nowrap tabular-nums leading-tight';

export const SEM_BADGE = {
    exito: `${BADGE_BASE} border-emerald-500/40 text-emerald-800 dark:text-emerald-300 bg-emerald-500/15`,
    advertencia: `${BADGE_BASE} border-amber-500/45 text-amber-900 dark:text-amber-200 bg-amber-500/20`,
    critico: `${BADGE_BASE} border-red-500/45 text-red-800 dark:text-red-300 bg-red-500/15`,
    info: `${BADGE_BASE} border-sky-500/35 text-sky-800 dark:text-sky-300 bg-sky-500/12`,
    neutro: `${BADGE_BASE} theme-border theme-text-muted theme-element`,
};

/** Rosa GELIA para iconos de métricas (marca, no estados). */
export const ACCENT_MARCA = 'var(--color-primario)';

export const fmtIncidencias = (n) => {
    const v = Number(n ?? 0);
    if (v === 0) return 'Sin incidencias';
    if (v === 1) return '1 incidencia';
    return `${v.toLocaleString('es-MX')} incidencias`;
};

export const fmtMxn = (v) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(v || 0));

/** @param {string|null|undefined} value */
export const fmtFechaSolo = (value) => {
    if (!value) return null;
    const d = new Date(String(value));
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
};

/** @param {string|null|undefined} value */
export const fmtHoraSolo = (value) => {
    if (!value) return null;
    const d = new Date(String(value));
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false });
};

export function badgeCoberturaExhibicion(ex, exhibiciones = []) {
    if (ex?.activo_para_cobertura) {
        return { label: 'Incluido', badge: SEM_BADGE.exito, texto: 'Incluido en cobertura' };
    }
    const fueSustituido = exhibiciones.some((o) => o.reemplaza_pago_id === ex.pago_id);
    if (fueSustituido) {
        return { label: 'Sustituido', badge: SEM_BADGE.info, texto: 'Sustituido' };
    }
    return { label: 'No contabilizado', badge: SEM_BADGE.neutro, texto: 'No contabilizado' };
}

export const fmtTamanoArchivo = (bytes) => {
    const n = Number(bytes || 0);
    if (n <= 0) return null;
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${Math.round(n / 1024)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
};

export const fmtTipoArchivo = (mimeType, nombre) => {
    const mime = (mimeType || '').toLowerCase();
    if (mime.includes('pdf')) return 'PDF';
    if (mime.startsWith('image/')) return 'Imagen';
    const ref = (nombre || '').toLowerCase();
    if (ref.endsWith('.pdf')) return 'PDF';
    return 'Archivo';
};

/** @param {string|null|undefined} value */
export const fmtFechaHora = (value) => {
    if (!value) return '—';
    const d = new Date(String(value));
    if (Number.isNaN(d.getTime())) return '—';
    const fecha = d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
    const hora = d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false });
    return `${fecha}, ${hora}`;
};

const EPS_COBERTURA = 0.005;

/** Una sola línea de resultado: pendiente, excedente, tolerancia o cubierto. */
export function lineaResultadoCobertura(financiero) {
    const diferencia = Number(financiero?.diferencia ?? 0);
    const excedente = Number(financiero?.excedente ?? 0);
    const tolerancia = Number(financiero?.tolerancia_aplicada ?? 0);
    const estado = financiero?.estado_cobertura;
    const pendiente = Math.max(0, diferencia);

    if (pendiente <= EPS_COBERTURA && excedente <= EPS_COBERTURA) {
        return { label: 'Diferencia', texto: `${fmtMxn(0)} — Cubierto`, tono: 'exito' };
    }
    if (excedente > EPS_COBERTURA && (excedente > tolerancia + EPS_COBERTURA || estado === 'con_excedente')) {
        return { label: 'Excedente', texto: fmtMxn(excedente), tono: 'advertencia' };
    }
    if (pendiente > EPS_COBERTURA && (pendiente > tolerancia + EPS_COBERTURA || estado === 'parcial' || estado === 'sin_pago')) {
        return {
            label: 'Pendiente',
            texto: fmtMxn(pendiente),
            tono: estado === 'sin_pago' ? 'critico' : 'advertencia',
        };
    }
    const residual = pendiente > EPS_COBERTURA ? pendiente : excedente;
    return {
        label: 'Diferencia dentro de tolerancia',
        texto: fmtMxn(residual),
        tono: 'info',
    };
}

if (import.meta.env?.DEV) {
    const cubierto = lineaResultadoCobertura({ diferencia: 0, excedente: 0, tolerancia_aplicada: 0.44, estado_cobertura: 'cubierto' });
    console.assert(cubierto.tono === 'exito' && cubierto.texto.includes('Cubierto'), 'lineaResultadoCobertura:cubierto');
    const exc = lineaResultadoCobertura({ diferencia: -250, excedente: 250, tolerancia_aplicada: 0.44, estado_cobertura: 'con_excedente' });
    console.assert(exc.label === 'Excedente', 'lineaResultadoCobertura:excedente');
    console.assert(labelEstadoCobertura({ estado_cobertura: 'cubierto', saf_aplicado: 100 }) === 'Cubierto con saldo a favor', 'labelEstadoCobertura:saf');
    console.assert(labelEstadoCobertura('parcial') === 'Parcialmente cubierto', 'labelEstadoCobertura:parcial');
}

export const fmtContador = (n, unidad) => {
    const v = Number(n || 0);
    const palabra = v === 1 ? unidad.replace(/s$/, '') : unidad;
    return `${v.toLocaleString('es-MX')} ${palabra}`;
};

export const fmtPedidosValidados = (n) => {
    const v = Number(n || 0);
    if (v === 1) return '1 pedido validado';
    return `${v.toLocaleString('es-MX')} pedidos validados`;
};

export const fmtVouchersLabel = (n) => {
    const v = Number(n || 0);
    if (v <= 1) return 'Voucher';
    return `${v.toLocaleString('es-MX')} vouchers`;
};

export const BTN_SECONDARY =
    'inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border theme-border theme-element theme-text-main text-xs font-semibold hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all disabled:opacity-50';

export const chipClass =
    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border theme-border theme-element theme-text-main';

export const badgeCobertura = (estado) => {
    switch (estado) {
        case 'cubierto':
            return SEM_BADGE.exito;
        case 'parcial':
        case 'con_excedente':
            return SEM_BADGE.advertencia;
        case 'sin_pago':
            return SEM_BADGE.critico;
        default:
            return SEM_BADGE.neutro;
    }
};

export const tonoRevisionEstado = (estado) => {
    switch (estado) {
        case 'verificado':
        case 'confirmado':
            return SEM_TEXTO.exito;
        case 'con_observaciones':
        case 'con_diferencia':
            return SEM_TEXTO.advertencia;
        case 'rechazado':
            return SEM_TEXTO.critico;
        case 'pendiente':
        case 'en_revision':
            return SEM_TEXTO.info;
        default:
            return SEM_TEXTO.neutro;
    }
};

export const labelRevisionEstado = (estado) =>
    LABEL_ESTADO_REVISION[estado] || estado || '—';

export const cardMetricaClass = 'p-5 md:p-6 flex items-center gap-5 min-w-0';
