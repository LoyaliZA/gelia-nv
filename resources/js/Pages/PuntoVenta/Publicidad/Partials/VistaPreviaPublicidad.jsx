import React, { useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import {
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK_COMPACT,
    THEME_BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '@/utils/geliaTheme';
import { formatearDuracionSeg } from '@/utils/estadoPublicidadPdv';
import CapaMediaRotada from '@/Pages/PuntoVenta/Pantallas/Partials/CapaMediaRotada';
import useModalPublicidad from '../useModalPublicidad';

export default function VistaPreviaPublicidad({ items = [], indiceInicial = 0, onClose }) {
    const lista = items.filter((item) => item?.url);
    const [indice, setIndice] = useState(Math.min(indiceInicial, Math.max(lista.length - 1, 0)));
    const [marco, setMarco] = useState('16:9');
    const ref = useRef(null);
    useModalPublicidad(true, onClose, ref);
    const actual = lista[indice];
    const objectFit = actual?.ajuste === 'contain' ? 'contain' : 'cover';

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div
                ref={ref}
                role="dialog"
                aria-modal="true"
                aria-labelledby="vista-previa-publicidad-titulo"
                className={`${THEME_MODAL_SHELL} max-w-3xl w-full p-4 md:p-6 space-y-4`}
                onClick={(event) => event.stopPropagation()}
            >
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="vista-previa-publicidad-titulo" className="text-lg font-black italic uppercase tracking-tighter theme-text-main m-0">
                            Vista previa
                        </h2>
                        <p className="text-sm theme-text-muted m-0">
                            {actual ? `${actual.nombre_original || 'Pieza'} · ${formatearDuracionSeg(actual.duracion_seg) || 'sin duración'}` : 'No hay piezas para mostrar.'}
                        </p>
                    </div>
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={onClose} aria-label="Cerrar vista previa">
                        <X className="h-4 w-4" />
                    </button>
                </div>
                <div className={GELIA_SEGMENT_TABS_SCROLL}>
                    <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK_COMPACT} p-1 shadow-sm`} role="group" aria-label="Marco de vista previa">
                        {['16:9', '9:16'].map((opcion) => (
                            <button
                                key={opcion}
                                type="button"
                                className="gelia-segment-btn whitespace-nowrap min-h-[44px]"
                                data-active={marco === opcion}
                                aria-pressed={marco === opcion}
                                onClick={() => setMarco(opcion)}
                            >
                                Marco {opcion}
                            </button>
                        ))}
                    </div>
                </div>
                {actual ? (
                    <div className={`overflow-hidden rounded-2xl bg-black ${marco === '9:16' ? 'aspect-[9/16] mx-auto w-full max-w-xs' : 'aspect-video'}`}>
                        <CapaMediaRotada rotacion={actual.rotacion}>
                            {actual.tipo === 'video' ? (
                                <video key={actual.id || actual.url} src={actual.url} controls className="h-full w-full" style={{ objectFit }} />
                            ) : (
                                <img src={actual.url} alt="" className="h-full w-full" style={{ objectFit }} />
                            )}
                        </CapaMediaRotada>
                    </div>
                ) : null}
                {lista.length > 1 ? (
                    <div className="flex items-center justify-between gap-3">
                        <button type="button" className={THEME_BTN_SECONDARY} disabled={indice === 0} onClick={() => setIndice((n) => n - 1)}>
                            Anterior
                        </button>
                        <span className="text-sm theme-text-muted">{indice + 1} / {lista.length}</span>
                        <button type="button" className={THEME_BTN_SECONDARY} disabled={indice >= lista.length - 1} onClick={() => setIndice((n) => n + 1)}>
                            Siguiente
                        </button>
                    </div>
                ) : null}
            </div>
        </div>,
        document.body,
    );
}
