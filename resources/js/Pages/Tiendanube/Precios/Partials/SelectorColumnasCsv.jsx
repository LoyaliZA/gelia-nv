import React from 'react';
import { geliaToggleBtnClass } from '../../../../utils/geliaTheme';

const GRUPOS = [
    { id: 'identidad', label: 'Identidad' },
    { id: 'precios', label: 'Precios' },
    { id: 'producto', label: 'Producto' },
    { id: 'inventario', label: 'Inventario' },
];

const PRESETS = [
    { id: 'solo_precios', label: 'Solo precios' },
    { id: 'identidad_precios', label: 'Identidad y precios' },
    { id: 'producto_completo', label: 'Producto completo' },
    { id: 'personalizado', label: 'Personalizado' },
];

export default function SelectorColumnasCsv({
    columnasMeta = [],
    presetsMap = {},
    preset,
    columnas,
    onPreset,
    onColumnas,
    disabled = false,
}) {
    const toggle = (clave, obligatoria) => {
        if (disabled || obligatoria) return;
        const tiene = columnas.includes(clave);
        const next = tiene ? columnas.filter((c) => c !== clave) : [...columnas, clave];
        onPreset('personalizado');
        onColumnas(next);
    };

    const elegirPreset = (id) => {
        onPreset(id);
        if (id !== 'personalizado' && presetsMap[id]) {
            onColumnas(presetsMap[id]);
        }
    };

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap gap-2">
                {PRESETS.map((p) => (
                    <button
                        key={p.id}
                        type="button"
                        disabled={disabled}
                        onClick={() => elegirPreset(p.id)}
                        className={geliaToggleBtnClass(preset === p.id)}
                        style={preset === p.id ? { backgroundColor: 'var(--color-primario)' } : undefined}
                    >
                        {p.label}
                    </button>
                ))}
            </div>
            {GRUPOS.map((grupo) => {
                const items = columnasMeta.filter((c) => c.grupo === grupo.id);
                if (items.length === 0) return null;
                return (
                    <div key={grupo.id}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1">{grupo.label}</p>
                        <div className="flex flex-wrap gap-x-3 gap-y-1">
                            {items.map((col) => (
                                <label key={col.clave} className="text-xs theme-text-main inline-flex items-center gap-1">
                                    <input
                                        type="checkbox"
                                        disabled={disabled || col.obligatoria}
                                        checked={columnas.includes(col.clave)}
                                        onChange={() => toggle(col.clave, col.obligatoria)}
                                    />
                                    {col.etiqueta}
                                </label>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
