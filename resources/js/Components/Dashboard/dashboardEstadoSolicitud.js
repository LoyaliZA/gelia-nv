import { AlertOctagon, CheckCircle2, CheckSquare, Clock } from 'lucide-react';

/** Mapeo de estado de solicitud operativa → tono GELIA + icono Lucide. */
export function estiloEstadoSolicitud(nombreEstado) {
    switch (nombreEstado?.toLowerCase()) {
        case 'respondida':
            return { icon: CheckCircle2, tono: 'exito' };
        case 'incorrecta':
            return { icon: AlertOctagon, tono: 'error' };
        case 'verificada':
            return { icon: CheckSquare, tono: 'info' };
        default:
            return { icon: Clock, tono: 'aviso' };
    }
}
