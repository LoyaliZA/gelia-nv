import React, { useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import { THEME_BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';
import { formatearDuracionSeg } from '@/utils/estadoPublicidadPdv';
import useModalPublicidad from '../useModalPublicidad';

export default function VistaPreviaPublicidad({ items = [], indiceInicial = 0, onClose }) {
    const lista = items.filter((item) => item?.url);
    const [indice, setIndice] = useState(Math.min(indiceInicial, Math.max(lista.length - 1, 0)));
    const ref = useRef(null);
    useModalPublicidad(true, onClose, ref);
    const actual = lista[indice];

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
                {actual ? (
                    <div className="overflow-hidden rounded-2xl bg-black aspect-video">
                        {actual.tipo === 'video' ? (
                            <video key={actual.id || actual.url} src={actual.url} controls className={`h-full w-full ${actual.ajuste === 'contain' ? 'object-contain' : 'object-cover'}`} />
                        ) : (
                            <img src={actual.url} alt="" className={`h-full w-full ${actual.ajuste === 'contain' ? 'object-contain' : 'object-cover'}`} />
                        )}
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
