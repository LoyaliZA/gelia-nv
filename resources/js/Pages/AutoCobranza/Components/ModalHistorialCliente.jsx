import React, { useEffect, useState } from 'react';
import { X, FileText } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import { createPortal } from 'react-dom';
import DesgloseFoliosCliente from './DesgloseFoliosCliente';
import {
    cantidadFoliosHistorialCliente,
    facturasActivasCliente,
    parseFechaCobranza,
    saldoTotalCliente,
    saldoVencidoCliente,
    todasFacturasCliente,
} from '../../../utils/cobranzaCliente';
import axios from 'axios';

export default function ModalHistorialCliente({ cliente, onClose }) {
    const [clienteDetalle, setClienteDetalle] = useState(cliente);
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        if (!cliente?.id) {
            setClienteDetalle(null);
            return;
        }

        setClienteDetalle(cliente);
        setCargando(true);

        axios.get(route('auto-cobranza.clientes.folios', cliente.id))
            .then((response) => {
                setClienteDetalle(response.data);
            })
            .catch(() => {
                setClienteDetalle(cliente);
            })
            .finally(() => setCargando(false));
    }, [cliente]);

    if (!cliente) return null;

    const datos = clienteDetalle ?? cliente;
    const saldoTotal = saldoTotalCliente(datos);
    const saldoVencido = saldoVencidoCliente(datos);
    const foliosActivos = facturasActivasCliente(datos).length;
    const totalFolios = cantidadFoliosHistorialCliente(datos);
    const foliosLiquidados = Math.max(0, totalFolios - foliosActivos);

    const formatearFecha = (fechaIso) => {
        const fecha = parseFechaCobranza(fechaIso);
        if (!fecha) return 'N/A';
        return fecha.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    if (typeof document === 'undefined') return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[9999] p-4`} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden`} onClick={(e) => e.stopPropagation()}>
                <div className="p-6 md:p-8 flex justify-between items-center border-b theme-border bg-black/5 dark:bg-white/5">
                    <div>
                        <h3 className="text-lg font-black uppercase italic tracking-tighter theme-text-main flex items-center gap-2">
                            <FileText className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            Historial de Créditos
                        </h3>
                        <p className="text-xs theme-text-muted mt-1 font-bold">
                            Cliente: <span className="theme-text-main">{datos.nombre}</span> (No. {datos.numero_cliente})
                        </p>
                    </div>
                    <button onClick={onClose} className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-6 md:p-8">
                    {cargando && totalFolios === 0 ? (
                        <p className="text-center text-xs theme-text-muted uppercase tracking-widest font-bold py-12">
                            Cargando historial de folios...
                        </p>
                    ) : totalFolios === 0 ? (
                        <p className="text-center text-xs theme-text-muted uppercase tracking-widest font-bold py-12">
                            Este cliente no tiene historial de crédito registrado.
                        </p>
                    ) : (
                        <div className="flex flex-col gap-6">
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div className="p-4 border theme-border rounded-xl bg-white/50 dark:bg-black/20">
                                    <span className="block text-[11px] uppercase tracking-widest theme-text-muted font-black mb-1">Saldo activo</span>
                                    <span className="text-xl font-black text-amber-600 dark:text-amber-400">
                                        {saldoTotal != null ? formatoMoneda(saldoTotal) : '—'}
                                    </span>
                                </div>
                                <div className="p-4 border border-red-500/20 rounded-xl bg-red-500/5">
                                    <span className="block text-[11px] uppercase tracking-widest text-red-500 font-black mb-1">Saldo vencido</span>
                                    <span className="text-xl font-black text-red-500">
                                        {saldoVencido > 0 ? formatoMoneda(saldoVencido) : '—'}
                                    </span>
                                </div>
                                <div className="p-4 border theme-border rounded-xl bg-white/50 dark:bg-black/20">
                                    <span className="block text-[11px] uppercase tracking-widest theme-text-muted font-black mb-1">Folios</span>
                                    <span className="text-xl font-black theme-text-main">
                                        {foliosActivos > 0 ? `${foliosActivos} activos` : '0 activos'}
                                    </span>
                                    {foliosLiquidados > 0 && (
                                        <span className="block text-[11px] font-bold text-emerald-500 mt-1">
                                            {foliosLiquidados} liquidado{foliosLiquidados === 1 ? '' : 's'}
                                        </span>
                                    )}
                                </div>
                            </div>

                            {datos.fecha_inicio_credito && (
                                <p className="text-xs theme-text-muted font-bold">
                                    Inicio de crédito: {formatearFecha(datos.fecha_inicio_credito)}
                                </p>
                            )}

                            <DesgloseFoliosCliente cliente={datos} modoHistorial />
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
