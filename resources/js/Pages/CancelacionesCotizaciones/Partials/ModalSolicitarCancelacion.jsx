import React from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, Ban } from 'lucide-react';

export default function ModalSolicitarCancelacion({ onClose, solicitud }) {
    const { data, setData, post, processing } = useForm({
        motivo_cancelacion: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('cancelaciones_cotizaciones.solicitar_cancelacion', solicitud.id), {
            onSuccess: () => onClose(),
        });
    };

    return createPortal(
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={onClose}>
            <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none"><X className="w-5 h-5" /></button>
                <div className="flex items-center gap-3 mb-6">
                    <Ban className="w-6 h-6 text-red-500" />
                    <h3 className="text-xl font-black italic theme-text-main uppercase m-0">Solicitar Cancelación</h3>
                </div>
                <p className="text-sm theme-text-muted mb-4">FOL-{solicitud.id} — La encargada deberá confirmar la cancelación.</p>
                <form onSubmit={submit} className="space-y-4">
                    <textarea
                        required
                        minLength={10}
                        value={data.motivo_cancelacion}
                        onChange={e => setData('motivo_cancelacion', e.target.value)}
                        placeholder="Describe el motivo (mín. 10 caracteres)..."
                        rows={4}
                        className="w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none resize-none"
                    />
                    <button type="submit" disabled={processing} className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] tracking-widest bg-red-600 hover:bg-red-700 outline-none disabled:opacity-50">
                        Enviar solicitud
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
