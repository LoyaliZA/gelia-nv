import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ArrowLeft, FolderOpen, Image as ImageIcon, Loader2, Search, Shield } from 'lucide-react';
import AvatarUsuario from './AvatarUsuario';
import BotonIconoMensajeria from './BotonIconoMensajeria';
import EstadoPresenciaTexto from './EstadoPresenciaTexto';
import VisorImagenMensaje from './VisorImagenMensaje';
import MensajeriaService from '@/Services/MensajeriaService';
import {
    colorRemitenteGrupo,
    nombreRemitenteGrupo,
    ordenarParticipantes,
} from '@/utils/mensajeriaGrupo';

function textoIntegrantes(cantidad) {
    return cantidad === 1 ? '1 integrante' : `${cantidad} integrantes`;
}

function TiraMultimedia({ conversacionId, onAbrirArchivos }) {
    const [items, setItems] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const [visor, setVisor] = useState(null);

    useEffect(() => {
        let cancelado = false;
        setCargando(true);
        setError(null);
        MensajeriaService.listarMedios(conversacionId, { categoria: 'imagenes' })
            .then((data) => {
                if (!cancelado) setItems(data.items || []);
            })
            .catch(() => {
                if (!cancelado) {
                    setError('No se pudo cargar el contenido.');
                    setItems([]);
                }
            })
            .finally(() => {
                if (!cancelado) setCargando(false);
            });
        return () => { cancelado = true; };
    }, [conversacionId]);

    return (
        <div>
            {cargando && (
                <div className="flex justify-center py-8">
                    <Loader2 className="w-5 h-5 animate-spin opacity-50" />
                </div>
            )}
            {!cargando && error && (
                <p className="text-sm theme-text-muted text-center px-4 py-6 m-0">{error}</p>
            )}
            {!cargando && !error && items.length === 0 && (
                <p className="text-sm theme-text-muted text-center px-4 py-6 m-0">No hay imágenes en este chat.</p>
            )}
            {!cargando && !error && items.length > 0 && (
                <div className="gelia-mensajeria-tira">
                    {items.map((item) => {
                        const url = item.adjunto?.thumbnail_url || item.adjunto?.url;
                        return (
                            <button
                                key={item.id}
                                type="button"
                                className="gelia-mensajeria-tira__item"
                                onClick={() => item.adjunto && setVisor(item.adjunto)}
                                aria-label={item.adjunto?.nombre_original || 'Imagen'}
                            >
                                {url ? (
                                    <img src={url} alt="" />
                                ) : (
                                    <ImageIcon className="w-6 h-6 theme-text-muted" />
                                )}
                            </button>
                        );
                    })}
                </div>
            )}
            <button type="button" className="gelia-mensajeria-fila-perfil" onClick={onAbrirArchivos}>
                <FolderOpen className="w-5 h-5 shrink-0 theme-text-muted" />
                <span className="flex-1 min-w-0 truncate">Archivos, enlaces y docs.</span>
            </button>
            {visor && (
                <VisorImagenMensaje adjunto={visor} onCerrar={() => setVisor(null)} />
            )}
        </div>
    );
}

