export const ESTADO_PRESTAMO_BADGE = {
    activo: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
    pausado: 'bg-amber-500/10 text-amber-600 border-amber-500/20',
    liquidado: 'bg-slate-500/10 text-slate-600 border-slate-500/20',
    cancelado: 'bg-red-500/10 text-red-600 border-red-500/20',
};

export const ESTADO_PRESTAMO_LABELS = {
    activo: 'Activo',
    pausado: 'Pausado',
    liquidado: 'Liquidado',
    cancelado: 'Cancelado',
};

export const MODALIDAD_LABELS = {
    recurrente: 'Fija / Recurrente',
    unica_vez: 'Deducción única',
};

export const MODALIDAD_BADGE = {
    recurrente: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
    unica_vez: 'bg-violet-500/10 text-violet-600 border-violet-500/20',
};

export const TABS_ESTADO = ['TODAS', 'ACTIVOS', 'PAUSADOS', 'LIQUIDADOS', 'CANCELADOS'];

export const TAB_ESTADO_MAP = {
    TODAS: '',
    ACTIVOS: 'activo',
    PAUSADOS: 'pausado',
    LIQUIDADOS: 'liquidado',
    CANCELADOS: 'cancelado',
};
