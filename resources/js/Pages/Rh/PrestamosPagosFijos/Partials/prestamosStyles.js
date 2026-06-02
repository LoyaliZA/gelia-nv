import {
    RH_ESTADO_PRESTAMO_BADGE,
    RH_ESTADO_PRESTAMO_LABELS,
    RH_MODALIDAD_BADGE,
    RH_MODALIDAD_LABELS,
} from '../../rhModuleStyles';

export const ESTADO_PRESTAMO_BADGE = { ...RH_ESTADO_PRESTAMO_BADGE };

export const ESTADO_PRESTAMO_LABELS = { ...RH_ESTADO_PRESTAMO_LABELS };

export const MODALIDAD_LABELS = { ...RH_MODALIDAD_LABELS };

export const MODALIDAD_BADGE = { ...RH_MODALIDAD_BADGE };

export const TABS_ESTADO = ['TODAS', 'ACTIVOS', 'PAUSADOS', 'LIQUIDADOS', 'CANCELADOS'];

export const TAB_ESTADO_MAP = {
    TODAS: '',
    ACTIVOS: 'activo',
    PAUSADOS: 'pausado',
    LIQUIDADOS: 'liquidado',
    CANCELADOS: 'cancelado',
};
