import React from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, MessageSquare, Tag, TrendingUp, Send } from 'lucide-react';

export default function ModalConsultaSolicitud({ onClose, solicitud }) {
    const { data, setData, post, processing } = useForm({
        consulta_tag: false,
        consulta_lista: false,
        comentario_vendedor: '',
    });

    const enviar = (e) => {
        e.preventDefault();
        if (!data.consulta_tag && !data.consulta_lista) {
            alert('Selecciona al menos TAG o Lista para consultar.');
            return;
        }
        post(route('solicitudes.consultas.store', solicitud.id), {
            onSuccess: onClose,
        });
    };

    return createPortal(
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div className="w-full max-w-lg theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-8 md:p-10 relative modal-pop" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl outline-none">
                    <X className="w-5 h-5" />
                </button>

                <div className="flex items-center gap-3 mb-6">
                    <MessageSquare className="w-8 h-8 text-amber-500" />
                    <div>
                        <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0">Consultar al Encargado_</h2>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1">FOL-{solicitud?.id} · Pago pendiente</p>
                    </div>
                </div>

                <form onSubmit={enviar} className="space-y-5">
                    <p className="text-xs font-bold theme-text-muted">Selecciona qué deseas verificar antes de confirmar el pago:</p>

                    <label className={`flex items-center gap-4 p-4 rounded-2xl border cursor-pointer transition-all ${data.consulta_tag ? 'border-amber-500 bg-amber-500/10' : 'theme-border theme-element'}`}>
                        <input type="checkbox" checked={data.consulta_tag} onChange={e => setData('consulta_tag', e.target.checked)} className="w-4 h-4 accent-amber-500" />
                        <Tag className="w-5 h-5 text-amber-500" />
                        <div>
                            <p className="text-sm font-black uppercase theme-text-main">TAG del cliente</p>
                            <p className="text-[10px] font-bold theme-text-muted">Verificar vendedora asignada al tag</p>
                        </div>
                    </label>

                    <label className={`flex items-center gap-4 p-4 rounded-2xl border cursor-pointer transition-all ${data.consulta_lista ? 'border-blue-500 bg-blue-500/10' : 'theme-border theme-element'}`}>
                        <input type="checkbox" checked={data.consulta_lista} onChange={e => setData('consulta_lista', e.target.checked)} className="w-4 h-4 accent-blue-500" />
                        <TrendingUp className="w-5 h-5 text-blue-500" />
                        <div>
                            <p className="text-sm font-black uppercase theme-text-main">Lista del cliente</p>
                            <p className="text-[10px] font-bold theme-text-muted">Verificar lista de descuento actual</p>
                        </div>
                    </label>

                    <div>
                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2 block">Comentario (opcional)</label>
                        <textarea
                            value={data.comentario_vendedor}
                            onChange={e => setData('comentario_vendedor', e.target.value)}
                            rows={3}
                            className="w-full theme-element border theme-border rounded-2xl p-4 text-sm font-bold theme-text-main outline-none focus:border-[var(--color-primario)] resize-none"
                            placeholder="Describe tu duda..."
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full py-4 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] flex items-center justify-center gap-2 transition-transform hover:scale-[1.02] disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Send className="w-4 h-4" /> Enviar Consulta
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
