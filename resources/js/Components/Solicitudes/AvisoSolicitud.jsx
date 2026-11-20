import React from 'react';
import { AlertOctagon, AlertTriangle, CheckCircle2, CheckSquare, CreditCard, Info, MessageSquare } from 'lucide-react';

const ICONO_POR_TONO = {
    info: Info,
    aviso: AlertTriangle,
    peligro: AlertOctagon,
    exito: CheckCircle2,
    neutro: MessageSquare,
    pago: CreditCard,
    verificado: CheckSquare,
};

/**
 * Aviso del módulo Solicitudes. El tono distingue una alerta grave (peligro), una advertencia (aviso),
 * una confirmación (exito), información general (info) y una nota sin carga de estado (neutro).
 * Tonos de avance: exito (aprobada, verde), pago (pago aprobado, azul) y verificado (validada, celeste).
 * El icono y el título acompañan al color; `anunciar` lo expone como alerta solo cuando aparece por una acción.
 */
export default function AvisoSolicitud({
    tono = 'info',
    titulo,
    icono,
    compacto = false,
    anunciar = false,
    className = '',
    children,
}) {
    const Icono = icono ?? ICONO_POR_TONO[tono] ?? Info;

    return (
        <div
            className={`gelia-aviso-solicitud ${compacto ? 'gelia-aviso-solicitud--compacto' : ''} ${className}`.trim()}
            data-tono={tono}
            role={anunciar ? (tono === 'peligro' ? 'alert' : 'status') : undefined}
        >
            <Icono className="gelia-aviso-solicitud-icono" aria-hidden="true" />
            <div className="gelia-aviso-solicitud-cuerpo">
                {titulo && <p className="gelia-aviso-solicitud-titulo">{titulo}</p>}
                {children != null && children !== false && (
                    <div className="gelia-aviso-solicitud-texto">{children}</div>
                )}
            </div>
        </div>
    );
}
