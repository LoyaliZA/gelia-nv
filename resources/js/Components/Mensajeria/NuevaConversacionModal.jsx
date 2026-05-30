import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { X, MessageSquarePlus } from 'lucide-react';
import SelectorUsuarios from './SelectorUsuarios';

export default function NuevaConversacionModal({ isOpen, onClose, onCrear }) {
    const [seleccionado, setSeleccionado] = useState(null);
    const [enviando, setEnviando] = useState(false);

    if (!isOpen) return null;

    const toggleUsuario = (usuario) => {
        setSeleccionado((prev) => (prev?.id === usuario.id ? null : usuario));
    };

    const handleCrear = async () => {
        if (!seleccionado) return;
        setEnviando(true);
        try {
            await onCrear({
                tipo: 'directo',
                participante_ids: [seleccionado.id],
            });
            setSeleccionado(null);
            onClose();
        } finally {
            setEnviando(false);
        }
    };

    return createPortal(
        <div
            className="gelia-modal-overlay gelia-mensajeria-modal"
            onClick={onClose}
            role="presentation"
        >
            <div
                className="gelia-modal-shell max-w-md w-full modal-pop theme-text-main"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="nuevo-chat-titulo"
            >
                <div className="flex items-center justify-between px-5 py-4 border-b theme-border shrink-0">
                    <div className="flex items-center gap-2 min-w-0">
                        <MessageSquarePlus className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <h2 id="nuevo-chat-titulo" className="text-sm font-black uppercase italic theme-text-main truncate">
                            Nuevo Chat_
                        </h2>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-1.5 rounded-full theme-element theme-text-main hover:border-[var(--color-primario)] border theme-border transition-colors shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="gelia-modal-body p-5">
                    <SelectorUsuarios
                        seleccionados={seleccionado ? [seleccionado] : []}
                        onToggle={toggleUsuario}
                        placeholder="Buscar colaborador..."
                    />
                </div>

                <div className="gelia-modal-footer px-5 py-4 flex flex-col-reverse sm:flex-row justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2.5 text-xs font-bold rounded-xl theme-element theme-text-main border theme-border"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        onClick={handleCrear}
                        disabled={enviando || !seleccionado}
                        className="px-4 py-2.5 text-xs font-bold rounded-xl bg-[var(--color-primario)] text-white disabled:opacity-40"
                    >
                        {enviando ? 'Abriendo...' : 'Iniciar chat'}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
