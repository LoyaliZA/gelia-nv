import React, { useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { formatearFechaNegocio } from '../pedidosBmaStyles';
import DetalleSnapshotBitacora from '../DetalleSnapshotBitacora';

export default function HistorialPedidoAcordeon({ historial = [] }) {
    const [abierto, setAbierto] = useState(false);
    const [expandidoId, setExpandidoId] = useState(null);
    const items = Array.isArray(historial) ? historial : [];

    return (
        <div className="rounded-xl border theme-border overflow-hidden">
            <button
                type="button"
                onClick={() => setAbierto((v) => !v)}
                className="w-full flex items-center justify-between gap-2 px-4 py-3 text-left outline-none theme-element min-h-[44px]"
                aria-expanded={abierto}
            >
                <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                    Historial ({items.length})
                </span>
                {abierto ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
            </button>
            {abierto && (
                <ul className="m-0 p-3 space-y-2 list-none border-t theme-border max-h-72 overflow-y-auto">
                    {items.length === 0 && (
                        <li className="text-xs theme-text-muted font-bold">Sin movimientos registrados.</li>
                    )}
                    {items.map((h) => {
                        const id = h.id || `${h.created_at}-${h.accion}`;
                        const expandido = expandidoId === id;
                        const snap = h.snapshot_json || h.snapshotJson;
                        return (
                            <li key={id} className="rounded-lg border theme-border theme-element">
                                <button
                                    type="button"
                                    onClick={() => setExpandidoId(expandido ? null : id)}
                                    className="w-full text-left px-3 py-2 outline-none"
                                >
                                    <p className="m-0 font-bold theme-text-main text-xs">
                                        {h.accion_etiqueta || h.accion_label || h.accion || 'Movimiento'}
                                        {h.usuario?.name ? ` · ${h.usuario.name}` : ''}
                                    </p>
                                    <p className="m-0 theme-text-muted font-bold text-[10px]">
                                        {h.created_at ? formatearFechaNegocio(h.created_at) : '—'}
                                        {snap ? ' · Ver detalle' : ''}
                                    </p>
                                </button>
                                {expandido && (
                                    <div className="px-3 pb-3">
                                        <DetalleSnapshotBitacora snapshot={snap} />
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}
