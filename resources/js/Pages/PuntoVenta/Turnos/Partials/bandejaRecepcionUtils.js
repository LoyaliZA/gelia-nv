import { mensajeErrorOperacionTurno, REFRESCO_TABLERO_MS } from './tableroVentasUtils';

export const REFRESCO_BANDEJA_MS = REFRESCO_TABLERO_MS;

export function puedeDarBajaCola(turno, permisos) {
    return Boolean(
        permisos?.baja_cola
        && turno?.estado === 'EN_COLA'
        && turno?.puede_baja_cola
    );
}

export function mensajeErrorBandejaRecepcion(err) {
    const status = err?.response?.status;
    if (status === 403) {
        return 'No tienes permiso para consultar la bandeja de turnos.';
    }
    return mensajeErrorOperacionTurno(err, 'consulta de la bandeja');
}

export {
    claveIdempotenciaOperacionTurno,
    esConflictoVersionTurno,
    mensajeErrorOperacionTurno,
    renovarClaveIdempotenciaOperacionTurno,
} from './tableroVentasUtils';
