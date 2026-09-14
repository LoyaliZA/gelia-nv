import React from 'react';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../../utils/geliaTheme';

export default function FiltrosHistorial({ filtros, onChange, onLimpiar }) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3 items-end">
            <div className="xl:col-span-2">
                <label htmlFor="tn-hist-q" className={THEME_LABEL}>Búsqueda</label>
                <input
                    id="tn-hist-q"
                    value={filtros.q || ''}
                    onChange={(e) => onChange({ q: e.target.value, page: 1 })}
                    placeholder="SKU o nombre"
                    className={`${THEME_INPUT} w-full rounded-xl border theme-border px-3 py-2 text-sm`}
                />
            </div>
            <div>
                <label htmlFor="tn-hist-desde" className={THEME_LABEL}>Desde</label>
                <input
                    id="tn-hist-desde"
                    type="date"
                    value={filtros.desde || ''}
                    onChange={(e) => onChange({ desde: e.target.value, page: 1 })}
                    className={`${THEME_INPUT} w-full rounded-xl border theme-border px-3 py-2 text-sm`}
                />
            </div>
            <div>
                <label htmlFor="tn-hist-hasta" className={THEME_LABEL}>Hasta</label>
                <input
                    id="tn-hist-hasta"
                    type="date"
                    value={filtros.hasta || ''}
                    onChange={(e) => onChange({ hasta: e.target.value, page: 1 })}
                    className={`${THEME_INPUT} w-full rounded-xl border theme-border px-3 py-2 text-sm`}
                />
            </div>
            <div>
                <label htmlFor="tn-hist-canal" className={THEME_LABEL}>Canal</label>
                <select
                    id="tn-hist-canal"
                    value={filtros.canal || ''}
                    onChange={(e) => onChange({ canal: e.target.value, page: 1 })}
                    className={`${THEME_SELECT} w-full rounded-xl border theme-border px-3 py-2 text-sm`}
                >
                    <option value="">Cualquiera</option>
                    <option value="api">API</option>
                    <option value="csv">CSV</option>
                    <option value="aprobado_sin_entrega">Aprobado sin entrega</option>
                </select>
            </div>
            <div>
                <label htmlFor="tn-hist-resultado" className={THEME_LABEL}>Resultado</label>
                <select
                    id="tn-hist-resultado"
                    value={filtros.resultado || ''}
                    onChange={(e) => onChange({ resultado: e.target.value, page: 1 })}
                    className={`${THEME_SELECT} w-full rounded-xl border theme-border px-3 py-2 text-sm`}
                >
                    <option value="">Cualquiera</option>
                    <option value="aplicado_api">Aplicado por API</option>
                    <option value="archivo_descargado">Archivo descargado</option>
                    <option value="importacion_declarada">Importación declarada</option>
                    <option value="aprobado">Aprobado</option>
                    <option value="por_verificar">Por verificar</option>
                    <option value="coincidencia_previa">Coincidencia previa</option>
                    <option value="conflicto">Conflicto</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </div>
            <div>
                <button type="button" onClick={onLimpiar} className="text-xs theme-text-muted underline">
                    Limpiar filtros
                </button>
            </div>
        </div>
    );
}
