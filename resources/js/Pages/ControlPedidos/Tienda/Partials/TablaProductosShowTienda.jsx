import React from 'react';
import { geliaCardClass, THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../../utils/geliaTheme';

export default function TablaProductosShowTienda({
    productos = [],
    productosState = [],
    estados_fisicos = {},
    editable,
    requisitos = {},
    evidenciasProducto = {},
    setProductos,
    setEvidenciasProducto,
}) {
    if (!productos.length) return null;

    return (
        <section className={`${geliaCardClass()} p-0 overflow-hidden`}>
            <div className="px-4 py-3 border-b theme-border flex items-center justify-between gap-2">
                <h3 className="text-sm font-bold theme-text-main m-0">Productos</h3>
                <span className="text-xs font-semibold theme-text-muted tabular-nums">{productos.length} líneas</span>
            </div>
            {editable ? (
                <div className="divide-y theme-border">
                    {productos.map((p, i) => (
                        <div key={p.id} className="p-4 space-y-3">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-bold text-sm theme-text-main m-0">{p.descripcion_snapshot}</p>
                                    {p.sku && <p className="text-xs theme-text-muted m-0 mt-0.5">SKU {p.sku}</p>}
                                </div>
                                <span className="shrink-0 text-xs font-semibold px-2 py-1 rounded-lg theme-element border theme-border tabular-nums">
                                    Sol. {p.cantidad_solicitada}
                                </span>
                            </div>
                            <div className="grid sm:grid-cols-2 gap-3">
                                <div>
                                    <label className={THEME_LABEL}>Cantidad encontrada</label>
                                    <input
                                        type="number"
                                        min="0"
                                        className={THEME_INPUT}
                                        value={productosState[i]?.cantidad_encontrada ?? ''}
                                        onChange={(e) => setProductos((prev) => prev.map((x, j) => (j === i ? { ...x, cantidad_encontrada: e.target.value } : x)))}
                                    />
                                </div>
                                <div>
                                    <label className={THEME_LABEL}>Estado físico</label>
                                    <select
                                        className={THEME_SELECT}
                                        value={productosState[i]?.estado_fisico || ''}
                                        onChange={(e) => setProductos((prev) => prev.map((x, j) => (j === i ? { ...x, estado_fisico: e.target.value } : x)))}
                                    >
                                        {Object.entries(estados_fisicos).map(([k, v]) => (
                                            <option key={k} value={k}>{v}</option>
                                        ))}
                                    </select>
                                </div>
                                {requisitos.evidencia_por_producto && Number(productosState[i]?.cantidad_encontrada) > 0 && (
                                    <div className="sm:col-span-2">
                                        <label className={THEME_LABEL}>Evidencia del producto</label>
                                        <input
                                            type="file"
                                            accept="image/*,application/pdf"
                                            className={THEME_INPUT}
                                            onChange={(e) => {
                                                const file = e.target.files?.[0] || null;
                                                setEvidenciasProducto((prev) => ({ ...prev, [p.id]: file }));
                                            }}
                                        />
                                    </div>
                                )}
                                <div className="sm:col-span-2">
                                    <label className={THEME_LABEL}>Observación</label>
                                    <input
                                        className={THEME_INPUT}
                                        value={productosState[i]?.observacion || ''}
                                        onChange={(e) => setProductos((prev) => prev.map((x, j) => (j === i ? { ...x, observacion: e.target.value } : x)))}
                                    />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="gelia-tienda-op-table-wrap border-0 rounded-none">
                    <table className="gelia-tienda-op-table">
                        <thead>
                            <tr>
                                <th scope="col">Producto</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Solicitado</th>
                                <th scope="col">Encontrado</th>
                                <th scope="col">Estado físico</th>
                            </tr>
                        </thead>
                        <tbody>
                            {productos.map((p) => (
                                <tr key={p.id}>
                                    <td className="font-semibold theme-text-main max-w-[14rem]">
                                        <span className="line-clamp-2">{p.descripcion_snapshot}</span>
                                    </td>
                                    <td className="theme-text-muted text-xs">{p.sku || '—'}</td>
                                    <td className="tabular-nums">{p.cantidad_solicitada}</td>
                                    <td className="tabular-nums">{p.cantidad_encontrada ?? '—'}</td>
                                    <td>{estados_fisicos[p.estado_fisico] || p.estado_fisico || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
