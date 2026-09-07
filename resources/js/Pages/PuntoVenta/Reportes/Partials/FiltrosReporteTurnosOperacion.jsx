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

export default function FiltrosReporteTurnosOperacion({
    valores = {},
    catalogos = {},
    onChange,
    onAplicar,
    onLimpiar,
    cargando = false,
}) {
    const turnosCatalogos = catalogos.turnos_operacion || catalogos;
    const servicios = turnosCatalogos.servicios || [];
    const alcances = turnosCatalogos.alcances || [];
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
                <Campo label="Alcance">
                    <select
                        className={inputClass}
                        value={valores.alcance || ''}
                        onChange={(e) => set('alcance', e.target.value)}
                    >
                        <option value="">Predeterminado</option>
                        {alcances.map((a) => (
                            <option key={a.value} value={a.value}>{a.label}</option>
                        ))}
                    </select>
                </Campo>
                <Campo label="Servicio">
                    <select
                        className={inputClass}
                        value={valores.servicio || ''}
                        onChange={(e) => set('servicio', e.target.value)}
                    >
                        <option value="">Todos</option>
                        {servicios.map((s) => (
                            <option key={s.value} value={s.value}>{s.label}</option>
                        ))}
                    </select>
                </Campo>
                <Campo label="Fecha operativa">
                    <input
                        type="date"
                        className={inputClass}
                        value={valores.fecha_operativa || ''}
                        onChange={(e) => set('fecha_operativa', e.target.value)}
                    />
                </Campo>
                <Campo label="Franja desde">
                    <input
                        type="time"
                        className={inputClass}
                        value={valores.franja_desde || ''}
                        onChange={(e) => set('franja_desde', e.target.value)}
                    />
                </Campo>
                <Campo label="Franja hasta">
                    <input
                        type="time"
                        className={inputClass}
                        value={valores.franja_hasta || ''}
                        onChange={(e) => set('franja_hasta', e.target.value)}
                    />
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
