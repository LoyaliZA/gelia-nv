import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { CreditCard, X } from 'lucide-react';
import { THEME_INPUT, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import { BTN_PRIMARY, BTN_PRIMARY_STYLE } from './contabilidadStyles';
import { contabilidadRoutes, montoEsperadoBanco } from '../contabilidadRoutes';

export default function ModalConfirmarRetiro({ pedido, onCerrar }) {
    const esperado = montoEsperadoBanco(pedido);
    const { data, setData, post, processing, errors } = useForm({
        monto_real_banco: esperado.toFixed(2),
        fecha_deposito: new Date().toISOString().split('T')[0],
    });

    if (!pedido) return null;

    const enviar = (e) => {
        e.preventDefault();
        post(contabilidadRoutes.pedidosConfirmarRetiro(pedido.id), {
            preserveScroll: true,
            onSuccess: () => onCerrar(),
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md`} onClick={onCerrar}>
            <div className={`${THEME_MODAL_SHELL} w-full max-w-sm rounded-[2rem] shadow-2xl p-8 relative`} onClick={(e) => e.stopPropagation()}>
                <button type="button" onClick={onCerrar} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none">
                    <X className="w-5 h-5" />
                </button>
                <div className="flex items-center gap-3 mb-6">
                    <CreditCard className="w-6 h-6 text-emerald-500" />
                    <h3 className="text-xl font-black italic theme-text-main uppercase m-0">
                        Confirmar pago · {pedido.numero_pedido}
                    </h3>
                </div>
                <form onSubmit={enviar} className="space-y-4">
                    <div>
                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Monto real en banco</label>
                        <input
                            type="number"
                            step="0.01"
                            required
                            className={`${THEME_INPUT} w-full mt-1 rounded-xl font-bold text-right`}
                            value={data.monto_real_banco}
                            onChange={(e) => setData('monto_real_banco', e.target.value)}
                        />
                        <p className="text-[10px] theme-text-muted mt-1">
                            Esperado: <span className="font-bold theme-text-main">{formatoMoneda(esperado)}</span>
                        </p>
                        {errors.monto_real_banco && <p className="text-xs text-red-500 mt-1">{errors.monto_real_banco}</p>}
                    </div>
                    <div>
                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Fecha de ingreso</label>
                        <input
                            type="date"
                            required
                            className={`${THEME_INPUT} w-full mt-1 rounded-xl font-bold`}
                            value={data.fecha_deposito}
                            onChange={(e) => setData('fecha_deposito', e.target.value)}
                        />
                    </div>
                    <button type="submit" disabled={processing} className={`${BTN_PRIMARY} w-full`} style={BTN_PRIMARY_STYLE}>
                        {processing ? 'Procesando...' : 'Guardar y transferir a neta'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
