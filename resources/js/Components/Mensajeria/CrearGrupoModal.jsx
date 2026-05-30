import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Users } from 'lucide-react';
import SelectorUsuarios from './SelectorUsuarios';

export default function CrearGrupoModal({ isOpen, onClose, onCrear }) {
    const [nombre, setNombre] = useState('');
    const [seleccionados, setSeleccionados] = useState([]);
    const [enviando, setEnviando] = useState(false);

    if (!isOpen) return null;

    const toggleUsuario = (usuario) => {
        setSeleccionados((prev) => {
            const existe = prev.some((u) => u.id === usuario.id);
            if (existe) return prev.filter((u) => u.id !== usuario.id);
            return [...prev, usuario];
        });
    };

    const handleCrear = async () => {
        if (!nombre.trim() || seleccionados.length === 0) return;
        setEnviando(true);
        try {
            await onCrear({
                tipo: 'grupo',
                nombre: nombre.trim(),
                participante_ids: seleccionados.map((u) => u.id),
            });
            setNombre('');
            setSeleccionados([]);
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
                aria-labelledby="nuevo-grupo-titulo"
            >
                <div className="flex items-center justify-between px-5 py-4 border-b theme-border shrink-0">
                    <div className="flex items-center gap-2 min-w-0">
                        <Users className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <h2 id="nuevo-grupo-titulo" className="text-sm font-black uppercase italic theme-text-main truncate">
                            Nuevo Grupo_
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

                <div className="gelia-modal-body p-5 space-y-4">
                    <input
                        type="text"
                        value={nombre}
                        onChange={(e) => setNombre(e.target.value)}
                        placeholder="Nombre del grupo"
                        className="w-full px-4 py-2.5 rounded-xl text-sm theme-element theme-text-main border theme-border outline-none focus:border-[var(--color-primario)] placeholder:theme-text-muted"
                    />

                    <SelectorUsuarios
                        seleccionados={seleccionados}
                        onToggle={toggleUsuario}
                        multiple
                        placeholder="Agregar participantes..."
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
                        disabled={enviando || !nombre.trim() || seleccionados.length === 0}
                        className="px-4 py-2.5 text-xs font-bold rounded-xl bg-[var(--color-primario)] text-white disabled:opacity-40"
                    >
                        {enviando ? 'Creando...' : 'Crear grupo'}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
