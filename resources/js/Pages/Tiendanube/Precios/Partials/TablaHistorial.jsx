import React from 'react';
import EtiquetaEvidencia from './EtiquetaEvidencia';

function formatoFecha(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}

export default function TablaHistorial({ filas = [], onAbrir, vacio, sinResultados }) {
    if (vacio) {
        return <p className="text-sm theme-text-muted p-4">Todavía no hay operaciones.</p>;
    }
    if (sinResultados || filas.length === 0) {
        return <p className="text-sm theme-text-muted p-4">No hay resultados para estos filtros.</p>;
    }

    return (
        <>
            <div className="hidden md:block overflow-auto max-h-[70vh] border theme-border rounded-xl">
                <table className="min-w-[880px] w-full text-sm">
                    <thead className="sticky top-0 z-10 theme-surface">
                        <tr className="border-b theme-border">
                            <th className="px-3 py-2 text-left text-[10px] font-black uppercase tracking-widest theme-text-muted">Fecha</th>
                            <th className="px-3 py-2 text-left text-[10px] font-black uppercase tracking-widest theme-text-muted">Descripción</th>
                            <th className="px-3 py-2 text-left text-[10px] font-black uppercase tracking-widest theme-text-muted">Actor</th>
                            <th className="px-3 py-2 text-left text-[10px] font-black uppercase tracking-widest theme-text-muted">Canal</th>
                            <th className="px-3 py-2 text-left text-[10px] font-black uppercase tracking-widest theme-text-muted">Alcance</th>
                            <th className="px-3 py-2 text-left text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map((fila) => (
                            <tr key={fila.lote_id} className="border-b theme-border">
                                <td className="px-3 py-2 whitespace-nowrap">{formatoFecha(fila.fecha)}</td>
                                <td className="px-3 py-2">
                                    <button type="button" className="font-semibold text-left underline" onClick={() => onAbrir(fila.lote_id)}>
                                        {fila.descripcion}
                                    </button>
                                    {fila.origen === 'restauracion' && (
                                        <p className="text-[10px] theme-text-muted m-0">Compensación</p>
                                    )}
                                </td>
                                <td className="px-3 py-2">{fila.actor || '—'}</td>
                                <td className="px-3 py-2 uppercase text-xs">{fila.canal || '—'}</td>
                                <td className="px-3 py-2">{fila.productos} productos / {fila.variantes} variantes</td>
                                <td className="px-3 py-2">
                                    <EtiquetaEvidencia codigo={fila.evidencia} etiqueta={fila.evidencia_etiqueta} ayuda={fila.evidencia_ayuda} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="md:hidden space-y-2">
                {filas.map((fila) => (
                    <button
                        key={fila.lote_id}
                        type="button"
                        onClick={() => onAbrir(fila.lote_id)}
                        className="w-full text-left border theme-border rounded-xl p-3 space-y-1"
                    >
                        <p className="font-semibold theme-text-main m-0">{fila.descripcion}</p>
                        <p className="text-xs theme-text-muted m-0">{formatoFecha(fila.fecha)} · {fila.actor || '—'}</p>
                        <p className="text-xs theme-text-muted m-0">{fila.productos} productos / {fila.variantes} variantes</p>
                        <EtiquetaEvidencia codigo={fila.evidencia} etiqueta={fila.evidencia_etiqueta} ayuda={fila.evidencia_ayuda} />
                    </button>
                ))}
            </div>
        </>
    );
}
