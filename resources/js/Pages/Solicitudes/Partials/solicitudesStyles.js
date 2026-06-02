const BADGE_BASE = 'inline-flex items-center border';

export const ESTADO_SOLICITUD_BADGE = {
    respondida: `${BADGE_BASE} bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30`,
    incorrecta: `${BADGE_BASE} bg-red-50 text-red-700 border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/30`,
    verificada: `${BADGE_BASE} bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/30`,
    cancelada: `${BADGE_BASE} bg-gray-100 text-gray-500 border-gray-300 dark:bg-gray-500/15 dark:text-gray-400 dark:border-gray-500/30`,
    revision: `${BADGE_BASE} bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30`,
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
