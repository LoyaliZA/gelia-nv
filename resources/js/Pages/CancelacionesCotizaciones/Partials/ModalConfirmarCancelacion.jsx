import React from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, XCircle } from 'lucide-react';

export default function ModalConfirmarCancelacion({ onClose, solicitud }) {
    const { put, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        put(route('cancelaciones_cotizaciones.cancelar', solicitud.id), {
            onSuccess: () => onClose(),
        });
    };

    return createPortal(
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={onClose}>
            <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none"><X className="w-5 h-5" /></button>
                <div className="flex items-center gap-3 mb-4">
                    <XCircle className="w-6 h-6 text-red-500" />
                    <h3 className="text-xl font-black italic theme-text-main uppercase m-0">Confirmar Cancelación</h3>
                </div>
                <p className="text-sm theme-text-muted mb-2">FOL-{solicitud.id}</p>
                {solicitud.motivo_cancelacion && (
                    <p className="text-xs font-bold theme-text-main italic mb-4 p-3 rounded-xl theme-element border theme-border">
                        {solicitud.motivo_cancelacion}
                    </p>
                )}
                <form onSubmit={submit} className="flex flex-col gap-3">
                    <button type="submit" disabled={processing} className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] tracking-widest bg-red-600 hover:bg-red-700 outline-none disabled:opacity-50">
                        Confirmar cancelación
                    </button>
                    <button type="button" onClick={onClose} className="w-full py-3 rounded-xl font-black uppercase text-[10px] tracking-widest theme-element border theme-border theme-text-muted outline-none">
                        Volver
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
