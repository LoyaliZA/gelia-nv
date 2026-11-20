import React from 'react';
import { AlertTriangle, BadgeCheck, CheckCircle2, Clock, Hourglass, Info, PauseCircle, XCircle } from 'lucide-react';
import { ESTADOS_RESGUARDO } from './estadosResguardoMunicipal';

const ICONOS = { info: Info, aviso: Clock, exito: CheckCircle2, peligro: XCircle };
const ICONO_ESPECIAL = {
    resguardo_vigente: PauseCircle,
    pendiente_integracion: Hourglass,
    pago_auditado: BadgeCheck,
    pago_rechazado: AlertTriangle,
};

/** Estados del resguardo con texto, icono y orden; el color nunca es lo único que los distingue. */
export default function EstadosResguardoMunicipal({ estados = [] }) {
    const visibles = estados.filter((clave) => ESTADOS_RESGUARDO[clave]);
    if (!visibles.length) return null;
    return (
        <ul className="flex flex-wrap gap-2 m-0 p-0 list-none" aria-label="Estado del resguardo">
            {visibles.map((clave) => {
                const { texto, tono } = ESTADOS_RESGUARDO[clave];
                const Icono = ICONO_ESPECIAL[clave] || ICONOS[tono] || Info;
                return (
                    <li key={clave} className={`gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--${tono === 'peligro' ? 'error' : tono}`}>
                        <Icono size={14} aria-hidden="true" />
                        <span>{texto}</span>
                    </li>
                );
            })}
        </ul>
    );
}
