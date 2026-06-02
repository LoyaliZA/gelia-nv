import {
    RH_ESTADO_FLUJO_BADGE,
    RH_ESTADO_FLUJO_LABELS,
} from '../../rhModuleStyles';

export const ESTADO_DEDUCCION_BADGE = { ...RH_ESTADO_FLUJO_BADGE };

export const ESTADO_DEDUCCION_LABELS = { ...RH_ESTADO_FLUJO_LABELS };

export const TABS_ESTADO = ['TODAS', 'PENDIENTES', 'PROGRAMADAS', 'APLICADAS'];

export const TAB_ESTADO_MAP = {
    TODAS: '',
    PENDIENTES: 'pendiente',
    PROGRAMADAS: 'programado',
    APLICADAS: 'aplicado',
};
