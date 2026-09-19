import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { Fingerprint, Loader2, X } from 'lucide-react';
import WebAuthnService from '../../Services/WebAuthnService';

export default function RegistrarPasskeyPrompt({ open, onClose, onRegistered }) {
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');

    if (!open || typeof document === 'undefined') {
        return null;
    }

    const registrar = async () => {
        setProcesando(true);
        setError('');
        try {
            await WebAuthnService.registrarPasskey(`Huella en ${WebAuthnService.nicknameEquipo()}`);
            onRegistered?.();
            onClose();
        } catch (e) {
            setError(WebAuthnService.mensajeErrorPasskey(e, 'No se pudo registrar la passkey.'));
        } finally {
            setProcesando(false);
        }
    };

    return createPortal(
        <div
            className="gelia-modal-overlay !items-center overflow-y-auto"
            onClick={() => !procesando && onClose()}
            role="presentation"
        >
            <div
                className="gelia-modal-shell max-w-md p-6 md:p-8 modal-pop my-auto space-y-5"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-labelledby="registrar-passkey-title"
                aria-modal="true"
            >
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div
                            className="p-3 rounded-2xl"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}
                        >
                            <Fingerprint className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                        </div>
                        <div>
                            <h2
                                id="registrar-passkey-title"
                                className="text-lg font-black italic theme-text-main uppercase tracking-tighter m-0"
                            >
                                Registrar huella_
                            </h2>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">
                                Acceso rápido en este equipo
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={procesando}
                        className="theme-text-muted hover:theme-text-main transition-colors outline-none disabled:opacity-50"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <p className="text-sm font-bold theme-text-main m-0 leading-relaxed">
                    ¿Quieres registrar tu huella en este equipo? La próxima vez podrás entrar sin escribir contraseña.
                </p>

                {error && (
                    <p className="text-sm font-bold text-red-500 m-0" role="alert">
                        {error}
                    </p>
                )}

                <div className="flex flex-col sm:flex-row gap-3 pt-1">
                    <button
                        type="button"
                        onClick={registrar}
                        disabled={procesando}
                        className="flex-1 py-3.5 px-5 rounded-2xl text-[10px] font-black uppercase tracking-widest theme-btn-primary disabled:opacity-50 outline-none flex items-center justify-center gap-2"
                    >
                        {procesando ? <Loader2 className="w-4 h-4 animate-spin" /> : <Fingerprint className="w-4 h-4" />}
                        {procesando ? 'Esperando huella…' : 'Registrar huella'}
                    </button>
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={procesando}
                        className="flex-1 py-3.5 px-5 rounded-2xl text-[10px] font-black uppercase tracking-widest theme-text-muted border theme-border hover:theme-text-main outline-none disabled:opacity-50"
                    >
                        Ahora no
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
