import React from 'react';
import { GELIA_FIELDSET_LEGEND } from '../../../../utils/geliaTheme';
import { requiereListaBase } from './reglaFormUtils';

export default function CampoBaseRegla({ base, baseListaId, metadatos, listas = [], onChange, error }) {
    const bases = metadatos?.bases || [];

    return (
        <fieldset className="space-y-2">
            <legend className={GELIA_FIELDSET_LEGEND}>
                Calcular desde
            </legend>
            <label className="block text-xs theme-text-main">
                Base
                <select
                    className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                    value={base}
                    onChange={(e) => onChange({ base: e.target.value, base_lista_id: '' })}
                    aria-invalid={!!error}
                >
                    {bases.map((o) => (
                        <option key={o.value} value={o.value}>{o.label}</option>
                    ))}
                </select>
            </label>
            {requiereListaBase(base) && (
                <label className="block text-xs theme-text-main">
                    Lista de referencia
                    <select
                        className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                        value={baseListaId || ''}
                        onChange={(e) => onChange({ base_lista_id: e.target.value })}
                        aria-invalid={!!error}
                    >
                        <option value="">Seleccione lista</option>
                        {listas.map((l) => (
                            <option key={l.id} value={l.id}>{l.nombre}</option>
                        ))}
                    </select>
                </label>
            )}
            {error && <p className="text-xs text-red-600 m-0" role="alert">{error}</p>}
        </fieldset>
    );
}
