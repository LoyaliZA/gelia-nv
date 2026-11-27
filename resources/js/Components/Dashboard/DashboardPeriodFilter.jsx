import React, { useEffect, useState } from 'react';
import { CalendarRange } from 'lucide-react';
import {
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK_COMPACT,
    THEME_INPUT,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
} from '../../utils/geliaTheme';

const OPCIONES = [
    { id: 'hoy', label: 'Hoy' },
    { id: '7d', label: '7 días' },
    { id: 'mes', label: 'Mes' },
    { id: 'rango', label: 'Rango' },
];

export default function DashboardPeriodFilter({
    filtro = { clave: 'mes', desde: '', hasta: '' },
    onChange,
    disabled = false,
}) {
    const claveServidor = filtro.clave || 'mes';
    const [modo, setModo] = useState(claveServidor);
    const [desde, setDesde] = useState(filtro.desde || '');
    const [hasta, setHasta] = useState(filtro.hasta || '');

    useEffect(() => {
        setModo(claveServidor);
        setDesde(filtro.desde || '');
        setHasta(filtro.hasta || '');
    }, [claveServidor, filtro.desde, filtro.hasta]);

    const aplicarPreset = (id) => {
        if (id === 'rango') {
            setModo('rango');
            setDesde((actual) => actual || filtro.desde || '');
            setHasta((actual) => actual || filtro.hasta || '');
            return;
        }
        setModo(id);
        onChange?.({ periodo: id });
    };

    const aplicarRango = (event) => {
        event.preventDefault();
        if (!desde || !hasta) return;
        onChange?.({ periodo: 'rango', desde, hasta });
    };

    return (
        <div className="dashboard-period-filter flex flex-col lg:flex-row lg:items-center gap-3">
            <div className={GELIA_SEGMENT_TABS_SCROLL}>
                <div
                    className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK_COMPACT} p-1 shadow-sm`}
                    role="group"
                    aria-label="Periodo del panel"
                >
                    {OPCIONES.map(({ id, label }) => (
                        <button
                            key={id}
                            type="button"
                            aria-pressed={modo === id}
                            data-active={modo === id}
                            disabled={disabled}
                            onClick={() => aplicarPreset(id)}
                            className="gelia-segment-btn whitespace-nowrap min-h-[44px]"
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            {modo === 'rango' && (
                <form
                    className="dashboard-period-filter__rango theme-surface-solid theme-no-blur theme-card border theme-border shadow-sm min-w-0"
                    onSubmit={aplicarRango}
                >
                    <label className="dashboard-period-filter__campo">
                        <span className={THEME_LABEL}>Desde</span>
                        <input
                            type="date"
                            value={desde}
                            onChange={(e) => setDesde(e.target.value)}
                            className={`${THEME_INPUT} min-h-[44px]`}
                            disabled={disabled}
                        />
                    </label>
                    <label className="dashboard-period-filter__campo">
                        <span className={THEME_LABEL}>Hasta</span>
                        <input
                            type="date"
                            value={hasta}
                            min={desde || undefined}
                            onChange={(e) => setHasta(e.target.value)}
                            className={`${THEME_INPUT} min-h-[44px]`}
                            disabled={disabled}
                        />
                    </label>
                    <button
                        type="submit"
                        disabled={disabled || !desde || !hasta}
                        className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact min-h-[44px] disabled:opacity-60`}
                    >
                        <CalendarRange className="w-4 h-4" aria-hidden />
                        Aplicar rango
                    </button>
                </form>
            )}
        </div>
    );
}
