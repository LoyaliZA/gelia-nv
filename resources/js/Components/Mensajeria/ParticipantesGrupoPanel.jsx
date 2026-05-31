import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { X, Users, Shield } from 'lucide-react';
import AvatarUsuario from './AvatarUsuario';
import {
    colorRemitenteGrupo,
    nombreRemitenteGrupo,
    etiquetaRolParticipante,
    ordenarParticipantes,
} from '@/utils/mensajeriaGrupo';

export default function ParticipantesGrupoPanel({
    isOpen,
    onClose,
    nombreGrupo = 'Grupo',
    participantes = [],
    usuarioActualId = null,
}) {
    useEffect(() => {
        if (!isOpen) return undefined;
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = prev;
        };
    }, [isOpen]);

    if (!isOpen) return null;

    const lista = ordenarParticipantes(participantes);

    return createPortal(
        <div
            className="gelia-modal-overlay gelia-mensajeria-modal z-[10000]"
            onClick={onClose}
            role="presentation"
        >
            <div
                className="gelia-modal-shell max-w-md w-full h-full max-h-dvh sm:max-h-[85vh] sm:h-auto flex flex-col modal-pop theme-text-main sm:rounded-[2rem]"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="participantes-grupo-titulo"
            >
                <div className="gelia-modal-body p-0 flex flex-col min-h-0">
                    <div className="flex items-center gap-3 p-5 border-b theme-border shrink-0">
                        <div
                            className="p-2.5 rounded-xl shrink-0"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}
                        >
                            <Users className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                        </div>
                        <div className="min-w-0 flex-1">
                            <h2
                                id="participantes-grupo-titulo"
                                className="text-sm font-black uppercase italic theme-text-main m-0 truncate"
                            >
                                Participantes_
                            </h2>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0 truncate">
                                {nombreGrupo} · {lista.length} integrante{lista.length !== 1 ? 's' : ''}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="p-2 rounded-full theme-element border theme-border theme-text-muted hover:theme-text-main outline-none shrink-0"
                            aria-label="Cerrar"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <ul className="flex-1 overflow-y-auto p-3 sm:p-4 space-y-2 m-0 list-none">
                        {lista.map((p) => {
                            const esYo = p.id === usuarioActualId;
                            const color = colorRemitenteGrupo(p.id);
                            const nombre = nombreRemitenteGrupo(p);

                            return (
                                <li
                                    key={p.id}
                                    className="flex items-center gap-3 p-3 rounded-2xl theme-element border theme-border"
                                >
                                    <AvatarUsuario
                                        foto={p.foto_perfil}
                                        nombre={nombre}
                                        className="w-11 h-11"
                                        iconClassName="w-5 h-5"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p
                                            className="text-sm font-bold theme-text-main m-0 truncate"
                                            style={{ color: esYo ? 'var(--color-primario)' : color }}
                                        >
                                            {nombre}
                                            {esYo && (
                                                <span className="text-[10px] font-black uppercase tracking-widest ms-1.5 opacity-80">
                                                    (Tú)
                                                </span>
                                            )}
                                        </p>
                                        <div className="flex items-center gap-2 mt-1 flex-wrap">
                                            {p.rol === 'admin' && (
                                                <span className="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full theme-surface border theme-border">
                                                    <Shield className="w-3 h-3" style={{ color: 'var(--color-primario)' }} />
                                                    {etiquetaRolParticipante(p.rol)}
                                                </span>
                                            )}
                                            {p.username && (
                                                <span className="text-[10px] font-bold theme-text-muted truncate">
                                                    @{p.username}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            </div>
        </div>,
        document.body
    );
}
