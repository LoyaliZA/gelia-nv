import { geliaCardClass, THEME_BTN_PRIMARY, THEME_INPUT as GELIA_THEME_INPUT, THEME_LABEL } from '../../../../utils/geliaTheme';

export const BTN_SECONDARY =
    'px-4 py-2.5 rounded-xl md:rounded-2xl text-xs font-semibold theme-element theme-border border theme-text-main disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--theme-surface)]';

/** Hover de fila en tablas densas (tokens tema). */
export const RESGUARDOS_TABLA_FILA =
    'border-b theme-border transition-colors hover:bg-black/5 dark:hover:bg-white/5';

/** Cabecera sticky en tablas con scroll horizontal (tablet/desktop). */
export const RESGUARDOS_TABLA_HEAD =
    'sticky top-0 z-[1] theme-surface border-b theme-border shadow-[0_1px_0_0_var(--theme-border)]';

export const RESGUARDOS_FILTRO_LABEL = `${THEME_LABEL} text-xs font-semibold theme-text-muted`;

export const RESGUARDOS_STICKY_SELECCION =
    'p-0 sticky bottom-0 md:bottom-3 z-20 flex flex-col overflow-hidden border theme-border shadow-lg pb-[max(0.5rem,env(safe-area-inset-bottom))]';

export const RESGUARDOS_BTN_ICON_TABLA =
    'inline-flex items-center justify-center min-h-[44px] min-w-[44px] rounded-xl border theme-border theme-element theme-text-main hover:border-[var(--color-primario)]/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] disabled:opacity-50';

/** Botones «Tomar foto» / «Galería» en formularios de evidencia PDV. */
export const BTN_CAPTURA_EVIDENCIA =
    'inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2.5 rounded-xl border theme-border theme-element text-xs font-semibold theme-text-main cursor-pointer disabled:opacity-50 hover:border-[var(--color-primario)]/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)]';

export const ICONO_CAPTURA_EVIDENCIA = 'w-4 h-4 shrink-0 text-[var(--color-primario)]';

/** Extiende el input global (color/placeholder vía --theme-text-main) con el radio PDV resguardos. */
export const THEME_INPUT =
    `${GELIA_THEME_INPUT} rounded-2xl px-4 py-3 text-sm font-semibold focus:border-[var(--color-primario)]`;

export const THEME_SELECT = `${THEME_INPUT} appearance-none`;

export function formatearFechaOperativa(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '—';
    }
}

/** Formato compacto para tarjetas móviles: 15/SEP - 11:38 AM */
export function formatearFechaCompacta(value) {
    if (!value) return '—';
    try {
        const fecha = new Date(value);
        const dia = fecha.toLocaleDateString('es-MX', { day: '2-digit' });
        const mes = fecha.toLocaleDateString('es-MX', { month: 'short' }).replace('.', '').toUpperCase();
        const hora = fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: true });
        return `${dia}/${mes} - ${hora}`;
    } catch {
        return '—';
    }
}

export const TARJETA_RECEPCION_METRICA =
    'flex items-center gap-2.5 rounded-xl border theme-border theme-element p-2.5 min-w-0';

export const TARJETA_RECEPCION_ICONO =
    'flex items-center justify-center w-9 h-9 rounded-lg shrink-0';

export const TARJETA_RECEPCION_PIE =
    'w-full min-h-[48px] rounded-xl border-2 font-black uppercase tracking-widest text-[10px] inline-flex items-center justify-center gap-2 transition-colors';

/** Acción principal 1-clic en tarjeta de recepción gerente. */
export const BTN_ACCION_RECEPCION_TARJETA =
    `${THEME_BTN_PRIMARY} w-full min-h-[48px] theme-btn-primary--compact !text-[10px] !tracking-widest`;

/** Acción secundaria en tarjeta de recepción (p. ej. ver detalle). */
export const BTN_SECUNDARIO_RECEPCION_TARJETA =
    `${BTN_SECONDARY} w-full min-h-[48px] inline-flex items-center justify-center gap-2 no-underline hover:border-[var(--color-primario)]/40`;

/** Tono semántico de Gelia. Claro y oscuro salen de las variables del tema. */
export const TONO_PELIGRO = 'bg-[color-mix(in_srgb,var(--color-peligro)_15%,transparent)] text-[var(--color-peligro)]';
export const TONO_AVISO = 'bg-[color-mix(in_srgb,var(--color-aviso)_15%,transparent)] text-[var(--color-aviso)]';
export const TONO_EXITO = 'bg-[color-mix(in_srgb,var(--color-exito)_15%,transparent)] text-[var(--color-exito)]';
export const TONO_INFO = 'bg-[color-mix(in_srgb,var(--color-info)_15%,transparent)] text-[var(--color-info)]';
export const TONO_PRIMARIO = 'bg-[color-mix(in_srgb,var(--color-primario)_15%,transparent)] text-[var(--color-primario)]';
export const TONO_NEUTRO = 'bg-[color-mix(in_srgb,var(--theme-text-main)_6%,transparent)] theme-text-muted';

