import React, { useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, History } from 'lucide-react';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import ListaErroresPedido from './ListaErroresPedido';
import TarjetaEntradaBitacora from './TarjetaEntradaBitacora';

export default function ModalBitacoraPedido({ abierto, onClose, pedido }) {
    const [docPreview, setDocPreview] = useState(null);

    const historial = pedido?.historial || [];
    const errores = pedido?.errores || [];

    const evidencias = useMemo(() => {
        const docs = [];
        historial.forEach((h) => {
            const ruta = h.evidencia_ruta || h.evidenciaRuta;
            if (ruta) {
                docs.push({
                    id: `hist-${h.id}`,
                    url: `/storage/${ruta}`,
                    nombre_original: h.evidencia_nombre || h.evidenciaNombre || 'Evidencia',
                    tipo: 'evidencia_bitacora',
                    comentario: h.accion_etiqueta || h.accion,
                    autor: h.usuario,
                    created_at: h.created_at,
                });
            }
            const snap = h.snapshot_json || h.snapshotJson;
            (snap?.archivos || []).forEach((arch, i) => {
                if (!arch?.ruta) return;
                docs.push({
                    id: `hist-${h.id}-snap-${i}`,
                    url: `/storage/${arch.ruta}`,
                    nombre_original: arch.nombre || arch.tipo || 'Archivo',
                    tipo: arch.tipo || 'snapshot',
                    mime_type: arch.mime_type,
                    comentario: h.accion_etiqueta || h.accion,
                });
            });
        });
        return docs;
    }, [historial]);

    const abrirDoc = (doc) => {
        const indice = evidencias.findIndex((d) => d.id === doc.id);
        setDocPreview({ indice: indice >= 0 ? indice : 0 });
    };

    if (!abierto || !pedido) return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-2xl w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-2 min-w-0">
                        <History className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div className="min-w-0">
                            <h2 className="text-lg font-black italic uppercase theme-text-main m-0">Bitácora</h2>
                            <EncabezadoFolioPedido pedido={pedido} size="sm" className="mt-1" />
                        </div>
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
                <div className="gelia-modal-body p-5 md:p-6 space-y-6">
                    {errores.length > 0 && (
                        <ListaErroresPedido errores={errores} />
                    )}
                    {historial.length === 0 ? (
                        <p className="text-sm theme-text-muted font-bold uppercase m-0">
                            {errores.length > 0 ? 'Sin otros movimientos de estado_' : 'Sin movimientos registrados_'}
                        </p>
                    ) : (
                        <div className="space-y-3">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                                Movimientos de estado · expande cada tarjeta para ver captura de datos y archivos
                            </p>
                            {historial.map((h) => {
                                const evidenciaRuta = h.evidencia_ruta || h.evidenciaRuta;
                                const idxEvidencia = evidencias.findIndex((d) => d.id === `hist-${h.id}`);
                                return (
                                    <TarjetaEntradaBitacora
                                        key={h.id}
                                        entrada={h}
                                        onVerEvidencia={evidenciaRuta
                                            ? () => setDocPreview({ indice: Math.max(idxEvidencia, 0) })
                                            : null}
                                        onVerArchivoSnapshot={abrirDoc}
                                    />
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
            <ModalVistaPreviaDocumento
                abierto={Boolean(docPreview)}
                documentos={evidencias}
                indice={docPreview?.indice || 0}
                onClose={() => setDocPreview(null)}
                onChangeIndice={(i) => setDocPreview({ indice: i })}
            />
        </div>,
        document.body
    );
}
