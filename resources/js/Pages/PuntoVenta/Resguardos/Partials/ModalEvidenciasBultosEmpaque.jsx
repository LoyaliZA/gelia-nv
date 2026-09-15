import React, { useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Package, Ticket, ImageIcon } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../ControlPedidos/Partials/pedidosBmaStyles';
import LightboxFotos from '../../../Activos/Partials/LightboxFotos';

function urlsDesdeBultos(bultos = []) {
    const urls = [];
    bultos.forEach((bulto) => {
        if (bulto.foto_bulto?.url) urls.push(bulto.foto_bulto.url);
        if (bulto.foto_ticket?.url) urls.push(bulto.foto_ticket.url);
    });
    return urls;
}

export default function ModalEvidenciasBultosEmpaque({ abierto, onClose, bultos = [], folio = '' }) {
    const [lightbox, setLightbox] = useState(null);
    const urls = useMemo(() => urlsDesdeBultos(bultos), [bultos]);

    if (!abierto) return null;

    const abrirFoto = (url) => {
        const indice = urls.indexOf(url);
        if (indice >= 0) setLightbox(indice);
    };

    return createPortal(
        <>
            <div
                className={`${THEME_MODAL_OVERLAY} items-end sm:items-center py-0 sm:py-4`}
                onClick={onClose}
            >
                <div
                    className={`${THEME_MODAL_SHELL} max-w-lg w-full max-h-[90dvh] flex flex-col`}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="p-4 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div>
                            <h2 className="text-base font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                                <ImageIcon className="w-4 h-4 text-sky-600" />
                                Evidencias CEDIS
                            </h2>
                            {folio && (
                                <p className="text-[10px] font-bold theme-text-muted m-0 mt-1 uppercase tracking-widest">
                                    {folio} · {bultos.length} bulto{bultos.length === 1 ? '' : 's'}
                                </p>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="p-2 min-h-[44px] min-w-[44px] rounded-full theme-text-muted outline-none inline-flex items-center justify-center"
                            aria-label="Cerrar"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="p-4 space-y-3 overflow-y-auto flex-1 pb-[max(1rem,env(safe-area-inset-bottom))]">
                        {bultos.map((bulto) => (
                            <div key={`bulto-ev-${bulto.numero}`} className="rounded-xl border theme-border p-3 space-y-2">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                    Bulto {bulto.numero}
                                </p>
                                <div className="grid grid-cols-2 gap-2">
                                    {bulto.foto_bulto?.url ? (
                                        <button
                                            type="button"
                                            onClick={() => abrirFoto(bulto.foto_bulto.url)}
                                            className="rounded-xl overflow-hidden border theme-border aspect-[4/3] relative group outline-none"
                                        >
                                            <img src={bulto.foto_bulto.url} alt={`Bulto ${bulto.numero}`} className="w-full h-full object-cover" />
                                            <span className="absolute bottom-0 inset-x-0 bg-black/55 text-white text-[9px] font-black uppercase tracking-widest py-1.5 flex items-center justify-center gap-1">
                                                <Package className="w-3 h-3" /> Bulto
                                            </span>
                                        </button>
                                    ) : (
                                        <div className="rounded-xl border theme-border aspect-[4/3] flex items-center justify-center text-[10px] font-bold theme-text-muted">
                                            Sin foto
                                        </div>
                                    )}
                                    {bulto.foto_ticket?.url ? (
                                        <button
                                            type="button"
                                            onClick={() => abrirFoto(bulto.foto_ticket.url)}
                                            className="rounded-xl overflow-hidden border theme-border aspect-[4/3] relative group outline-none"
                                        >
                                            <img src={bulto.foto_ticket.url} alt={`Ticket bulto ${bulto.numero}`} className="w-full h-full object-cover" />
                                            <span className="absolute bottom-0 inset-x-0 bg-black/55 text-white text-[9px] font-black uppercase tracking-widest py-1.5 flex items-center justify-center gap-1">
                                                <Ticket className="w-3 h-3" /> Ticket
                                            </span>
                                        </button>
                                    ) : (
                                        <div className="rounded-xl border theme-border aspect-[4/3] flex items-center justify-center text-[10px] font-bold theme-text-muted">
                                            Sin ticket
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
            {lightbox != null && (
                <LightboxFotos
                    fotos={urls}
                    indiceInicial={lightbox}
                    onCerrar={() => setLightbox(null)}
                />
            )}
        </>,
        document.body
    );
}

export function ChipEvidenciasBultosEmpaque({ bultos = [], folio = '', className = '' }) {
    const [abierto, setAbierto] = useState(false);
    if (!bultos?.length) return null;

    const miniatura = bultos[0]?.foto_bulto?.url || bultos[0]?.foto_ticket?.url;

    return (
        <>
            <button
                type="button"
                onClick={() => setAbierto(true)}
                className={`w-full flex items-center gap-2 rounded-xl border theme-border p-2 min-h-[44px] text-left outline-none hover:border-[var(--color-primario)]/40 transition-colors ${className}`}
            >
                {miniatura ? (
                    <span className="w-10 h-10 rounded-lg overflow-hidden border theme-border shrink-0">
                        <img src={miniatura} alt="" className="w-full h-full object-cover" />
                    </span>
                ) : (
                    <span className="w-10 h-10 rounded-lg bg-sky-500/15 text-sky-700 dark:text-sky-300 shrink-0 inline-flex items-center justify-center">
                        <ImageIcon className="w-4 h-4" aria-hidden />
                    </span>
                )}
                <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">
                    Ver evidencias ({bultos.length} bulto{bultos.length === 1 ? '' : 's'})
                </span>
            </button>
            <ModalEvidenciasBultosEmpaque
                abierto={abierto}
                onClose={() => setAbierto(false)}
                bultos={bultos}
                folio={folio}
            />
        </>
    );
}
