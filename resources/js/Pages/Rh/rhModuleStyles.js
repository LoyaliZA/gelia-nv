/** Paleta de badges RH (listados, shows, modales). */
export const RH_BADGE = {
    amber: 'bg-amber-500/10 text-amber-600 border-amber-500/20',
    emerald: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
    slate: 'bg-slate-500/10 text-slate-600 border-slate-500/20',
    blue: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
    rose: 'bg-rose-500/10 text-rose-600 border-rose-500/20',
    red: 'bg-red-500/10 text-red-600 border-red-500/20',
    violet: 'bg-violet-500/10 text-violet-600 border-violet-500/20',
};

/** Flujo nómina compartido: pendiente → programado → aplicado */
export const RH_ESTADO_FLUJO_BADGE = {
    pendiente: RH_BADGE.amber,
    programado: RH_BADGE.emerald,
    aplicado: RH_BADGE.slate,
};

export const RH_ESTADO_FLUJO_LABELS = {
    pendiente: 'Pendiente',
    programado: 'Programado',
    aplicado: 'Aplicado',
};

export const RH_ESTADO_DEDUCCION_BADGE = {
    ...RH_ESTADO_FLUJO_BADGE,
    pendiente_nomina: RH_BADGE.amber,
    pendiente_comision: RH_BADGE.blue,
};

export const RH_ESTADO_DEDUCCION_LABELS = {
    ...RH_ESTADO_FLUJO_LABELS,
    pendiente_nomina: 'Pendiente Nómina',
    pendiente_comision: 'Pendiente Comisión',
};

export const RH_ESTADO_PAGO_BADGE = {
    pendiente: RH_ESTADO_FLUJO_BADGE.pendiente,
    programado: RH_ESTADO_FLUJO_BADGE.programado,
};

export const RH_ESTADO_PAGO_LABELS = {
    pendiente: RH_ESTADO_FLUJO_LABELS.pendiente,
    programado: RH_ESTADO_FLUJO_LABELS.programado,
};

export const RH_ESTADO_BT_BADGE = {
    activa: RH_BADGE.amber,
    saldada: RH_BADGE.emerald,
};

export const RH_ESTADO_BT_LABELS = {
    activa: 'Activa',
    saldada: 'Saldada',
};

export const RH_ESTADO_PRESTAMO_BADGE = {
    activo: RH_BADGE.emerald,
    pausado: RH_BADGE.amber,
    liquidado: RH_BADGE.slate,
    cancelado: RH_BADGE.red,
};

export const RH_ESTADO_PRESTAMO_LABELS = {
    activo: 'Activo',
    pausado: 'Pausado',
    liquidado: 'Liquidado',
    cancelado: 'Cancelado',
};

export const RH_MODALIDAD_BADGE = {
    recurrente: RH_BADGE.blue,
    unica_vez: RH_BADGE.violet,
};

export const RH_MODALIDAD_LABELS = {
    recurrente: 'Fija / Recurrente',
    unica_vez: 'Deducción única',
};

export const RH_BADGE_SHELL_SM = 'px-2 py-1 rounded-lg text-[9px] font-black uppercase border';
export const RH_BADGE_SHELL_MD = 'px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border';

export const RH_CHIP_SHELL = 'inline-flex px-3 py-1.5 rounded-full text-[9px] font-black uppercase';
export const RH_CHIP_SHELL_SM = 'inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase';

export const RH_CHIP_ACTIVE = 'bg-emerald-500/10 text-emerald-500';
export const RH_CHIP_INACTIVE = 'bg-red-500/10 text-red-500';
export const RH_CHIP_POSITIVE = 'bg-emerald-500/10 text-emerald-500';
export const RH_CHIP_NEGATIVE = 'bg-red-500/10 text-red-500';

/**
 * @param {string|Record<string, string>} colorOrMap - clave de RH_BADGE o mapa de estados
 * @param {string} [key] - clave del mapa cuando colorOrMap es objeto
 * @param {'sm'|'md'} [size='sm']
 */
export function rhBadgeClass(colorOrMap, key, size = 'sm') {
    const shell = size === 'md' ? RH_BADGE_SHELL_MD : RH_BADGE_SHELL_SM;
    const colors = typeof colorOrMap === 'string' ? RH_BADGE[colorOrMap] : colorOrMap[key];
    return `${shell} ${colors ?? ''}`.trim();
}

/**
 * @param {'active'|'inactive'|'positive'|'negative'} variant
 * @param {'sm'|'md'} [size='md']
 */
export function rhChipClass(variant, size = 'md') {
    const shell = size === 'sm' ? RH_CHIP_SHELL_SM : RH_CHIP_SHELL;
    const tone = {
        active: RH_CHIP_ACTIVE,
        inactive: RH_CHIP_INACTIVE,
        positive: RH_CHIP_POSITIVE,
        negative: RH_CHIP_NEGATIVE,
    }[variant] ?? RH_CHIP_INACTIVE;
    return `${shell} ${tone}`.trim();
}
