import { GELIA_ESTADO_VIVO_TONO } from '@/utils/geliaTheme';

const BADGE_BASE = 'gelia-estado-vivo gelia-estado-vivo--compacto text-xs font-semibold';

export const ESTADO_SOLICITUD_BADGE = {
    respondida: `${BADGE_BASE} ${GELIA_ESTADO_VIVO_TONO.exito}`,
    incorrecta: `${BADGE_BASE} ${GELIA_ESTADO_VIVO_TONO.error}`,
    verificada: `${BADGE_BASE} ${GELIA_ESTADO_VIVO_TONO.info}`,
    cancelada: `${BADGE_BASE} ${GELIA_ESTADO_VIVO_TONO.neutro}`,
    revision: `${BADGE_BASE} ${GELIA_ESTADO_VIVO_TONO.aviso}`,
};

export const ESTADO_SOLICITUD_BADGE_MAP = {
    respondida: ESTADO_SOLICITUD_BADGE.respondida,
    incorrecta: ESTADO_SOLICITUD_BADGE.incorrecta,
    verificada: ESTADO_SOLICITUD_BADGE.verificada,
    cancelada: ESTADO_SOLICITUD_BADGE.cancelada,
};

export function badgeClaseEstadoSolicitud(nombreEstado) {
    const key = nombreEstado?.toLowerCase();
    return ESTADO_SOLICITUD_BADGE_MAP[key] ?? ESTADO_SOLICITUD_BADGE.revision;
}

const ETIQUETA_BASE = 'inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-semibold uppercase tracking-wide';

function nivelLista(nombre) {
    return (nombre || '').toUpperCase().replace('MAYOREO ', '').trim();
}

/** Clases alineadas al badge de lista. No usa el componente. */
export function claseEtiquetaLista(nombre) {
    const nivel = nivelLista(nombre);

    if (nivel.includes('PLATAFORMA')) {
        return `${ETIQUETA_BASE} border-indigo-500/30 bg-indigo-500/10 text-indigo-600`;
    }
    if (nivel.includes('BRONCE')) {
        // ponytail: style-exception tono bronce de lista, igual que el badge de descuento
        return `${ETIQUETA_BASE} border-[#cd7f32]/30 bg-[#cd7f32]/10 text-[#cd7f32]`;
    }
    if (nivel.includes('PLATA')) {
        return `${ETIQUETA_BASE} border-slate-400/30 bg-slate-400/10 text-slate-500`;
    }
    if (nivel.includes('ORO')) {
        return `${ETIQUETA_BASE} border-yellow-500/30 bg-yellow-500/10 text-yellow-600`;
    }
    if (nivel.includes('DIAMANTE')) {
        return 'relative inline-flex items-center gap-1.5 overflow-hidden rounded-md border border-cyan-400/40 bg-gradient-to-r from-cyan-500/10 to-blue-500/10 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-cyan-700 shadow-[0_0_8px_rgba(34,211,238,0.2)] dark:text-cyan-300';
    }
    if (nivel.includes('PUBLICO') || nivel.includes('PÚBLICO')) {
        return `${ETIQUETA_BASE} theme-element theme-border theme-text-muted`;
    }
    if (nivel.includes('COLABORADOR')) {
        return `${ETIQUETA_BASE} border-purple-500/30 bg-purple-500/10 text-purple-600`;
    }

    return `${ETIQUETA_BASE} border-blue-500/30 bg-blue-500/10 text-blue-600`;
}

export function esListaDiamante(nombre) {
    return nivelLista(nombre).includes('DIAMANTE');
}

export function claseEtiquetaTipoCliente(nombre) {
    const tipo = (nombre || '').toUpperCase();
    if (tipo.includes('REACTIV')) {
        return `${ETIQUETA_BASE} border-amber-500/30 bg-amber-500/10 text-amber-600`;
    }
    if (tipo.includes('NUEVO')) {
        return `${ETIQUETA_BASE} border-sky-500/30 bg-sky-500/10 text-sky-600`;
    }
    if (tipo.includes('ACTIV')) {
        return `${ETIQUETA_BASE} border-emerald-500/30 bg-emerald-500/10 text-emerald-600`;
    }
    return `${ETIQUETA_BASE} border-indigo-500/30 bg-indigo-500/10 text-indigo-600`;
}

export const PANEL_AVISO = 'rounded-lg border border-[color-mix(in_srgb,var(--color-aviso)_35%,transparent)] bg-[color-mix(in_srgb,var(--color-aviso)_12%,transparent)] p-2.5';
export const PANEL_ERROR = 'rounded-lg border border-[color-mix(in_srgb,var(--color-peligro)_35%,transparent)] bg-[color-mix(in_srgb,var(--color-peligro)_12%,transparent)] p-2.5';
export const PANEL_EXITO = 'rounded-lg border border-[color-mix(in_srgb,var(--color-exito)_35%,transparent)] bg-[color-mix(in_srgb,var(--color-exito)_12%,transparent)] p-2.5';
export const PANEL_NOTA = 'rounded-lg border theme-border theme-element p-2.5';
