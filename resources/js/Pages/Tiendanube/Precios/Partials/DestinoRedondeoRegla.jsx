import React from 'react';
import { GELIA_FIELDSET_LEGEND } from '../../../../utils/geliaTheme';

export default function DestinoRedondeoRegla({
    destino,
    redondeo,
    redondeoDireccion,
    metadatos,
    onChange,
    error,
}) {
    const destinos = metadatos?.destinos || [];
    const redondeos = metadatos?.redondeos || [];
    const direcciones = metadatos?.redondeo_direcciones || [];

    return (
        <div className="space-y-4">
            <fieldset className="space-y-2">
                <legend className={GELIA_FIELDSET_LEGEND}>
                    Guardar resultado en
                </legend>
                <label className="block text-xs theme-text-main">
                    Destino
                    <select
                        className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                        value={destino}
                        onChange={(e) => onChange({ destino: e.target.value })}
                        aria-invalid={!!error}
                    >
                        {destinos.map((o) => (
                            <option key={o.value} value={o.value}>{o.label}</option>
                        ))}
                    </select>
                </label>
                {destino === 'promocional' && (
                    <p className="text-xs theme-text-muted m-0">
                        Para conservar o quitar promoción use la operación correspondiente. Un nuevo precio normal puede ser incompatible con eliminar promoción en la misma fila.
                    </p>
                )}
            </fieldset>
            <fieldset className="space-y-2">
                <legend className={GELIA_FIELDSET_LEGEND}>
                    Redondeo
                </legend>
                <label className="block text-xs theme-text-main">
                    Modo
                    <select
                        className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                        value={redondeo}
                        onChange={(e) => onChange({ redondeo: e.target.value })}
                    >
                        {redondeos.map((o) => (
                            <option key={o.value} value={o.value}>{o.label}</option>
                        ))}
                    </select>
                </label>
                <label className="block text-xs theme-text-main">
                    Dirección
                    <select
                        className="mt-1 w-full rounded-lg border theme-border px-2 py-1.5 text-sm"
                        value={redondeoDireccion}
                        onChange={(e) => onChange({ redondeo_direccion: e.target.value })}
                    >
                        {direcciones.map((o) => (
                            <option key={o.value} value={o.value}>{o.label}</option>
                        ))}
                    </select>
                </label>
            </fieldset>
            {error && <p className="text-xs text-red-600 m-0" role="alert">{error}</p>}
        </div>
    );
}
