import { useState } from 'react';
import { THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';
import { ir } from './compartido';

export default function Productividad({ filas, filtros, puede }) {
    const [desde, setDesde] = useState(filtros.desde || '');
    const [hasta, setHasta] = useState(filtros.hasta || '');
    const porArea = filas.reduce((grupos, fila) => {
        const personas = grupos.get(fila.area) || [];
        personas.push(fila);
        return grupos.set(fila.area, personas);
    }, new Map());
    const resumen = [
        ['Cajas identificadas', 'cajas'], ['Bolsas', 'bolsas'], ['Bultos sin clasificar', 'sin_desglose'],
    ].map(([etiqueta, campo]) => [etiqueta, filas.reduce((total, fila) => total + (fila[campo] || 0), 0)]);
    const rango = { area_id: filtros.area_id, desde: filtros.desde, hasta: filtros.hasta };
    return <section className="min-w-0 space-y-3" aria-labelledby="productividad-titulo">
        <div className="flex flex-wrap items-start justify-between gap-2">
            <div><h2 className="m-0 text-lg font-bold" id="productividad-titulo">Productividad de empaque</h2><p className="m-0 text-sm theme-text-muted">Participación en los bultos registrados de cada área, incluidas bolsas y piezas sin clasificar.</p></div>
            {puede.exportar && <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.productividad', rango)}>Exportar productividad</a>}
        </div>
        <dl className="m-0 flex flex-wrap gap-x-6 gap-y-2 border-y theme-border py-2">
            {resumen.map(([etiqueta, valor]) => <div key={etiqueta}>
                <dt className="text-xs theme-text-muted">{etiqueta}</dt>
                <dd className="m-0 text-lg font-semibold tabular-nums">{valor}</dd>
            </div>)}
        </dl>
        <form className="flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); ir({ tab: 'productividad', periodo_id: undefined, desde: desde || undefined, hasta: hasta || undefined }); }}>
            <label><span className={THEME_LABEL}>Empaque desde</span><input className={THEME_INPUT} type="date" value={desde} onChange={(e) => setDesde(e.target.value)} /></label>
            <label><span className={THEME_LABEL}>Empaque hasta</span><input className={THEME_INPUT} type="date" min={desde} value={hasta} onChange={(e) => setHasta(e.target.value)} /></label>
            <button className={THEME_BTN_SECONDARY}>Aplicar rango</button>
        </form>
        <div className={geliaCardClass('min-w-0 overflow-x-auto')}>
            <table className="w-full min-w-[720px] text-left text-sm">
                <thead><tr>{['Empacador', 'Cajas', 'Comisionables', 'Pendientes', 'Excluidas', 'Pagadas fuera', 'Bolsas', 'Sin clasificar', 'Pedidos', 'Participación'].map((columna) => <th className="p-2" scope="col" key={columna}>{columna}</th>)}</tr></thead>
                {filas.length === 0 && <tbody><tr><td className="border-t theme-border p-4 theme-text-muted" colSpan={10}>No hay empaques en este rango. Pruebe con otras fechas.</td></tr></tbody>}
                {[...porArea].map(([area, personas]) => <tbody key={area}>
                    <tr className="border-t theme-border theme-element"><th className="px-3 py-2" scope="rowgroup" colSpan={10}>{area}</th></tr>
                    {personas.map((fila) => <tr className="border-t theme-border" key={`${fila.empacador_id}-${fila.filtro_nombre || ''}`}>
                        <th className="p-2" scope="row"><button type="button" className="min-h-11 px-1 text-left theme-text-primario underline underline-offset-4" onClick={() => ir({ ...rango, tab: 'acumulado', area_id: fila.area_id, periodo_id: undefined, empacador_id: fila.empacador_id, empacador_nombre: fila.filtro_nombre || undefined })}>{fila.nombre}</button></th>
                        {['cajas', 'validas', 'pendientes', 'excluidas', 'pagadas_fuera', 'bolsas', 'sin_desglose', 'pedidos'].map((campo) => <td key={campo} className="p-2 tabular-nums">{fila[campo] ?? 0}</td>)}
                        <td className="min-w-28 p-2 tabular-nums">
                            <span>{fila.porcentaje}%</span>
                            <div className="mt-1 h-1.5 overflow-hidden rounded-full theme-element" aria-hidden="true">
                                <div className="h-full rounded-full bg-[var(--color-info)]" style={{ width: `${fila.porcentaje}%` }} />
                            </div>
                        </td>
                    </tr>)}
                </tbody>)}
            </table>
        </div>
    </section>;
}
