import React from 'react';
import { X } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { formatearFechaNegocio } from './pedidosBmaStyles';

export default function ModalAuditoriaEliminacionPedido({ abierto, pedido, onClose }) {
    if (!abierto || !pedido) return null;

    const auditorias = pedido.auditorias_registro || pedido.auditoriasRegistro || [];

    return (
        <div className="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div className={`${geliaCardClass()} w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col shadow-2xl`}>
                <div className="flex items-center justify-between p-5 border-b theme-border">
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Auditoría administrativa</p>
                        <h2 className="text-lg font-black uppercase theme-text-main m-0 mt-1">
                            {pedido.folio_remision || pedido.folio || `Pedido #${pedido.id}`}
                        </h2>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-lg hover:bg-black/5" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="p-5 overflow-y-auto space-y-4">
                    {auditorias.length === 0 ? (
                        <p className="text-sm theme-text-muted m-0">Sin registros de auditoría.</p>
                    ) : (
                        auditorias.map((a) => (
                            <div key={a.id} className="p-4 rounded-xl border theme-border bg-black/[0.02]">
                                <div className="flex flex-wrap gap-x-4 gap-y-1 text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                    <span>{a.accion === 'restauracion' ? 'Restauración' : 'Eliminación'}</span>
                                    <span>{a.usuario?.name || '—'}</span>
                                    <span>{formatearFechaNegocio(a.created_at)}</span>
                                </div>
                                <p className="text-sm theme-text-main mt-2 m-0 whitespace-pre-wrap">{a.motivo}</p>
                                {a.fase_ciclo && (
                                    <p className="text-[10px] theme-text-muted mt-2 m-0 uppercase font-bold">
                                        Fase al momento: {a.fase_ciclo}
                                    </p>
                                )}
                            </div>
                        ))
                    )}
                </div>
            </div>
        </div>
    );
}
