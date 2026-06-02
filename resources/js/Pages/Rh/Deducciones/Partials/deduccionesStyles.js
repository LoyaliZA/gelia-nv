import {
    RH_ESTADO_DEDUCCION_BADGE,
    RH_ESTADO_DEDUCCION_LABELS,
} from '../../rhModuleStyles';

export const ESTADO_DEDUCCION_BADGE = { ...RH_ESTADO_DEDUCCION_BADGE };

export const ESTADO_DEDUCCION_LABELS = { ...RH_ESTADO_DEDUCCION_LABELS };

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