export default function ParticipantesGrupoPanel({
    isOpen,
    onClose,
    conversacion,
    usuarioActualId = null,
    onBuscar,
    onAbrirArchivos,
}) {
    const [pestana, setPestana] = useState('integrantes');
    const cerrarRef = useRef(onClose);
    cerrarRef.current = onClose;

    useEffect(() => {
        if (!isOpen) return undefined;
        setPestana('integrantes');
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        const tecla = (evento) => {
            if (evento.key === 'Escape') cerrarRef.current?.();
        };
        window.addEventListener('keydown', tecla);
        return () => {
            document.body.style.overflow = prev;
            window.removeEventListener('keydown', tecla);
        };
    }, [isOpen]);

    if (!isOpen || !conversacion) return null;

    const esGrupo = conversacion.tipo === 'grupo';
    const participantes = ordenarParticipantes(conversacion.participantes || []);
    const tituloId = 'perfil-conversacion-titulo';

    return createPortal(
        <div
            className="gelia-modal-overlay gelia-mensajeria-modal z-[10000]"
            onClick={onClose}
            role="presentation"
        >
            <div
                className="gelia-modal-shell gelia-mensajeria-perfil max-w-md w-full h-full max-h-dvh sm:max-h-[85vh] sm:h-auto flex flex-col modal-pop theme-text-main sm:rounded-[2rem]"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby={tituloId}
            >
                <div className="gelia-modal-body p-0 flex flex-col min-h-0">
                    <div className="flex items-center px-1 pt-1 shrink-0">
                        <BotonIconoMensajeria
                            icon={ArrowLeft}
                            label="Cerrar perfil"
                            onClick={onClose}
                            plano
                        />
                    </div>

                    <div className="flex-1 overflow-y-auto min-h-0">
                        <div className="flex flex-col items-center px-6 pb-5 text-center">
                            <AvatarUsuario
                                foto={conversacion.foto}
                                nombre={conversacion.nombre}
                                esGrupo={esGrupo}
                                enLinea={!esGrupo && conversacion.presencia_otro?.en_linea === true}
                                className="w-28 h-28"
                                iconClassName="w-12 h-12"
                            />
                            <h2 id={tituloId} className="text-xl font-semibold theme-text-main m-0 mt-4 truncate max-w-full">
                                {conversacion.nombre}
                            </h2>
                            {esGrupo ? (
                                <p className="text-sm theme-text-muted m-0 mt-1">
                                    Grupo · {textoIntegrantes(participantes.length)}
                                </p>
                            ) : (
                                <EstadoPresenciaTexto presencia={conversacion.presencia_otro} className="mt-1" />
                            )}
                        </div>

                        {!esGrupo && (
                            <div className="border-t theme-border">
                                <button type="button" className="gelia-mensajeria-fila-perfil" onClick={onBuscar}>
                                    <Search className="w-5 h-5 shrink-0 theme-text-muted" />
                                    <span>Buscar</span>
                                </button>
                                <button type="button" className="gelia-mensajeria-fila-perfil" onClick={onAbrirArchivos}>
                                    <FolderOpen className="w-5 h-5 shrink-0 theme-text-muted" />
                                    <span>Archivos, enlaces y docs.</span>
                                </button>
                            </div>
                        )}

                        {esGrupo && (
                            <>
                                <div className="gelia-mensajeria-pestanas" role="tablist">
                                    <button
                                        type="button"
                                        role="tab"
                                        aria-selected={pestana === 'integrantes'}
                                        onClick={() => setPestana('integrantes')}
                                    >
                                        Integrantes
                                    </button>
                                    <button
                                        type="button"
                                        role="tab"
                                        aria-selected={pestana === 'multimedia'}
                                        onClick={() => setPestana('multimedia')}
                                    >
                                        Contenido multimedia
                                    </button>
                                </div>

                                {pestana === 'integrantes' && (
                                    <ul className="m-0 p-0 list-none">
                                        <li className="px-4 pt-3 pb-1 text-xs theme-text-muted">
                                            {textoIntegrantes(participantes.length)}
                                        </li>
                                        {participantes.map((p) => {
                                            const esYo = p.id === usuarioActualId;
                                            const nombre = nombreRemitenteGrupo(p);
                                            return (
                                                <li key={p.id} className="gelia-mensajeria-integrante">
                                                    <AvatarUsuario
                                                        foto={p.foto_perfil}
                                                        nombre={nombre}
                                                        className="w-11 h-11"
                                                        iconClassName="w-5 h-5"
                                                    />
                                                    <div className="min-w-0 flex-1">
                                                        <p
                                                            className="text-sm font-semibold theme-text-main m-0 truncate"
                                                            style={{ color: esYo ? 'var(--color-primario)' : colorRemitenteGrupo(p.id) }}
                                                        >
                                                            {nombre}
                                                            {esYo && <span className="theme-text-muted font-normal"> (Tú)</span>}
                                                        </p>
                                                        {(p.rol === 'admin' || p.username) && (
                                                            <p className="text-xs theme-text-muted m-0 mt-0.5 truncate">
                                                                {p.rol === 'admin' && (
                                                                    <span className="inline-flex items-center gap-1">
                                                                        <Shield className="w-3 h-3" aria-hidden />
                                                                        Admin.
                                                                    </span>
                                                                )}
                                                                {p.rol === 'admin' && p.username ? ' · ' : ''}
                                                                {p.username ? `@${p.username}` : ''}
                                                            </p>
                                                        )}
                                                    </div>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}

                                {pestana === 'multimedia' && (
                                    <TiraMultimedia
                                        conversacionId={conversacion.id}
                                        onAbrirArchivos={onAbrirArchivos}
                                    />
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}
