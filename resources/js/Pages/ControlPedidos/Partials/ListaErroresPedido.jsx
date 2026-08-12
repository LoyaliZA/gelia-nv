import React from 'react';
import { AlertTriangle } from 'lucide-react';
import { formatearFechaHoraAuditoria } from './pedidosBmaStyles';
import { CAMPOS_ERROR_DATOS } from './ModalReportarErrorDatos';

const etiquetaCampo = (id) => CAMPOS_ERROR_DATOS.find((c) => c.id === id)?.label || id;

/**
 * Lista compacta de bitácora de errores del pedido.
 */
export default function ListaErroresPedido({ errores = [] }) {
    if (!Array.isArray(errores) || errores.length === 0) return null;

    return (
        <div className="rounded-xl border border-orange-500/30 bg-orange-500/5 p-3 space-y-2">
            <p className="text-[9px] font-black uppercase tracking-widest text-orange-700 m-0 flex items-center gap-1">
                <AlertTriangle className="w-3 h-3" /> Historial de errores
            </p>
            {errores.map((err) => {
                const campos = Array.isArray(err.campos) ? err.campos.map(etiquetaCampo).join(', ') : '—';
                const abierto = err.estatus === 'abierto';
                return (
                    <div key={err.id} className="text-[11px] theme-text-main space-y-0.5 border-t theme-border pt-2 first:border-0 first:pt-0">
                        <p className="m-0 font-bold">
                            <span className={abierto ? 'text-orange-600' : 'text-emerald-600'}>
                                {abierto ? 'Abierto' : 'Corregido'}
                            </span>
                            {' · '}
                            {err.responsable_dueno}
                            {' · '}
                            {campos}
                        </p>
                        {err.descripcion && (
                            <p className="m-0 theme-text-muted font-bold">{err.descripcion}</p>
                        )}
                        <p className="m-0 text-[10px] theme-text-muted font-mono">
                            Reportó {(err.reportado_por?.name || err.reportadoPor?.name) || '—'}
                            {err.reportado_at ? ` · ${formatearFechaHoraAuditoria(err.reportado_at)}` : ''}
                        </p>
                        {!abierto && (
                            <p className="m-0 text-[10px] theme-text-muted">
                                Corrección: {err.correccion_realizada || '—'}
                                {err.corregido_at ? ` · ${formatearFechaHoraAuditoria(err.corregido_at)}` : ''}
                            </p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
