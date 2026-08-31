import { geliaCardClass } from '../../../../utils/geliaTheme';
import { puedePermiso } from '../../../../utils/permisos';

/** Radios por jerarquía (sobreescribe theme-card 2.5rem). */
export const RADIUS_METRICA = '!rounded-[22px]';
export const RADIUS_DESGLOSE = '!rounded-[18px]';
export const RADIUS_CONTENEDOR_DIA = '!rounded-[18px]';
export const RADIUS_PEDIDO = 'rounded-xl';
export const RADIUS_PEDIDO_CARD = '!rounded-xl';

/** Tipografía reporte — escala unificada */
export const NOMBRE_CLIENTE = 'text-sm font-semibold theme-text-main';
export const FOLIO_META = 'text-xs theme-text-muted';
export const SECCION_TITULO = 'text-[13px] font-semibold theme-text-main m-0';
export const ETIQUETA_FIN = 'text-[11px] md:text-xs font-medium theme-text-main/75';
export const IMPORTE_FIN = 'text-[15px] md:text-base font-semibold tabular-nums theme-text-main';
export const META_DETALLE = 'text-xs theme-text-main/65 leading-relaxed';
export const TABLA_TEXTO = 'text-[13px] theme-text-main leading-snug';
export const TABLA_TH = 'text-[11px] md:text-xs font-semibold uppercase tracking-wide theme-text-main/75';
export const TABLA_META = 'text-xs theme-text-main/65 leading-relaxed';
export const DETALLE_SECCION = 'space-y-3 md:space-y-4';
export const DETALLE_PAD = 'p-4 md:p-5';
export const CARD_PAD = 'p-4 md:p-5';
export const BLOQUE_GAP = 'space-y-3 md:space-y-4';

/** Animación suave de despliegue/cierre para acordeones del reporte. */
export const ACORDEON_CONTENIDO_GRID = 'grid transition-[grid-template-rows] duration-300 ease-out';
export const ACORDEON_CONTENIDO_INNER = 'overflow-hidden min-h-0';
export const ACORDEON_ANIMACION_MS = 300;

/** Desplaza la vista al acordeón recién expandido, tras su animación. */
export function scrollAlExpandirAcordeon(elemento, { block = 'start', delayMs = ACORDEON_ANIMACION_MS } = {}) {
    if (!elemento) return;
    window.requestAnimationFrame(() => {
        window.setTimeout(() => {
            elemento.scrollIntoView({ behavior: 'smooth', block });
        }, delayMs);
    });
}

export function acordeonContenidoGridClass(abierto) {
    return `${ACORDEON_CONTENIDO_GRID} ${abierto ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`;
}

export function cardReportePagos(extra = '', radius = RADIUS_METRICA) {
    return geliaCardClass(`${radius} ${extra}`.trim());
}

export const LABEL_COBERTURA = {
    cubierto: 'Cubierto',
    parcial: 'Parcial',
    con_excedente: 'Excedente',
    sin_pago: 'Pendiente de pago',
};

export const CATEGORIA_COBERTURA = 'Cobertura financiera';
export const CATEGORIA_VALIDACION = 'Validación del pago';
export const CATEGORIA_ADMIN = 'Revisión administrativa';

