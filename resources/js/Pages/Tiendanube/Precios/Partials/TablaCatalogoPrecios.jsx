import React from 'react';
import { router } from '@inertiajs/react';
import { GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';

function formatoImporte(valor, vacio) {
    if (valor === null || valor === undefined || valor === '') {
        return vacio;
    }
    return valor;
}

export default function TablaCatalogoPrecios({
    filas = [],
    meta,
    conteos,
    puedeVerCosto,
    puedeEditarCosto,
    puedeVerHistorial,
    seleccionadosPagina = [],
    onToggleFila,
    onTogglePagina,
    onPagina,
    sort,
    dir,
    onSort,
}) {
    const idsPagina = filas.map((f) => f.variante_id);
    const selSet = new Set(seleccionadosPagina);
    const todas = idsPagina.length > 0 && idsPagina.every((id) => selSet.has(id));
    const algunas = idsPagina.some((id) => selSet.has(id)) && !todas;

    const headerRef = React.useRef(null);
    React.useEffect(() => {
        if (headerRef.current) {
            headerRef.current.indeterminate = algunas;
        }
    }, [algunas]);

    const thSort = (col, label, align = 'left') => (
        <th className={`px-2 py-2 ${align === 'right' ? 'text-right' : 'text-left'}`}>
            <button
                type="button"
                onClick={() => onSort(col)}
                className="font-black uppercase tracking-widest text-[10px] theme-text-muted"
            >
                {label}
                {sort === col ? (dir === 'desc' ? ' ↓' : ' ↑') : ''}
            </button>
        </th>
    );

    return (
        <div className="space-y-2">
            <p className="text-xs theme-text-muted">
                {conteos?.productos_distintos ?? 0} productos · {conteos?.variantes_total ?? 0} variantes
            </p>
            <div className="hidden md:block overflow-auto max-h-[70vh] border theme-border rounded-xl">
                <table className="min-w-[960px] w-full text-sm">
                    <thead className="sticky top-0 z-10 theme-surface">
                        <tr className="border-b theme-border">
                            <th className="px-2 py-2 w-10 sticky left-0 theme-surface">
                                <input
                                    ref={headerRef}
                                    type="checkbox"
                                    checked={todas}
                                    onChange={() => onTogglePagina(idsPagina, !todas)}
                                    aria-label="Seleccionar variantes visibles en esta página"
                                />
                            </th>
                            <th className="px-2 py-2 text-left sticky left-10 theme-surface text-[10px] font-black uppercase tracking-widest theme-text-muted">Producto</th>
                            {thSort('sku', 'SKU')}
                            <th className="px-2 py-2 text-left text-[10px] font-black uppercase tracking-widest theme-text-muted">Categorías</th>
                            {thSort('precio_normal', 'Normal', 'right')}
                            <th className="px-2 py-2 text-right text-[10px] font-black uppercase tracking-widest theme-text-muted">Promoción</th>
                            {puedeVerCosto && (
                                <th className="px-2 py-2 text-right text-[10px] font-black uppercase tracking-widest theme-text-muted">Costo remoto</th>
                            )}
                            {puedeVerCosto && (
                                <th className="px-2 py-2 text-right text-[10px] font-black uppercase tracking-widest theme-text-muted">Costo local</th>
                            )}
                            <th className="px-2 py-2 text-[10px] font-black uppercase tracking-widest theme-text-muted">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filas.map((fila) => (
                            <tr key={fila.variante_id} className="border-b theme-border align-top">
                                <td className="px-2 py-2 sticky left-0 theme-surface">
                                    <input
                                        type="checkbox"
                                        checked={selSet.has(fila.variante_id)}
                                        onChange={() => onToggleFila(fila.variante_id, !selSet.has(fila.variante_id))}
                                        aria-label={`Seleccionar variante ${fila.sku || fila.variante_id} de ${fila.nombre}`}
                                    />
                                </td>
                                <td className="px-2 py-2 sticky left-10 theme-surface">
                                    <div className="flex gap-2 min-w-[180px]">
                                        {fila.miniatura ? (
                                            <img src={fila.miniatura} alt="" className="w-10 h-10 object-cover rounded-md border theme-border" />
                                        ) : (
                                            <div className="w-10 h-10 rounded-md border theme-border" />
                                        )}
                                        <div>
                                            <p className="font-semibold theme-text-main">{fila.nombre}</p>
                                            {fila.atributos?.length > 0 && (
                                                <p className="text-xs theme-text-muted">{fila.atributos.join(' · ')}</p>
                                            )}
                                        </div>
                                    </div>
                                </td>
                                <td className="px-2 py-2 theme-text-main">{fila.sku || '—'}</td>
                                <td className="px-2 py-2 theme-text-muted text-xs">
                                    {fila.categorias?.length ? fila.categorias.map((c) => c.nombre).join(', ') : 'Sin categoría'}
                                </td>
                                <td className="px-2 py-2 text-right tabular-nums theme-text-main">
                                    {formatoImporte(fila.precio_normal, '—')}
                                </td>
                                <td className="px-2 py-2 text-right tabular-nums theme-text-main">
                                    {fila.sin_promocion ? 'Sin promoción' : formatoImporte(fila.precio_promocional, '—')}
                                </td>
                                {puedeVerCosto && (
                                    <td className="px-2 py-2 text-right tabular-nums theme-text-main">
                                        {fila.sin_costo_remoto ? 'Sin costo' : formatoImporte(fila.costo_remoto, '—')}
                                    </td>
                                )}
                                {puedeVerCosto && (
                                    <td className="px-2 py-2 text-right tabular-nums theme-text-muted">
                                        {fila.sin_costo_local ? '—' : formatoImporte(fila.costo_local, '—')}
                                    </td>
                                )}
                                <td className="px-2 py-2">
                                    <div className="flex flex-col gap-1">
                                        {puedeEditarCosto && (
                                            <button
                                                type="button"
                                                className={GELIA_BTN_OUTLINE}
                                                onClick={() => router.visit(route('tiendanube.precios.fuentes', { variante_id: fila.variante_id }))}
                                            >
                                                Editar costo
                                            </button>
                                        )}
                                        {puedeVerHistorial && (
                                            <button
                                                type="button"
                                                className={GELIA_BTN_OUTLINE}
                                                onClick={() => router.visit(route('tiendanube.precios.historial.index', { variante_id: fila.variante_id }))}
                                            >
                                                Ver cambios de precio
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="md:hidden space-y-2">
                {filas.map((fila) => (
                    <article key={fila.variante_id} className="border theme-border rounded-xl p-3 space-y-2">
                        <label className="flex items-start gap-2">
                            <input
                                type="checkbox"
                                checked={selSet.has(fila.variante_id)}
                                onChange={() => onToggleFila(fila.variante_id, !selSet.has(fila.variante_id))}
                                aria-label={`Seleccionar variante ${fila.sku || fila.variante_id}`}
                            />
                            <span className="font-semibold theme-text-main">{fila.nombre}</span>
                        </label>
                        {fila.atributos?.length > 0 && <p className="text-xs theme-text-muted">{fila.atributos.join(' · ')}</p>}
                        <p className="text-xs theme-text-muted">SKU {fila.sku || '—'}</p>
                        <dl className="grid grid-cols-2 gap-1 text-sm">
                            <dt className="theme-text-muted">Normal</dt>
                            <dd className="text-right tabular-nums">{formatoImporte(fila.precio_normal, '—')}</dd>
                            <dt className="theme-text-muted">Promoción</dt>
                            <dd className="text-right tabular-nums">{fila.sin_promocion ? 'Sin promoción' : formatoImporte(fila.precio_promocional, '—')}</dd>
                            {puedeVerCosto && (
                                <>
                                    <dt className="theme-text-muted">Costo remoto</dt>
                                    <dd className="text-right tabular-nums">{fila.sin_costo_remoto ? 'Sin costo' : formatoImporte(fila.costo_remoto, '—')}</dd>
                                </>
                            )}
                        </dl>
                        {puedeVerHistorial && (
                            <button
                                type="button"
                                className={GELIA_BTN_OUTLINE}
                                onClick={() => router.visit(route('tiendanube.precios.historial.index', { variante_id: fila.variante_id }))}
                            >
                                Ver cambios de precio
                            </button>
                        )}
                    </article>
                ))}
            </div>

            {meta && meta.last_page > 1 && (
                <div className="flex items-center justify-between text-sm">
                    <button
                        type="button"
                        disabled={meta.current_page <= 1}
                        onClick={() => onPagina(meta.current_page - 1)}
                        className="px-3 py-1 rounded-lg border theme-border disabled:opacity-40"
                    >
                        Anterior
                    </button>
                    <span className="theme-text-muted">Página {meta.current_page} de {meta.last_page}</span>
                    <button
                        type="button"
                        disabled={meta.current_page >= meta.last_page}
                        onClick={() => onPagina(meta.current_page + 1)}
                        className="px-3 py-1 rounded-lg border theme-border disabled:opacity-40"
                    >
                        Siguiente
                    </button>
                </div>
            )}
        </div>
    );
}
