import React from 'react';
import { GELIA_CHIP, THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../../utils/geliaTheme';

export default function FiltrosCatalogo({
    filtros,
    categorias = [],
    puedeVerCosto,
    onChange,
    onLimpiar,
}) {
    const chips = [];
    if (filtros.q) chips.push({ key: 'q', label: `Búsqueda: ${filtros.q}` });
    (filtros.categoria_ids || []).forEach((id) => {
        const cat = categorias.find((c) => c.id === Number(id));
        chips.push({ key: `cat-${id}`, label: cat?.nombre || `Categoría ${id}` });
    });
    if (filtros.sin_categoria) chips.push({ key: 'sin_cat', label: 'Sin categoría' });
    if (filtros.incluir_subcategorias) chips.push({ key: 'sub', label: 'Incluye subcategorías' });
    if (filtros.precio_min) chips.push({ key: 'min', label: `Desde ${filtros.precio_min}` });
    if (filtros.precio_max) chips.push({ key: 'max', label: `Hasta ${filtros.precio_max}` });
    if (filtros.promocion === 'con') chips.push({ key: 'promo', label: 'Con promoción' });
    if (filtros.promocion === 'sin') chips.push({ key: 'promo', label: 'Sin promoción' });
    if (puedeVerCosto && filtros.costo === 'con') chips.push({ key: 'costo', label: 'Con costo remoto' });
    if (puedeVerCosto && filtros.costo === 'sin') chips.push({ key: 'costo', label: 'Sin costo remoto' });

    const toggleCategoria = (id) => {
        const current = (filtros.categoria_ids || []).map(Number);
        const next = current.includes(id) ? current.filter((c) => c !== id) : [...current, id];
        onChange({ categoria_ids: next });
    };

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 items-end">
                <div className="xl:col-span-2">
                    <label htmlFor="tn-precio-q" className={THEME_LABEL}>Nombre o SKU</label>
                    <input
                        id="tn-precio-q"
                        value={filtros.q || ''}
                        onChange={(e) => onChange({ q: e.target.value })}
                        className={`${THEME_INPUT} w-full rounded-xl border theme-border px-3 py-2 text-sm`}
                    />
                </div>
                <div>
                    <label htmlFor="tn-precio-min" className={THEME_LABEL}>Precio normal desde</label>
                    <input
                        id="tn-precio-min"
                        inputMode="decimal"
                        value={filtros.precio_min || ''}
                        onChange={(e) => onChange({ precio_min: e.target.value })}
                        className={`${THEME_INPUT} w-full rounded-xl border theme-border px-3 py-2 text-sm text-right`}
                    />
                </div>
                <div>
                    <label htmlFor="tn-precio-max" className={THEME_LABEL}>Precio normal hasta</label>
                    <input
                        id="tn-precio-max"
                        inputMode="decimal"
                        value={filtros.precio_max || ''}
                        onChange={(e) => onChange({ precio_max: e.target.value })}
                        className={`${THEME_INPUT} w-full rounded-xl border theme-border px-3 py-2 text-sm text-right`}
                    />
                </div>
                <div>
                    <label htmlFor="tn-precio-promo" className={THEME_LABEL}>Promoción</label>
                    <select
                        id="tn-precio-promo"
                        value={filtros.promocion || 'cualquiera'}
                        onChange={(e) => onChange({ promocion: e.target.value })}
                        className={`${THEME_SELECT} w-full rounded-xl border theme-border px-3 py-2 text-sm`}
                    >
                        <option value="cualquiera">Cualquiera</option>
                        <option value="con">Con promoción</option>
                        <option value="sin">Sin promoción</option>
                    </select>
                </div>
                {puedeVerCosto && (
                    <div>
                        <label htmlFor="tn-precio-costo" className={THEME_LABEL}>Costo remoto</label>
                        <select
                            id="tn-precio-costo"
                            value={filtros.costo || 'cualquiera'}
                            onChange={(e) => onChange({ costo: e.target.value })}
                            className={`${THEME_SELECT} w-full rounded-xl border theme-border px-3 py-2 text-sm`}
                        >
                            <option value="cualquiera">Cualquiera</option>
                            <option value="con">Con costo</option>
                            <option value="sin">Sin costo</option>
                        </select>
                    </div>
                )}
            </div>

            <fieldset className="space-y-2">
                <legend className={`${THEME_LABEL} mb-1`}>Categorías de la tienda</legend>
                <div className="flex flex-wrap gap-x-4 gap-y-1 max-h-28 overflow-y-auto border theme-border rounded-xl px-3 py-2">
                    {categorias.length === 0 && (
                        <p className="text-xs theme-text-muted">Aún no hay categorías en el espejo local.</p>
                    )}
                    {categorias.map((cat) => (
                        <label key={cat.id} className="inline-flex items-center gap-2 text-sm theme-text-main">
                            <input
                                type="checkbox"
                                checked={(filtros.categoria_ids || []).map(Number).includes(cat.id)}
                                onChange={() => toggleCategoria(cat.id)}
                            />
                            {cat.nombre}
                        </label>
                    ))}
                </div>
                <div className="flex flex-wrap gap-4">
                    <label className="inline-flex items-center gap-2 text-sm theme-text-main">
                        <input
                            type="checkbox"
                            checked={!!filtros.incluir_subcategorias}
                            onChange={(e) => onChange({ incluir_subcategorias: e.target.checked })}
                        />
                        Incluir subcategorías
                    </label>
                    <label className="inline-flex items-center gap-2 text-sm theme-text-main">
                        <input
                            type="checkbox"
                            checked={!!filtros.sin_categoria}
                            onChange={(e) => onChange({ sin_categoria: e.target.checked })}
                        />
                        Sin categoría
                    </label>
                </div>
            </fieldset>

            {chips.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    {chips.map((chip) => (
                        <span key={chip.key} className={GELIA_CHIP}>
                            {chip.label}
                        </span>
                    ))}
                    <button
                        type="button"
                        onClick={onLimpiar}
                        className="text-[10px] font-black uppercase tracking-widest theme-text-muted underline"
                    >
                        Limpiar filtros
                    </button>
                </div>
            )}
        </div>
    );
}
