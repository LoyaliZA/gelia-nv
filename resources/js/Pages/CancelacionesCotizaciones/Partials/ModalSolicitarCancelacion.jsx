import React from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, Ban, Send } from 'lucide-react';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_TEXTAREA,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
} from '../../../utils/geliaTheme';

export default function ModalSolicitarCancelacion({ onClose, onExito, solicitud }) {
    const { data, setData, post, processing } = useForm({
        motivo_cancelacion: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('cancelaciones_cotizaciones.solicitar_cancelacion', solicitud.id), {
            onSuccess: () => { onExito?.(); onClose(); },
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-md w-full flex flex-col text-left`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        <Ban className="w-6 h-6 text-red-500 shrink-0" />
                        <h3 className="text-lg font-black italic uppercase theme-text-main m-0 leading-tight">
                            Solicitar cancelación
                        </h3>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                    <div className="gelia-modal-body p-5 md:p-6 space-y-4">
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest m-0">
                            FOL-{solicitud.id} — La encargada deberá confirmar la cancelación
                        </p>
                        <div className="space-y-1.5">
                            <label className={THEME_LABEL}>Motivo</label>
                            <textarea
                                required
                                minLength={10}
                                value={data.motivo_cancelacion}
                                onChange={(e) => setData('motivo_cancelacion', e.target.value)}
                                placeholder="Describe el motivo (mín. 10 caracteres)…"
                                rows={4}
                                className={THEME_TEXTAREA}
                            />
                        </div>
                    </div>
                    <div className="gelia-modal-footer p-5 md:p-6">
                        <button
                            type="submit"
                            disabled={processing}
                            className={`${THEME_BTN_PRIMARY} w-full !bg-red-600 hover:!opacity-90`}
                        >
                            <Send className="w-4 h-4 shrink-0" />
                            {processing ? 'Enviando…' : 'Enviar solicitud'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
