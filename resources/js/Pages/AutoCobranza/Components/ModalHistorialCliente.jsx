import React, { useState } from 'react';
import { X, Check, AlertTriangle, FileText } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import { createPortal } from 'react-dom';
import axios from 'axios';

export default function ModalHistorialCliente({ cliente, onClose, onVerificado }) {
    if (!cliente) return null;

    const [verificando, setVerificando] = useState(false);
    const facturaActiva = cliente.factura_cobranza_activa;

    const formatearFecha = (fechaIso) => {
        if (!fechaIso) return 'N/A';
        const d = new Date(fechaIso);
        return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    const handleVerificar = (facturaId) => {
        setVerificando(true);
        axios.post(route('auto-cobranza.facturas.verificar', facturaId))
            .then((res) => {
                alert(res.data.message);
                if (onVerificado) onVerificado();
            })
            .catch((err) => {
                console.error(err);
                alert(err.response?.data?.message || 'Error al verificar la factura.');
            })
            .finally(() => {
                setVerificando(false);
            });
    };

    if (typeof document === 'undefined') return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[9999] p-4`} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden`} onClick={(e) => e.stopPropagation()}>
                <div className="p-6 md:p-8 flex justify-between items-center border-b theme-border bg-black/5 dark:bg-white/5">
                    <div>
                        <h3 className="text-lg font-black uppercase italic tracking-tighter theme-text-main flex items-center gap-2">
                            <FileText className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            Crédito Activo
                        </h3>
                        <p className="text-xs theme-text-muted mt-1 font-bold">
                            Cliente: <span className="theme-text-main">{cliente.nombre}</span> (No. {cliente.numero_cliente})
                        </p>
                    </div>
                    <button onClick={onClose} className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-6 md:p-8">
                    {!facturaActiva ? (
                        <p className="text-center text-xs theme-text-muted uppercase tracking-widest font-bold py-12">
                            Este cliente no tiene un crédito activo en este momento.
                        </p>
                    ) : (
                        <div className="p-4 border theme-border rounded-xl flex flex-col gap-4 bg-white/50 dark:bg-black/20">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-xs font-black theme-text-main uppercase">{facturaActiva.folio}</span>
                                <span className="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-amber-500/10 text-amber-500">
                                    Crédito activo
                                </span>
                                {new Date() > new Date(facturaActiva.fecha_vencimiento) && (
                                    <span className="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-red-500/10 text-red-500 flex items-center gap-1">
                                        <AlertTriangle className="w-3 h-3" /> Vencido
                                    </span>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <span className="block text-[10px] uppercase tracking-widest theme-text-muted font-black mb-1">Emisión</span>
                                    <span className="text-sm font-bold theme-text-main">{formatearFecha(facturaActiva.fecha_emision)}</span>
                                </div>
                                <div>
                                    <span className="block text-[10px] uppercase tracking-widest theme-text-muted font-black mb-1">Vencimiento</span>
                                    <span className="text-sm font-bold theme-text-main">{formatearFecha(facturaActiva.fecha_vencimiento)}</span>
                                </div>
                                <div>
                                    <span className="block text-[10px] uppercase tracking-widest theme-text-muted font-black mb-1">Inicio de crédito</span>
                                    <span className="text-sm font-bold theme-text-main">{formatearFecha(cliente.fecha_inicio_credito)}</span>
                                </div>
                                <div>
                                    <span className="block text-[10px] uppercase tracking-widest theme-text-muted font-black mb-1">Saldo consolidado</span>
                                    <span className="text-lg font-black theme-text-main">{formatoMoneda(facturaActiva.monto)}</span>
                                </div>
                            </div>

                            {facturaActiva.pagada && !facturaActiva.verificado_manualmente && (
                                <div className="pt-4 border-t theme-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <p className="text-[10px] theme-text-muted m-0">
                                        La verificación humana es opcional y solo sirve como respaldo de auditoría.
                                    </p>
                                    <button
                                        onClick={() => handleVerificar(facturaActiva.id)}
                                        disabled={verificando}
                                        className="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest theme-text-main theme-element border theme-border hover:bg-zinc-200 dark:hover:bg-zinc-700 disabled:opacity-50 flex items-center gap-1 shrink-0"
                                    >
                                        <Check className="w-3 h-3" />
                                        {verificando ? 'Verificando...' : 'Verificar (opcional)'}
                                    </button>
                                </div>
                            )}

                            {facturaActiva.pagada && facturaActiva.verificado_manualmente && (
                                <span className="inline-flex items-center gap-1 px-2 py-1 text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400">
                                    <Check className="w-3 h-3" /> Verificado por Admin
                                </span>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
