import React from 'react';
import { Check, Lock, AlertTriangle, Circle } from 'lucide-react';
import { ETAPAS_LABEL } from './constantes';

/**
 * Stepper de etapas. Solo navega etapas con editable=true.
 * No depende solo de color: icono + texto + aria.
 */
export default function ProgresoPedido({
    progreso = null,
    etapaSeleccionada = null,
    onSeleccionar = null,
}) {
    const etapas = progreso?.etapas || [];
    if (etapas.length === 0) return null;

    const actual = etapaSeleccionada || progreso?.etapa_actual;

    return (
        <nav aria-label="Progreso del pedido" className="w-full">
            <ol className="flex flex-col sm:flex-row sm:flex-wrap gap-2 sm:gap-1 list-none m-0 p-0">
                {etapas.map((etapa, idx) => {
                    const label = ETAPAS_LABEL[etapa.codigo] || etapa.codigo;
                    const seleccionada = etapa.codigo === actual;
                    const completa = etapa.estado === 'completa';
                    const bloqueada = etapa.estado === 'bloqueada';
                    const correccion = etapa.estado === 'requiere_correccion';
                    const Icon = correccion
                        ? AlertTriangle
                        : completa
                            ? Check
                            : bloqueada
                                ? Lock
                                : Circle;
                    const puedeClic = Boolean(etapa.editable) && typeof onSeleccionar === 'function';

                    return (
                        <li key={etapa.codigo} className="sm:flex-1 min-w-0">
                            <button
                                type="button"
                                disabled={!puedeClic}
                                onClick={() => puedeClic && onSeleccionar(etapa.codigo)}
                                title={etapa.motivo_bloqueo || label}
                                aria-current={seleccionada ? 'step' : undefined}
                                aria-disabled={bloqueada || undefined}
                                className={`w-full text-left px-3 py-2.5 rounded-xl border theme-border outline-none min-h-[44px]
                                    ${seleccionada ? 'ring-2 ring-[var(--color-primario)] theme-element' : 'theme-surface'}
                                    ${!puedeClic ? 'opacity-70 cursor-not-allowed' : 'hover:theme-element cursor-pointer'}
                                `}
                            >
                                <span className="flex items-center gap-2">
                                    <Icon className="w-3.5 h-3.5 shrink-0" aria-hidden />
                                    <span className="min-w-0">
                                        <span className="block text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                            {idx + 1}. {label}
                                        </span>
                                        <span className="block text-[11px] font-bold theme-text-main truncate">
                                            {completa ? 'Completa' : correccion ? 'Requiere corrección' : bloqueada ? (etapa.motivo_bloqueo || 'Bloqueada') : 'En curso'}
                                        </span>
                                    </span>
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
            {progreso?.accion_recomendada && (
                <p className="text-xs font-bold theme-text-main m-0 mt-3" role="status">
                    {progreso.accion_recomendada}
                </p>
            )}
        </nav>
    );
}
