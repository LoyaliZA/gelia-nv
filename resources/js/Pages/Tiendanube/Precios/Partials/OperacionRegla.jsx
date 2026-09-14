import React from 'react';
import { GELIA_FIELDSET_LEGEND } from '../../../../utils/geliaTheme';
import { esPorcentual, limpiarParametroAlCambiarOperacion, requiereParametro } from './reglaFormUtils';

export default function OperacionRegla({ operacion, parametro, metadatos, onChange, error }) {
    const operaciones = metadatos?.operaciones || [];
    const ayudas = metadatos?.ayudas || {};
    const muestraParam = requiereParametro(operacion);
    const porcentual = esPorcentual(operacion);

    const cambiarOperacion = (next) => {
        onChange({
            operacion: next,
            parametro: limpiarParametroAlCambiarOperacion(operacion, next, parametro),
        });
    };

    return (
        <fieldset className="space-y-2">
            <legend className={GELIA_FIELDSET_LEGEND}>
                Operación
            </legend>
            <label className="block text-xs theme-text-main">
                Tipo
                <select
                    className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                    value={operacion}
                    onChange={(e) => cambiarOperacion(e.target.value)}
                >
                    {operaciones.map((o) => (
                        <option key={o.value} value={o.value}>{o.label}</option>
                    ))}
                </select>
            </label>
            {ayudas[operacion] && (
                <p className="text-xs theme-text-muted m-0">{ayudas[operacion]}</p>
            )}
            {muestraParam && (
                <label className="block text-xs theme-text-main">
                    {porcentual ? 'Porcentaje' : 'Importe'}
                    <div className="mt-1 flex items-center gap-2">
                        {porcentual ? <span aria-hidden="true">%</span> : <span aria-hidden="true">$</span>}
                        <input
                            type="text"
                            inputMode="decimal"
                            className="flex-1 rounded-lg border theme-border px-2 py-1.5 text-sm"
                            value={parametro || ''}
                            onChange={(e) => onChange({ parametro: e.target.value })}
                            placeholder={porcentual ? 'Ej. 6' : 'Ej. 50.00'}
                            aria-invalid={!!error}
                        />
                    </div>
                </label>
            )}
            {error && <p className="text-xs text-red-600 m-0" role="alert">{error}</p>}
        </fieldset>
    );
}
