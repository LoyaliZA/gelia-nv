import React, { useCallback, useEffect, useState } from 'react';
import { Download, Eye, RefreshCw } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { reintentarPagosPedidosExportacion } from '../../../../utils/pagosPedidosReporteTracker';
import { BTN_LINK, BTN_ICON } from './pagosPedidosStyles';

function etiquetaEstado(item) {
    if (item.estado_label) return item.estado_label;
    if (item.estado === 'pending') return 'En cola';
    if (item.estado === 'processing') {
        return `Generando ${item.progress ?? 0}%`;
    }
    if (item.estado === 'completed') return 'Listo';
    if (item.estado === 'expired') return 'Expirado';
    if (item.estado === 'failed') return 'Error';
    if (item.estado === 'cancelled') return 'Cancelado';
    return item.estado;
}

function etiquetaPeriodo(item) {
    const p = item.periodo;
    if (!p) return '—';
    const desde = p.desde || '…';
    const hasta = p.hasta || '…';
    return `${desde} — ${hasta}`;
}

function etiquetaFecha(item) {
    if (!item.completed_at && item.estado === 'processing') return '—';
    if (item.completed_at) {
        try {
            return new Date(item.completed_at).toLocaleString('es-MX', {
                day: 'numeric',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return item.completed_at;
        }
    }
    return '—';
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
        if (item.estado === 'processing' || item.estado === 'pending') {
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
        if (item.estado === 'processing' || item.estado === 'pending') return 'Ver progreso';
        if (item.puede_descargar) return 'Descargar';
        if (item.puede_reintentar) return 'Generar nuevamente';
        return '—';
    };

    return (
        <div className={geliaCardClass('p-4 md:p-5 space-y-3')}>
            <div className="flex items-center justify-between gap-3">
                <p className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted m-0">Mis reportes</p>
                {items.length > 3 && (
                    <button type="button" onClick={() => setAbierto((v) => !v)} className={BTN_LINK}>
                        {abierto ? 'Ver menos' : `Ver todos (${items.length})`}
                    </button>
                )}
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-sm min-w-[48rem]">
                    <thead>
                        <tr className="text-[10px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                            <th className="text-left py-2 pr-3">Archivo</th>
                            <th className="text-left py-2 pr-3">Tipo</th>
                            <th className="text-left py-2 pr-3">Formato</th>
                            <th className="text-left py-2 pr-3">Periodo</th>
                            <th className="text-left py-2 pr-3">Criterio fecha</th>
                            <th className="text-left py-2 pr-3">Solicitado por</th>
                            <th className="text-left py-2 pr-3">Solicitud</th>
                            <th className="text-left py-2 pr-3">Fin</th>
                            <th className="text-left py-2 pr-3">Tamaño</th>
                            <th className="text-left py-2 pr-3">Estado</th>
                            <th className="text-left py-2 pr-3">Error</th>
                            <th className="text-left py-2">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visibles.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0">
                                <td className="py-3 pr-3 font-semibold theme-text-main max-w-[10rem] truncate" title={item.titulo}>
                                    {item.nombre_archivo || item.titulo}
                                </td>
                                <td className="py-3 pr-3 theme-text-muted whitespace-nowrap">{item.tipo_reporte_label || '—'}</td>
                                <td className="py-3 pr-3 theme-text-muted whitespace-nowrap">{item.formato_label || item.formato}</td>
                                <td className="py-3 pr-3 theme-text-muted whitespace-nowrap text-xs">{etiquetaPeriodo(item)}</td>
                                <td className="py-3 pr-3 theme-text-muted whitespace-nowrap text-xs">{item.criterio_fecha || '—'}</td>
                                <td className="py-3 pr-3 theme-text-muted whitespace-nowrap">{item.solicitado_por || '—'}</td>
                                <td className="py-3 pr-3 theme-text-muted whitespace-nowrap text-xs">
                                    {item.creado_fecha} {item.creado_etiqueta}
                                </td>
                                <td className="py-3 pr-3 theme-text-muted whitespace-nowrap text-xs">{etiquetaFecha(item)}</td>
                                <td className="py-3 pr-3 theme-text-muted whitespace-nowrap">{item.tamano_etiqueta || '—'}</td>
                                <td className="py-3 pr-3 theme-text-muted whitespace-nowrap">{etiquetaEstado(item)}</td>
                                <td className="py-3 pr-3 text-xs text-red-500 max-w-[8rem] truncate" title={item.error || ''}>
                                    {item.error || '—'}
                                </td>
                                <td className="py-3">
                                    {(item.estado === 'processing' || item.estado === 'pending' || item.puede_descargar || item.puede_reintentar) ? (
                                        <button
                                            type="button"
                                            onClick={() => accionArchivo(item)}
                                            className={BTN_LINK}
                                        >
                                            {item.puede_descargar ? <Download className={BTN_ICON} aria-hidden="true" /> : (item.estado === 'processing' || item.estado === 'pending') ? <Eye className={BTN_ICON} aria-hidden="true" /> : <RefreshCw className={BTN_ICON} aria-hidden="true" />}
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
