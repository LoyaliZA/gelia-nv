import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, AlertTriangle } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../../utils/geliaTheme';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';

export default function ModalReportarIncidencia({ abierto, onClose, pedido }) {
    const [detalle, setDetalle] = useState('');
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (abierto) {
            setDetalle('');
            setError('');
            setProcesando(false);
        }
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const enviar = (e) => {
        e.preventDefault();
        const texto = detalle.trim();
        if (texto.length < 5) {
            setError('El detalle debe tener al menos 5 caracteres.');
            return;
        }
        setProcesando(true);
        setError('');
        router.post(route('control_pedidos.cedis.reportar_incidencia', pedido.id), { detalle: texto }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errors) => setError(errors.detalle || 'No se pudo reportar la incidencia.'),
            onFinish: () => setProcesando(false),
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center py-4`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 border-b theme-border flex justify-between items-start gap-3">
                    <div>
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                            <AlertTriangle className="w-5 h-5 text-orange-500" />
                            Reportar Error
                        </h2>
                        <EncabezadoFolioPedido pedido={pedido} size="sm" className="mt-1" />
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <form onSubmit={enviar} className="p-5 space-y-4">
                    <div>
                        <label htmlFor="detalle-incidencia" className={`${THEME_LABEL} ml-1`}>
                            Detalle encontrado <span className="text-red-500">*</span>
                        </label>
                        <textarea
                            id="detalle-incidencia"
                            value={detalle}
                            onChange={(e) => setDetalle(e.target.value)}
                            rows={4}
                            required
                            minLength={5}
                            placeholder="Ej. producto faltante, dañado, inventario incorrecto..."
                            className={`${THEME_INPUT} w-full mt-1.5 py-3 text-sm font-bold resize-y min-h-[100px]`}
                        />
                        {error && <p className="text-xs text-red-500 font-bold mt-2 m-0">{error}</p>}
                    </div>
                    <div className="flex flex-wrap gap-3 justify-end">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} theme-element border theme-border outline-none`}>
                            Cancelar
                        </button>
                        <button type="submit" disabled={procesando} className={`${BTN_PRIMARY} outline-none disabled:opacity-50`}>
                            Confirmar reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
