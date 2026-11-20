import React from 'react';
import { CheckCircle2, Clock } from 'lucide-react';
import { accionPendienteRemision, estadoFisicoTexto, MECANISMOS_REMISION } from './estadosResguardoMunicipal';

/**
 * Cada remisión es una unidad operativa: folio, remisión, pago, dónde está, qué falta y quién lo hace.
 * `acciones(vinculo)` agrega botones propios de la superficie (desvincular, confirmar integración).
 */
export default function ConjuntoRemisionesMunicipal({ vinculos = [], acciones = null, vacio = 'Aún no hay remisiones vinculadas.' }) {
    if (!vinculos.length) return <p className="text-sm theme-text-muted m-0">{vacio}</p>;
    const integradas = vinculos.filter((item) => item.integrado).length;
    return (
        <div className="grid gap-2 min-w-0">
            <p className="text-xs theme-text-muted m-0" role="status">
                {integradas} de {vinculos.length} remisiones integradas. El conjunto no sale hasta integrar todas.
            </p>
            <ul className="grid gap-2 m-0 p-0 list-none">
                {vinculos.map((item) => {
                    const pendiente = accionPendienteRemision(item);
                    const Icono = item.integrado ? CheckCircle2 : Clock;
                    return (
                        <li key={item.origen_id} className="border theme-border rounded-xl px-3 py-2 grid gap-1 min-w-0">
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm theme-text-main">
                                <strong>{item.folio}</strong>
                                <span>Remisión {item.remision || 'sin número'}</span>
                                <span className="inline-flex items-center gap-1"><Icono size={14} aria-hidden="true" />{item.integrado ? 'Integrada' : estadoFisicoTexto(item.estado)}</span>
                            </div>
                            <p className="text-xs theme-text-muted m-0">
                                {item.pago_auditado ? 'Pago auditado' : 'Pago sin auditar'} · {MECANISMOS_REMISION[item.mecanismo] || 'Integración'}
                                {pendiente.area ? ` · Pendiente: ${pendiente.texto} (${pendiente.area})` : ''}
                                {!item.integrado && !item.puede_desvincular ? ' · Ya no se puede desvincular' : ''}
                            </p>
                            {acciones && <div className="flex flex-wrap gap-2">{acciones(item)}</div>}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
