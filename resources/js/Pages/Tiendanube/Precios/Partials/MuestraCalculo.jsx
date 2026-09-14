import React from 'react';
import { etiquetaCampo } from './reglaFormUtils';

export default function MuestraCalculo({ preview, metadatos, puedeVerCosto }) {
    if (!preview) {
        return null;
    }

    const filas = preview.filas || [];

    return (
        <section className="space-y-2" aria-live="polite">
            <div className="rounded-xl border border-amber-300 bg-amber-50/80 px-3 py-2 text-sm text-amber-900">
                {preview.mensaje || 'Muestra; aún no revisada para aplicar'}
                {preview.total_seleccion > preview.mostradas
                    ? ` · Mostrando ${preview.mostradas} de ${preview.total_seleccion} variantes`
                    : ''}
            </div>
            {filas.length === 0 ? (
                <p className="text-sm theme-text-muted m-0">No hay filas para mostrar en la muestra.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-[10px] uppercase tracking-widest theme-text-muted">
                                <th className="py-2 pr-2">Producto</th>
                                <th className="py-2 pr-2">SKU</th>
                                <th className="py-2 pr-2">Base</th>
                                <th className="py-2 pr-2">Antes</th>
                                <th className="py-2 pr-2">Después</th>
                                <th className="py-2">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filas.map((fila) => (
                                <tr key={fila.variante_id} className="border-t theme-border">
                                    <td className="py-2 pr-2">{fila.nombre || '—'}</td>
                                    <td className="py-2 pr-2">{fila.sku || '—'}</td>
                                    <td className="py-2 pr-2">
                                        {etiquetaCampo(fila.base, metadatos)}
                                        {fila.base_faltante ? ' (faltante)' : ''}
                                    </td>
                                    <td className="py-2 pr-2">
                                        {puedeVerCosto || !['costo_local', 'costo_remoto_actual'].includes(fila.base)
                                            ? (fila.antes ?? '—')
                                            : '—'}
                                    </td>
                                    <td className="py-2 pr-2">{fila.despues ?? '—'}</td>
                                    <td className="py-2">
                                        {fila.publicable ? (
                                            <span className="text-emerald-700">Calculado</span>
                                        ) : (
                                            <span className="text-red-700" title={(fila.errores || []).join(', ')}>
                                                Con observaciones
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
