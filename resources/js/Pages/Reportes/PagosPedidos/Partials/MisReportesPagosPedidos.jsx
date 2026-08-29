import React, { useCallback, useEffect, useState } from 'react';
import { Download, Eye, RefreshCw } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { reintentarPagosPedidosExportacion } from '../../../../utils/pagosPedidosReporteTracker';

function etiquetaEstado(item) {
    if (item.estado === 'processing') {
        return `Generando ${item.progress ?? 0}%`;
    }
    if (item.estado === 'completed') return 'Listo';
    if (item.estado === 'expired') return 'Expirado';
    if (item.estado === 'failed') return 'Error';
    if (item.estado === 'cancelled') return 'Cancelado';
    return item.estado;
}

export default function MisReportesPagosPedidos({
    exportacionesIniciales = [],
    puedeExportar = false,
    onVerProgreso,
}) {
    const [items, setItems] = useState(exportacionesIniciales);
    const [abierto, setAbierto] = useState(false);

    const refrescar = useCallback(async () => {
        try {
            const res = await fetch(route('reportes.pagos_pedidos.exportaciones.index'), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (res.ok) {
                const data = await res.json();
                setItems(data.exportaciones || []);
            }
        } catch {
            /* silencioso */
        }
    }, []);

    useEffect(() => {
        if (!puedeExportar) return undefined;
        refrescar();
        const iv = setInterval(refrescar, 5000);
        return () => clearInterval(iv);
    }, [puedeExportar, refrescar]);

    useEffect(() => {
        setItems(exportacionesIniciales);
    }, [exportacionesIniciales]);

    if (!puedeExportar || items.length === 0) {
        return null;
    }

    const visibles = abierto ? items : items.slice(0, 3);

    const accionArchivo = async (item) => {
        if (item.estado === 'processing') {
            onVerProgreso?.(item.id);
            return;
        }
        if (item.puede_descargar) {
            window.location.href = route('reportes.pagos_pedidos.exportar.descargar', { exportacion: item.id });
            return;
        }
        if (item.puede_reintentar) {
            const data = await reintentarPagosPedidosExportacion(item.id);
            onVerProgreso?.(data.job_id);
            refrescar();
        }
    };

    const labelAccion = (item) => {
        if (item.estado === 'processing') return 'Ver progreso';
        if (item.puede_descargar) return 'Descargar';
        if (item.puede_reintentar) return 'Generar nuevamente';
        return '—';
    };

    return (
        <div className={geliaCardClass('p-4 md:p-5 space-y-3')}>
            <div className="flex items-center justify-between gap-3">
                <p className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted m-0">Mis reportes</p>
                {items.length > 3 && (
                    <button type="button" onClick={() => setAbierto((v) => !v)} className="text-xs font-semibold theme-text-muted hover:text-[var(--color-primario)]">
                        {abierto ? 'Ver menos' : `Ver todos (${items.length})`}
                    </button>
                )}
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-sm min-w-[32rem]">
                    <thead>
                        <tr className="text-[10px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                            <th className="text-left py-2 pr-3">Reporte</th>
                            <th className="text-left py-2 pr-3">Estado</th>
                            <th className="text-left py-2 pr-3">Creado</th>
                            <th className="text-left py-2">Archivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visibles.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0">
                                <td className="py-3 pr-3 font-semibold theme-text-main">{item.titulo}</td>
                                <td className="py-3 pr-3 theme-text-muted">{etiquetaEstado(item)}</td>
                                <td className="py-3 pr-3 theme-text-muted whitespace-nowrap">{item.creado_etiqueta || item.creado_fecha}</td>
                                <td className="py-3">
                                    {(item.estado === 'processing' || item.puede_descargar || item.puede_reintentar) ? (
                                        <button
                                            type="button"
                                            onClick={() => accionArchivo(item)}
                                            className="inline-flex items-center gap-1.5 text-xs font-bold text-[var(--color-primario)] hover:underline"
                                        >
                                            {item.puede_descargar ? <Download className="w-3.5 h-3.5" /> : item.estado === 'processing' ? <Eye className="w-3.5 h-3.5" /> : <RefreshCw className="w-3.5 h-3.5" />}
                                            {labelAccion(item)}
                                        </button>
                                    ) : (
                                        <span className="text-xs theme-text-muted">—</span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
