import React from 'react';
import { AlertTriangle, CheckCircle2, Clock } from 'lucide-react';

const FASE_PENDIENTE = 'PENDIENTE_AUXILIAR';
const FASE_RECHAZADO = 'RECHAZADO_VENDEDORA';

export function esGrupoMultienvio(pedido) {
    return (pedido?.multienvio?.hermanos || []).length > 1;
}

export function estadoRevisionEnvio(hermano) {
    const fase = hermano?.estatus_fase;
    if (fase === FASE_RECHAZADO) return { Icono: AlertTriangle, texto: 'Con observación' };
    if (fase && fase !== FASE_PENDIENTE) return { Icono: CheckCircle2, texto: 'Aprobado' };
    if (hermano?.pago_validado && hermano?.tiene_remision) return { Icono: CheckCircle2, texto: 'Remisionado' };
    if (hermano?.pago_validado) return { Icono: CheckCircle2, texto: 'Pago validado' };

    return { Icono: Clock, texto: 'Pago por validar' };
}

export function progresoAprobadosEnvios(hermanos) {
    const lista = Array.isArray(hermanos) ? hermanos : [];
    const aprobados = lista.filter((h) => h.estatus_fase && h.estatus_fase !== FASE_PENDIENTE && h.estatus_fase !== FASE_RECHAZADO).length;

    return { aprobados, total: lista.length };
}

/** Selector compacto de envíos del multipedido en el modal de auditoría. */
export default function SelectorEnviosAuditoria({
    pedido,
    onSeleccionar = null,
    disabled = false,
    className = '',
}) {
    const grupo = pedido?.multienvio;
    const hermanos = [...(grupo?.hermanos || [])].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0));
    if (hermanos.length < 2) return null;

    const { aprobados, total } = progresoAprobadosEnvios(hermanos);
    const nTotal = grupo.parcial || !grupo.n_total ? null : grupo.n_total;

    return (
        <div data-selector-envios-auditoria className={`gelia-pedidos-revision-grupo min-w-0 ${className}`.trim()}>
            <p className="text-xs theme-text-muted m-0 mb-1.5">
                {aprobados} de {total} envíos aprobados
            </p>
            <div role="tablist" aria-label="Envíos del multipedido" className="gelia-pedidos-revision-envios">
                {hermanos.map((h) => {
                    const actual = h.id === pedido.id;
                    const { Icono, texto } = estadoRevisionEnvio(h);
                    const folio = h.folio_remision || h.folio || '—';
                    const posicion = nTotal ? `Envío ${h.orden} de ${nTotal}` : `Envío ${h.orden}`;

                    return (
                        <button
                            key={h.id}
                            type="button"
                            role="tab"
                            aria-selected={actual}
                            aria-current={actual ? 'true' : undefined}
                            aria-label={`${posicion}, folio ${folio}, ${texto}`}
                            disabled={disabled || actual}
                            className="gelia-pedidos-revision-envio"
                            onClick={() => {
                                if (!actual) onSeleccionar?.(h.id);
                            }}
                        >
                            <span className="inline-flex items-center gap-1 font-bold">
                                <Icono className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                                {posicion}
                            </span>
                            <span className="theme-text-muted font-medium truncate max-w-full">
                                {folio} · {texto}
                            </span>
                        </button>
                    );
                })}
            </div>
            {grupo?.parcial && (
                <p className="text-xs theme-text-muted m-0 mt-1">Solo envíos a tu alcance</p>
            )}
        </div>
    );
}
