import { useState } from 'react';
import { THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '@/utils/geliaTheme';
import { ir } from './compartido';

export default function Productividad({ filas, filtros, puede }) {
    const [desde, setDesde] = useState(filtros.desde || '');
    const [hasta, setHasta] = useState(filtros.hasta || '');
    const rango = { area_id: filtros.area_id, desde: filtros.desde, hasta: filtros.hasta };
    return <section className="min-w-0 space-y-3" aria-labelledby="productividad-titulo">
        <div className="flex flex-wrap items-start justify-between gap-2">
            <div><h2 className="m-0 text-lg font-bold" id="productividad-titulo">Productividad de empaque</h2><p className="m-0 text-sm theme-text-muted">Participación física en las cajas registradas del área. El reparto del fondo se consulta en Liquidaciones.</p></div>
            {puede.exportar && <a className={THEME_BTN_SECONDARY} href={route('gestion_interna.comisiones_empaque.exportar.productividad', rango)}>Exportar productividad</a>}
        </div>
        <form className="flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); ir({ tab: 'productividad', periodo_id: undefined, desde: desde || undefined, hasta: hasta || undefined }); }}>
            <label><span className={THEME_LABEL}>Empaque desde</span><input className={THEME_INPUT} type="date" value={desde} onChange={(e) => setDesde(e.target.value)} /></label>
            <label><span className={THEME_LABEL}>Empaque hasta</span><input className={THEME_INPUT} type="date" min={desde} value={hasta} onChange={(e) => setHasta(e.target.value)} /></label>
            <button className={THEME_BTN_SECONDARY}>Aplicar rango</button>
        </form>
        <div className={geliaCardClass('min-w-0 overflow-x-auto')}>
            <table className="w-full min-w-[720px] text-left text-sm">
                <thead><tr>{['Empacador', 'Área', 'Cajas', 'Válidas', 'Pendientes', 'Excluidas', 'Pedidos', 'Participación'].map((columna) => <th className="p-3" scope="col" key={columna}>{columna}</th>)}</tr></thead>
                <tbody>
                    {!filas.length && <tr><td className="border-t theme-border p-4 theme-text-muted" colSpan={8}>No hay empaques en este rango. Cambie las fechas o actualice los empaques.</td></tr>}
                    {filas.map((fila) => <tr className="border-t theme-border" key={`${fila.area_id}-${fila.empacador_id}-${fila.filtro_nombre || ''}`}>
                        <th className="p-2" scope="row"><button type="button" className="min-h-11 px-2 text-left theme-text-primario underline underline-offset-4" onClick={() => ir({ ...rango, tab: 'acumulado', area_id: fila.area_id, empacador_id: fila.empacador_id, empacador_nombre: fila.filtro_nombre || undefined })}>{fila.nombre}</button></th>
                        <td className="p-3">{fila.area}</td>
                        <td className="p-3 tabular-nums">{fila.cajas}</td>
                        <td className="p-3 tabular-nums">{fila.validas}</td>
                        <td className="p-3 tabular-nums">{fila.pendientes}</td>
                        <td className="p-3 tabular-nums">{fila.excluidas}</td>
                        <td className="p-3 tabular-nums">{fila.pedidos}</td>
                        <td className="p-3 tabular-nums">{fila.porcentaje}%</td>
                    </tr>)}
                </tbody>
            </table>
        </div>
    </section>;
}
