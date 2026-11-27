import React from 'react';
import { CheckCircle2, Clock, Layers, Receipt, Truck } from 'lucide-react';
import DetallePedidoSeccion from './DetallePedidoSeccion';
import { textoMultienvio } from '../EtiquetaMultienvio';

function estadoHermano(hermano) {
    if (['ENVIADO', 'ENTREGADO'].includes(hermano.estatus_fase)) return { Icono: Truck, texto: hermano.estatus_nombre || 'Enviado' };
    if (hermano.tiene_guia) return { Icono: Receipt, texto: hermano.estatus_nombre || 'Con guía' };
    if (hermano.pago_validado) return { Icono: CheckCircle2, texto: hermano.estatus_nombre || 'Pago validado' };

    return { Icono: Clock, texto: hermano.estatus_nombre || 'En proceso' };
}

/** Envíos del mismo Multienvío que quien consulta puede ver (el resumen ya omite los ocultos). */
export default function SeccionMultienvioDetalle({ pedido }) {
    const grupo = pedido?.multienvio;
    if (!grupo || typeof grupo !== 'object') return null;
    const hermanos = [...(grupo.hermanos || [])].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0));
    const titulo = textoMultienvio(grupo) || 'Multienvío';

    return (
        <DetallePedidoSeccion id="detalle-multienvio" icon={Layers} titulo="Multienvío">
            <p className="m-0 text-sm theme-text-main">
                {titulo}. Cada envío tiene su propio pedido, pago y remisión.
                {grupo.parcial ? ' Solo se muestran los envíos a los que tienes acceso.' : ''}
            </p>
            {hermanos.length > 0 && (
                <ol className="m-0 grid list-none gap-2 p-0" aria-label="Envíos del multienvío">
                    {hermanos.map((hermano) => {
                        const { Icono, texto } = estadoHermano(hermano);
                        const propio = hermano.id === pedido.id;
                        const hechos = [
                            hermano.pago_validado ? 'Pago validado' : null,
                            hermano.tiene_remision ? 'Remisión' : null,
                            hermano.tiene_guia ? 'Guía' : null,
                        ].filter(Boolean);

                        return (
                            <li key={hermano.id} aria-current={propio ? 'true' : undefined}
                                className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border theme-border px-3 py-2 text-sm">
                                <span className="font-bold theme-text-main">Envío {hermano.orden}</span>
                                <span className="min-w-0 break-words theme-text-main">Folio Wizerp: {hermano.folio_remision || '—'}</span>
                                <span className="min-w-0 break-words text-xs theme-text-muted">{hermano.folio}</span>
                                <span className="inline-flex items-center gap-1 theme-text-main">
                                    <Icono className="w-4 h-4 shrink-0" aria-hidden="true" />
                                    {texto}
                                </span>
                                {hechos.length > 0 && <span className="text-xs theme-text-muted">{hechos.join(' · ')}</span>}
                                {propio && <span className="text-xs font-semibold theme-text-muted">Este pedido</span>}
                            </li>
                        );
                    })}
                </ol>
            )}
        </DetallePedidoSeccion>
    );
}
