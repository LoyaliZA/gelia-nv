export const ESTADO_DEDUCCION_BADGE = {
    pendiente_nomina: 'bg-amber-500/10 text-amber-600 border-amber-500/20',
    pendiente_comision: 'bg-blue-500/10 text-blue-600 border-blue-500/20',
    aplicado: 'bg-slate-500/10 text-slate-600 border-slate-500/20',
    pendiente: 'bg-amber-500/10 text-amber-600 border-amber-500/20',
    programado: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
};

export const ESTADO_DEDUCCION_LABELS = {
    pendiente_nomina: 'Pendiente Nómina',
    pendiente_comision: 'Pendiente Comisión',
    aplicado: 'Aplicado',
    pendiente: 'Pendiente',
    programado: 'Programado',
};

export const ORIGEN_DEDUCCION_LABELS = {
    nomina: 'Descuento vía Nómina',
    comisiones: 'Descuento vía Comisiones',
};

export const TABS_ESTADO = ['TODAS', 'PENDIENTE_NOMINA', 'PENDIENTE_COMISION', 'APLICADAS'];

export const TAB_ESTADO_MAP = {
    TODAS: '',
    PENDIENTE_NOMINA: 'pendiente_nomina',
    PENDIENTE_COMISION: 'pendiente_comision',
    APLICADAS: 'aplicado',
};
