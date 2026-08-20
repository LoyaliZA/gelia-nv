import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle, X } from 'lucide-react';
import { THEME_TEXTAREA } from '../../../utils/geliaTheme';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_SECONDARY,
    deferModalAction,
} from './pedidosBmaStyles';

const MIN_MOTIVO = 10;
const MAX_MOTIVO = 255;

export default function ModalEliminarRegistroPedido({ abierto, pedido, onClose, onConfirm }) {
    const [motivo, setMotivo] = useState('');
    const [error, setError] = useState('');
    const [enviando, setEnviando] = useState(false);

    useEffect(() => {
        if (abierto) {
            setMotivo('');
            setError('');
            setEnviando(false);
        }
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const folio = pedido.folio_remision || pedido.folio || `#${pedido.id}`;
    const fase = pedido.estatus?.fase_ciclo || '—';
    const tienePagos = Boolean(pedido.pagos_exhibicion?.length || pedido.pagosExhibicion?.length);

    const enviar = () => {
        const texto = motivo.trim();
        if (texto.length < MIN_MOTIVO) {
            setError(`El motivo debe tener al menos ${MIN_MOTIVO} caracteres.`);
            return;
        }
        if (texto.length > MAX_MOTIVO) {
            setError(`El motivo no puede superar ${MAX_MOTIVO} caracteres.`);
            return;
        }
        setEnviando(true);
        onConfirm(texto, () => setEnviando(false));
    };

    const cerrar = (e) => {
        e?.stopPropagation?.();
        if (enviando) return;
        deferModalAction(onClose);
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-center py-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 20)' }}
            data-gelia-modal="1"
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 border-b theme-border flex justify-between items-start gap-3">
                    <div>
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                            <AlertTriangle className="w-5 h-5 text-red-500 shrink-0" />
                            Eliminar registro
                        </h2>
                        <p className="text-xs theme-text-muted font-bold mt-1 m-0">
                            {folio} · fase {fase}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={cerrar}
                        disabled={enviando}
                        className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="p-5 space-y-4">
                    <p className="text-sm theme-text-muted m-0 leading-relaxed">
                        El pedido quedará en la papelera administrativa con respaldo en auditoría. No sustituye una cancelación contable.
                    </p>
                    {tienePagos && (
                        <p className="text-xs font-bold text-orange-500 m-0 leading-relaxed">
                            Este pedido tiene pagos registrados. La eliminación lo oculta del listado; no resuelve el cobro.
                        </p>
                    )}
                    <div>
                        <label htmlFor="motivo-eliminar-registro" className={THEME_LABEL}>
                            Motivo (mínimo {MIN_MOTIVO} caracteres)
                        </label>
                        <textarea
                            id="motivo-eliminar-registro"
                            value={motivo}
                            onChange={(e) => { setMotivo(e.target.value); setError(''); }}
                            rows={4}
                            maxLength={MAX_MOTIVO}
                            disabled={enviando}
                            className={`${THEME_TEXTAREA} w-full min-h-[96px]`}
                            placeholder="Describe por qué se elimina este registro..."
                        />
                        <p className="text-[10px] font-bold theme-text-muted mt-1 m-0 text-right">
                            {motivo.trim().length}/{MAX_MOTIVO}
                        </p>
                        {error && <p className="text-xs text-red-500 font-bold mt-1 m-0">{error}</p>}
                    </div>
                    <div className="flex flex-col sm:flex-row gap-3">
                        <button
                            type="button"
                            onClick={cerrar}
                            disabled={enviando}
                            className={`${BTN_SECONDARY} flex-1 py-3 rounded-xl border theme-border theme-element`}
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onClick={enviar}
                            disabled={enviando}
                            className="theme-btn-danger flex-1 py-3 rounded-xl justify-center"
                        >
                            {enviando ? 'Eliminando…' : 'Eliminar registro'}
                        </button>
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}
