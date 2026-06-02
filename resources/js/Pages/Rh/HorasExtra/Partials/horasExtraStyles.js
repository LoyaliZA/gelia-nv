import {
    RH_ESTADO_FLUJO_BADGE,
    RH_ESTADO_FLUJO_LABELS,
} from '../../rhModuleStyles';

export const ESTADO_PAGO_BADGE = {
    pendiente: RH_ESTADO_FLUJO_BADGE.pendiente,
    programado: RH_ESTADO_FLUJO_BADGE.programado,
};

export const ESTADO_PAGO_LABELS = {
    pendiente: RH_ESTADO_FLUJO_LABELS.pendiente,
    programado: RH_ESTADO_FLUJO_LABELS.programado,
};

export const TABS_ESTADO = ['TODAS', 'PENDIENTES', 'PROGRAMADAS'];

export const TAB_ESTADO_MAP = {
    TODAS: '',
    PENDIENTES: 'pendiente',
    PROGRAMADAS: 'programado',
};
