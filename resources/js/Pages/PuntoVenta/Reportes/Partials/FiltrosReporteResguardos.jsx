import React from 'react';
import { Filter } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';

function Campo({ label, children, className = '' }) {
    return (
        <label className={`flex flex-col gap-1.5 min-w-0 ${className}`}>
            <span className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted">{label}</span>
            {children}
        </label>
    );
}

const inputClass = 'w-full rounded-lg border theme-border theme-element px-3 py-2 text-sm theme-text-main';

export default function FiltrosReporteResguardos({
    valores = {},
    catalogos = {},
    onChange,
    onAplicar,
    onLimpiar,
    cargando = false,
}) {
    const estados = catalogos.estados || {};
    const antiguedades = catalogos.antiguedades || {};
    const tiposIncidencia = catalogos.tipos_incidencia || {};
    const sucursales = catalogos.sucursales || [];

    const set = (campo, value) => onChange?.({ ...valores, [campo]: value });

    return (
        <div className={geliaCardClass('p-4 md:p-5 space-y-4')}>
            <div className="flex items-center gap-2">
                <Filter className="w-4 h-4 theme-text-muted" />
                <h2 className="text-sm font-semibold theme-text-main m-0">Filtros</h2>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <Campo label="Desde">
                    <input
                        type="date"
                        className={inputClass}
                        value={valores.desde || ''}
                        onChange={(e) => set('desde', e.target.value)}
                    />
                </Campo>
                <Campo label="Hasta">
                    <input
                        type="date"
                        className={inputClass}
                        value={valores.hasta || ''}
                        onChange={(e) => set('hasta', e.target.value)}
                    />
                </Campo>
                <Campo label="Sucursal">
                    <select
                        className={inputClass}
                        value={valores.sucursal_id || ''}
                        onChange={(e) => set('sucursal_id', e.target.value)}
                    >
                        <option value="">Todas (desglose)</option>
                        {sucursales.map((s) => (
                            <option key={s.id} value={s.id}>{s.nombre}</option>
                        ))}
                    </select>
                </Campo>
                <Campo label="Estado">
                    <select
                        className={inputClass}
                        value={valores.estado || ''}
                        onChange={(e) => set('estado', e.target.value)}
                    >
                        <option value="">Todos</option>
                        {Object.entries(estados).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                </Campo>
                <Campo label="Antigüedad">
                    <select
                        className={inputClass}
                        value={valores.antiguedad || ''}
                        onChange={(e) => set('antiguedad', e.target.value)}
                    >
                        <option value="">Todas</option>
                        {Object.entries(antiguedades).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                </Campo>
                <Campo label="Tipo de incidencia">
                    <select
                        className={inputClass}
                        value={valores.tipo_incidencia || ''}
                        onChange={(e) => set('tipo_incidencia', e.target.value)}
                    >
                        <option value="">Todos</option>
                        {Object.entries(tiposIncidencia).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                </Campo>
            </div>

            <div className="flex flex-col sm:flex-row gap-2 pt-2 border-t theme-border">
                <button type="button" className={THEME_BTN_PRIMARY} onClick={onAplicar} disabled={cargando}>
                    {cargando ? 'Consultando…' : 'Aplicar filtros'}
                </button>
                <button type="button" className={THEME_BTN_SECONDARY} onClick={onLimpiar} disabled={cargando}>
                    Limpiar
                </button>
            </div>
        </div>
    );
}