export const TEXTO_PELIGRO = 'text-[var(--color-peligro)]';
export const TEXTO_AVISO = 'text-[var(--color-aviso)]';
export const TEXTO_EXITO = 'text-[var(--color-exito)]';
export const TEXTO_INFO = 'text-[var(--color-info)]';
export const TEXTO_PRIMARIO = 'text-[var(--color-primario)]';

export const BORDE_AVISO = 'border-[color-mix(in_srgb,var(--color-aviso)_35%,transparent)]';
export const BORDE_EXITO = 'border-[color-mix(in_srgb,var(--color-exito)_35%,transparent)]';
export const BORDE_INFO = 'border-[color-mix(in_srgb,var(--color-info)_35%,transparent)]';
export const BORDE_PRIMARIO = 'border-[color-mix(in_srgb,var(--color-primario)_35%,transparent)]';

export const FONDO_AVISO = 'bg-[color-mix(in_srgb,var(--color-aviso)_10%,transparent)]';
export const FONDO_PELIGRO = 'bg-[color-mix(in_srgb,var(--color-peligro)_10%,transparent)]';
export const FONDO_PRIMARIO = 'bg-[color-mix(in_srgb,var(--color-primario)_8%,transparent)]';

export const ANILLO_AVISO = 'ring-[color-mix(in_srgb,var(--color-aviso)_45%,transparent)]';
export const ANILLO_PELIGRO = 'ring-[color-mix(in_srgb,var(--color-peligro)_45%,transparent)]';

export const BADGE_BASE = 'inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase tracking-wide';

export const ICONO_METRICA_INFO = `${TARJETA_RECEPCION_ICONO} ${TONO_INFO}`;
export const ICONO_METRICA_EXITO = `${TARJETA_RECEPCION_ICONO} ${TONO_EXITO}`;

/** Barra inferior de acciones masivas. Usa el botón principal del tema. */
export const BTN_ACCION_MASIVA_GERENTE =
    `${THEME_BTN_PRIMARY} theme-btn-primary--compact w-full min-h-[52px] !text-[11px] !tracking-[0.12em] disabled:opacity-60 disabled:pointer-events-none`;

export function claseTextoPlazo(clasificacion) {
    if (clasificacion === 'vencido') return `font-bold ${TEXTO_PELIGRO}`;
    if (clasificacion === 'rezagado' || clasificacion === 'proximo_a_vencer') return `font-bold ${TEXTO_AVISO}`;
    return 'theme-text-muted';
}

export function badgeEstadoResguardo(estado) {
    const mapa = {
        pendiente_recepcion: TONO_AVISO,
        recibido: TONO_EXITO,
        en_recepcion: TONO_INFO,
        en_custodia: TONO_INFO,
        entregado: TONO_EXITO,
        devuelto: TONO_NEUTRO,
    };
    return mapa[estado] || TONO_NEUTRO;
}

export function badgeEstadoIncidencia(estado) {
    const mapa = {
        abierta: TONO_AVISO,
        autorizada: TONO_EXITO,
        cerrada: TONO_NEUTRO,
    };
    return mapa[estado] || TONO_NEUTRO;
}

export function badgeAntiguedad(clave) {
    const mapa = {
        rezagado: TONO_AVISO,
        proximo_a_vencer: TONO_AVISO,
        vencido: TONO_PELIGRO,
    };
    return mapa[clave] || TONO_NEUTRO;
}

export function tonoAlertaAntiguedad(clave) {
    if (clave === 'vencido') {
        return { tone: TEXTO_PELIGRO, ring: ANILLO_PELIGRO, bg: FONDO_PELIGRO };
    }
    return { tone: TEXTO_AVISO, ring: ANILLO_AVISO, bg: FONDO_AVISO };
}

export function tarjetaResguardoClass(resguardo) {
    const base = `${geliaCardClass()} p-4 space-y-3`;
    if (resguardo?.clasificaciones?.vencido) return `${base} ring-1 ${ANILLO_PELIGRO}`;
    if (resguardo?.clasificaciones?.proximo_a_vencer || resguardo?.clasificaciones?.rezagado) {
        return `${base} ring-1 ${ANILLO_AVISO}`;
    }
    if ((resguardo?.incidencias_abiertas_count || 0) > 0) return `${base} ring-1 ${ANILLO_AVISO}`;
    return base;
}
