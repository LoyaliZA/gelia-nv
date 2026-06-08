import React, { useState } from 'react';
import { X, Check, Clock, AlertTriangle, FileText } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import { router } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import axios from 'axios';

export default function ModalHistorialCliente({ cliente, onClose, onVerificado }) {
    if (!cliente) return null;

    const [verificando, setVerificando] = useState(false);

    const facturas = cliente.facturas_cobranza || [];

    const formatearFecha = (fechaIso) => {
        if (!fechaIso) return 'N/A';
        const d = new Date(fechaIso);
        return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    const handleVerificar = (facturaId) => {
        setVerificando(true);
        axios.post(route('auto-cobranza.facturas.verificar', facturaId))
            .then(res => {
                alert(res.data.message);
                if (onVerificado) onVerificado();
            })
            .catch(err => {
                console.error(err);
                alert('Error al verificar la factura.');
            })
            .finally(() => {
                setVerificando(false);
            });
    };

    if (typeof document === 'undefined') return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[9999] p-4`} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden`} onClick={e => e.stopPropagation()}>
                <div className="p-6 md:p-8 flex justify-between items-center border-b theme-border bg-black/5 dark:bg-white/5">
                    <div>
                        <h3 className="text-lg font-black uppercase italic tracking-tighter theme-text-main flex items-center gap-2">
                            <FileText className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            Historial Global de Créditos
                        </h3>
                        <p className="text-xs theme-text-muted mt-1 font-bold">
                            Cliente: <span style={{ color: 'var(--color-primario)' }}>{cliente.nombre}</span> (No. {cliente.numero_cliente})
                        </p>
                    </div>
                    <button onClick={onClose} className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-6 md:p-8">
                    {facturas.length === 0 ? (
                        <p className="text-center text-xs theme-text-muted uppercase tracking-widest font-bold py-12">
                            No hay facturas de crédito registradas para este cliente.
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {facturas.map((factura) => {
                                const isPagada = factura.pagada;
                                const isDelayed = isPagada 
                                    ? new Date(factura.updated_at) > new Date(factura.fecha_vencimiento)
                                    : new Date() > new Date(factura.fecha_vencimiento);
                                
                                return (
                                    <div key={factura.id} className="p-4 border theme-border rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4 bg-white/50 dark:bg-black/20">
                                        <div className="flex-1 space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-black theme-text-main uppercase">{factura.folio}</span>
                                                {isPagada ? (
                                                    <span className="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-500">
                                                        Pagado
                                                    </span>
                                                ) : (
                                                    <span className="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-amber-500/10 text-amber-500">
                                                        Pendiente
                                                    </span>
                                                )}
                                                {isDelayed && (
                                                    <span className="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-red-500/10 text-red-500 flex items-center gap-1">
                                                        <AlertTriangle className="w-3 h-3" /> Atraso
                                                    </span>
                                                )}
                                            </div>
                                            <div className="text-[10px] theme-text-muted font-bold grid grid-cols-2 gap-2 mt-2">
                                                <div>
                                                    <span className="block uppercase tracking-widest opacity-70">Emisión</span>
                                                    {formatearFecha(factura.fecha_emision)}
                                                </div>
                                                <div>
                                                    <span className="block uppercase tracking-widest opacity-70">Vencimiento</span>
                                                    {formatearFecha(factura.fecha_vencimiento)}
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="text-right">
                                            <p className="text-sm font-black theme-text-main mb-2">
                                                {formatoMoneda(factura.monto)}
                                            </p>
                                            
                                            {isPagada && !factura.verificado_manualmente && (
                                                <button
                                                    onClick={() => handleVerificar(factura.id)}
                                                    disabled={verificando}
                                                    className="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest bg-[var(--color-primario)] text-white hover:opacity-90 disabled:opacity-50 flex items-center gap-1 transition-transform active:scale-95"
                                                >
                                                    <Check className="w-3 h-3" /> 
                                                    {verificando ? 'Verificando...' : 'Confirmar Pago'}
                                                </button>
                                            )}
                                            {isPagada && factura.verificado_manualmente && (
                                                <span className="inline-flex items-center gap-1 px-2 py-1 text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400">
                                                    <Check className="w-3 h-3" /> Verificado por Admin
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