/** Etiqueta de cobertura con variante SAF cuando aplica. */
export function labelEstadoCobertura(pedidoOrEstado, safAplicado = 0) {
    const estado = typeof pedidoOrEstado === 'string' ? pedidoOrEstado : pedidoOrEstado?.estado_cobertura;
    const saf = typeof pedidoOrEstado === 'object'
        ? Number(pedidoOrEstado?.saf_aplicado ?? 0)
        : Number(safAplicado ?? 0);

    if (estado === 'cubierto' && saf > 0.005) {
        return 'Cubierto con SAF';
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
    exitoSuave: 'text-emerald-700/85 dark:text-emerald-400/90',
    advertencia: 'text-amber-700 dark:text-amber-400',
    critico: 'text-red-600 dark:text-red-400',
    info: 'text-sky-700 dark:text-sky-400',
    neutro: 'theme-text-muted',
};

const BADGE_BASE = 'inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full border whitespace-nowrap tabular-nums leading-tight';

export const SEM_BADGE = {
    exito: `${BADGE_BASE} border-emerald-500/40 text-emerald-800 dark:text-emerald-300 bg-emerald-500/15`,
    exitoSuave: `${BADGE_BASE} border-emerald-500/28 text-emerald-800/90 dark:text-emerald-300/90 bg-emerald-500/[0.08]`,
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
        return { label: 'Incluido', badge: SEM_BADGE.exito, texto: 'Incluido' };
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
        return { label: 'Diferencia', texto: `${fmtMxn(0)} — Cubierto`, tono: 'exitoSuave' };
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
    console.assert(cubierto.tono === 'exitoSuave' && cubierto.texto.includes('Cubierto'), 'lineaResultadoCobertura:cubierto');
    const exc = lineaResultadoCobertura({ diferencia: -250, excedente: 250, tolerancia_aplicada: 0.44, estado_cobertura: 'con_excedente' });
    console.assert(exc.label === 'Excedente', 'lineaResultadoCobertura:excedente');
    console.assert(labelEstadoCobertura({ estado_cobertura: 'cubierto', saf_aplicado: 100 }) === 'Cubierto con SAF', 'labelEstadoCobertura:saf');
    console.assert(labelEstadoCobertura('parcial') === 'Parcial', 'labelEstadoCobertura:parcial');
    console.assert(
        labelConteoExhibicionesRevisadas(1, 3) === '1 de 3 exhibiciones revisadas',
        'labelConteoExhibicionesRevisadas:plural',
    );
    console.assert(
        labelConteoExhibicionesRevisadas(1, 1) === '1 de 1 exhibición revisada',
        'labelConteoExhibicionesRevisadas:singular',
    );
    console.assert(
        labelConteoExhibicionesRevisadas(0, 1) === '0 de 1 exhibición revisada',
        'labelConteoExhibicionesRevisadas:cero-singular',
    );
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

/** Base compartida: altura, radio, tipografía e iconos uniformes en toda la zona de acciones. */
const BTN_BASE =
    'inline-flex items-center justify-center gap-2 min-h-9 h-9 px-3 rounded-xl text-[13px] font-semibold leading-none whitespace-nowrap transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--theme-surface)] disabled:opacity-50 disabled:cursor-not-allowed shrink-0';

export const BTN_ICON = 'w-4 h-4 shrink-0';

/** Neutral: Ver voucher, Remisión, Cancelar. */
export const BTN_NEUTRAL = `${BTN_BASE} border theme-border theme-element theme-text-main hover:bg-black/[0.04] dark:hover:bg-white/[0.04] hover:border-[color-mix(in_srgb,var(--theme-text-main)_22%,var(--theme-border))]`;

/** @deprecated Usar BTN_NEUTRAL */
export const BTN_SECONDARY = BTN_NEUTRAL;

/** Principal: Aprobar pedido / exhibición. */
export const BTN_OK = `${BTN_BASE} border border-emerald-600 bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 hover:border-emerald-700 active:bg-emerald-800 disabled:hover:bg-emerald-600`;

/** Secundario destructivo: Marcar con error (borde rojo, sin relleno). */
export const BTN_ERR = `${BTN_BASE} border border-red-500/40 theme-element text-red-700 dark:text-red-400 hover:border-red-500/55 hover:bg-red-500/[0.06] hover:text-red-700 dark:hover:text-red-400`;

/** Discreto: Cambiar revisión y acciones terciarias. */
export const BTN_LINK =
    'inline-flex items-center justify-center gap-1.5 min-h-9 px-2 text-[13px] font-semibold theme-text-muted hover:text-[var(--color-primario)] underline-offset-2 hover:underline transition-colors disabled:opacity-50 disabled:cursor-not-allowed shrink-0';

export const GRUPO_ACCIONES = 'flex flex-wrap items-center gap-2';

export const chipClass =
    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border theme-border theme-element theme-text-main';

export const badgeCobertura = (estado) => {
    switch (estado) {
        case 'cubierto':
            return SEM_BADGE.exitoSuave;
        case 'parcial':
        case 'con_excedente':
            return SEM_BADGE.advertencia;
        case 'sin_pago':
            return SEM_BADGE.critico;
        default:
            return SEM_BADGE.neutro;
    }
};

export const badgeValidacionEstado = (estado) => {
    switch (estado) {
        case 'verificado':
        case 'confirmado':
            return SEM_BADGE.exito;
        case 'con_observaciones':
        case 'con_diferencia':
            return SEM_BADGE.advertencia;
        case 'rechazado':
            return SEM_BADGE.critico;
        case 'pendiente':
        case 'en_revision':
            return SEM_BADGE.info;
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

export const LABEL_ADMIN_ESTADO = {
    pendiente: 'Pendiente',
    confirmado: 'Aprobado',
    con_error: 'Con error',
    parcial: 'Revisión parcial',
};

export const labelAdminEstado = (estado) =>
    LABEL_ADMIN_ESTADO[estado] || estado || '—';

export function labelConteoExhibicionesRevisadas(revisadas, total) {
    const revisadasNum = Number(revisadas ?? 0);
    const totalNum = Number(total ?? 0);
    if (totalNum <= 0) return null;
    const palabra = totalNum === 1 ? 'exhibición revisada' : 'exhibiciones revisadas';
    return `${revisadasNum.toLocaleString('es-MX')} de ${totalNum.toLocaleString('es-MX')} ${palabra}`;
}

/** @deprecated Usar labelAdminEstado */
export const LABEL_ADMIN_ESTADO_CORTO = LABEL_ADMIN_ESTADO;

/** @deprecated Usar labelAdminEstado */
export const labelAdminEstadoCorto = labelAdminEstado;

export const badgeAdminEstado = (estado) => {
    switch (estado) {
        case 'confirmado':
            return SEM_BADGE.exito;
        case 'con_error':
            return SEM_BADGE.critico;
        case 'parcial':
            return SEM_BADGE.info;
        case 'pendiente':
            return SEM_BADGE.advertencia;
        default:
            return SEM_BADGE.neutro;
    }
};

export function puedeAccionesAdmin(auth) {
    return {
        puedeConfirmar: puedePermiso(auth, 'reportes.pagos_pedidos.confirmar_admin'),
        puedeReportar: puedePermiso(auth, 'reportes.pagos_pedidos.reportar_error_admin'),
    };
}

export const cardMetricaClass = 'p-5 md:p-6 flex items-center gap-5 min-w-0';
