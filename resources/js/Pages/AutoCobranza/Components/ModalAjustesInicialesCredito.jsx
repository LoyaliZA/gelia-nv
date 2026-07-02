import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Calendar, X, Check, AlertCircle } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_INPUT } from '../../../utils/geliaTheme';
import { formatoMoneda } from '../../../utils/formatoMoneda';

export default function ModalAjustesInicialesCredito({
    isOpen,
    creditos = [],
    onClose,
    onConfirm,
    processing = false,
}) {
    const [fechas, setFechas] = useState({});

    useEffect(() => {
        if (isOpen && creditos.length > 0) {
            const inicial = {};
            creditos.forEach((c) => {
                inicial[c.clave] = c.fecha_inicio_sugerida;
            });
            setFechas(inicial);
        }
    }, [isOpen, creditos]);

    if (!isOpen) {
        return null;
    }

    const handleFechaChange = (clave, valor) => {
        setFechas((prev) => ({ ...prev, [clave]: valor }));
    };

    const handleConfirm = () => {
        const ajustes = creditos.map((c) => ({
            clave: c.clave,
            fecha_inicio_credito: fechas[c.clave] || c.fecha_inicio_sugerida,
        }));
        onConfirm(ajustes);
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[9999] p-4`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} w-full max-w-3xl max-h-[90vh] flex flex-col`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="gelia-modal-header px-6 py-4 flex justify-between items-center border-b theme-border">
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl bg-amber-500/10">
                            <Calendar className="w-5 h-5 text-amber-500" />
                        </div>
                        <div>
                            <h2 className="text-lg font-black uppercase italic tracking-tighter theme-text-main m-0">
                                Ajustes Iniciales de Crédito
                            </h2>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest m-0 mt-1">
                                {creditos.length} crédito(s) nuevo(s) detectado(s)
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="px-6 py-4 overflow-y-auto flex-1 space-y-4">
                    <div className="flex items-start gap-2 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20">
                        <AlertCircle className="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
                        <p className="text-xs theme-text-muted m-0">
                            Revise y ajuste las fechas de inicio de crédito antes de procesar la carga.
                            Solo se muestran créditos nuevos; los créditos ya existentes no aparecen aquí.
                        </p>
                    </div>

                    <div className="overflow-x-auto rounded-xl border theme-border">
                        <table className="w-full text-left text-xs">
                            <thead>
                                <tr className="theme-element border-b theme-border">
                                    <th className="px-4 py-3 font-black uppercase tracking-widest text-[10px] theme-text-muted">Cliente</th>
                                    <th className="px-4 py-3 font-black uppercase tracking-widest text-[10px] theme-text-muted text-right">Monto</th>
                                    <th className="px-4 py-3 font-black uppercase tracking-widest text-[10px] theme-text-muted">Fecha inicio crédito</th>
                                </tr>
                            </thead>
                            <tbody>
                                {creditos.map((credito) => (
                                    <tr key={credito.clave} className="border-b theme-border last:border-b-0">
                                        <td className="px-4 py-3">
                                            <div className="font-bold theme-text-main">{credito.numero_cliente}</div>
                                            <div className="theme-text-muted">{credito.nombre}</div>
                                            {credito.es_cliente_nuevo && (
                                                <span className="inline-block mt-1 px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-emerald-500/10 text-emerald-600">
                                                    Cliente nuevo
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold theme-text-main">
                                            {formatoMoneda(credito.monto)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <input
                                                type="date"
                                                value={fechas[credito.clave] || credito.fecha_inicio_sugerida}
                                                onChange={(e) => handleFechaChange(credito.clave, e.target.value)}
                                                className={`${THEME_INPUT} w-full text-xs font-bold`}
                                                required
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="gelia-modal-footer px-6 py-4 flex justify-end gap-3 border-t theme-border">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={processing}
                        className="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest theme-text-main theme-element border theme-border hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        onClick={handleConfirm}
                        disabled={processing}
                        className="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-white flex items-center gap-2 shadow-lg disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Check className="w-4 h-4" />
                        {processing ? 'Procesando...' : 'Confirmar y cargar'}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
