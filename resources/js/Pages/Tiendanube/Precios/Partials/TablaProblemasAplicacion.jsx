import React from 'react';
import { GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';

function formatearValor(valor) {
    if (!valor || typeof valor !== 'object') return '—';
    const partes = [];
    if (valor.normal != null && valor.normal !== '') partes.push(`normal ${valor.normal}`);
    if (valor.promocional != null && valor.promocional !== '') partes.push(`promo ${valor.promocional}`);
    if (valor.costo_remoto != null && valor.costo_remoto !== '') partes.push(`costo ${valor.costo_remoto}`);
    return partes.length ? partes.join(' · ') : 'sin promoción / sin cambio';
}

export default function TablaProblemasAplicacion({
    items,
    meta,
    onPagina,
    onReintentar,
    onVerificar,
    puedeAplicar,
    busy,
}) {
    const filas = items || [];
    if (filas.length === 0) {
        return <p className="text-xs theme-text-muted m-0">No hay filas con conflicto, verificación pendiente o error.</p>;
    }

    return (
        <div className="space-y-2">
            <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                    <thead>
                        <tr className="theme-text-muted">
                            <th className="font-medium py-1 pr-2">Producto</th>
                            <th className="font-medium py-1 pr-2">SKU</th>
                            <th className="font-medium py-1 pr-2">Aprobado</th>
                            <th className="font-medium py-1 pr-2">Remoto</th>
                            <th className="font-medium py-1 pr-2">Estado</th>
                            <th className="font-medium py-1">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map((fila) => (
                            <tr key={fila.id} className="border-t theme-border align-top">
                                <td className="py-2 pr-2">
                                    <div className="flex gap-2 items-start">
                                        {fila.imagen_url ? (
                                            <img src={fila.imagen_url} alt="" className="w-8 h-8 rounded object-cover" />
                                        ) : null}
                                        <div>
                                            <p className="m-0 theme-text-main">{fila.nombre || `Producto ${fila.producto_id}`}</p>
                                            <p className="m-0 theme-text-muted">{(fila.atributos || []).join(' · ')}</p>
                                        </div>
                                    </div>
                                </td>
                                <td className="py-2 pr-2 theme-text-main">{fila.sku || '—'}</td>
                                <td className="py-2 pr-2">{formatearValor(fila.valor_aprobado)}</td>
                                <td className="py-2 pr-2">{formatearValor(fila.valor_remoto)}</td>
                                <td className="py-2 pr-2">
                                    <p className="m-0 theme-text-main">{fila.estado}</p>
                                    <p className="m-0 theme-text-muted">{fila.explicacion}</p>
                                </td>
                                <td className="py-2">
                                    {puedeAplicar && (fila.acciones || []).includes('reintentar') && (
                                        <button type="button" disabled={busy} className={GELIA_BTN_OUTLINE} onClick={() => onReintentar(fila)}>
                                            Reintentar
                                        </button>
                                    )}
                                    {puedeAplicar && (fila.acciones || []).includes('verificar_resultado') && (
                                        <button type="button" disabled={busy} className={GELIA_BTN_OUTLINE} onClick={() => onVerificar(fila)}>
                                            Verificar resultado
                                        </button>
                                    )}
                                    {(fila.acciones || []).includes('revisar_conflicto') && (
                                        <p className="m-0 text-[11px] theme-text-muted">Revisar conflicto en una nueva revisión</p>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {meta?.last_page > 1 && (
                <div className="flex gap-2">
                    <button type="button" className={GELIA_BTN_OUTLINE} disabled={meta.current_page <= 1} onClick={() => onPagina(meta.current_page - 1)}>
                        Anterior
                    </button>
                    <button type="button" className={GELIA_BTN_OUTLINE} disabled={meta.current_page >= meta.last_page} onClick={() => onPagina(meta.current_page + 1)}>
                        Siguiente
                    </button>
                </div>
            )}
        </div>
    );
}
