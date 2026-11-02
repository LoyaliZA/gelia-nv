import CronometroVisualOperacion from './CronometroVisualOperacion';
import { esAvisoDisponibilidad, etiquetaEstadoVendedor } from './operacionUtils';

export default function AvisoDisponibilidadPersona({
    estado,
    cronometro = null,
    servidorAt,
}) {
    if (!esAvisoDisponibilidad(estado)) {
        return null;
    }

    const etiqueta = etiquetaEstadoVendedor(estado);
    const referenciaAt = cronometro?.referencia_at || cronometro?.referenciaAt;

    return (
        <span className="inline-flex items-center gap-1 shrink-0 gelia-estado-vivo gelia-estado-vivo--aviso gelia-estado-vivo--compacto text-xs font-semibold">
            <span>{etiqueta}</span>
            {referenciaAt ? (
                <>
                    <span aria-hidden>·</span>
                    <CronometroVisualOperacion
                        referenciaAt={referenciaAt}
                        servidorAt={servidorAt}
                        modo={cronometro?.modo || (estado === 'en_espera' ? 'restante' : 'transcurrido')}
                        variante="valor"
                    />
                </>
            ) : null}
        </span>
    );
}
