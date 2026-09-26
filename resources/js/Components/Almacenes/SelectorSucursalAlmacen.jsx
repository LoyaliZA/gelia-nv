import React, { useMemo } from 'react';

export default function SelectorSucursalAlmacen({
    sucursales = [],
    almacenes = [],
    sucursalId = '',
    almacenId = '',
    onSucursalChange,
    onAlmacenChange,
    requiereAlmacen = false,
    requiereSucursal = false,
    className = 'theme-input w-full px-3 py-2 text-[11px] font-bold',
    disabled = false,
}) {
    const almacenesFiltrados = useMemo(() => {
        if (!sucursalId) return almacenes;
        return almacenes.filter((a) => String(a.sucursal_id) === String(sucursalId));
    }, [almacenes, sucursalId]);

    const handleSucursal = (e) => {
        const value = e.target.value;
        onSucursalChange?.(value);
        onAlmacenChange?.('');
    };

    return (
        <div className="space-y-3">
            <div>
                <label className="text-[10px] font-black uppercase theme-text-muted block mb-1">
                    Sucursal {requiereSucursal ? '*' : ''}
                </label>
                <select
                    value={sucursalId}
                    onChange={handleSucursal}
                    className={className}
                    disabled={disabled || sucursales.length === 0}
                >
                    <option value="">Selecciona sucursal</option>
                    {sucursales.map((s) => (
                        <option key={s.id} value={s.id}>{s.nombre}</option>
                    ))}
                </select>
            </div>
            {(requiereAlmacen || almacenes.length > 0) && (
                <div>
                    <label className="text-[10px] font-black uppercase theme-text-muted block mb-1">
                        Almacén {requiereAlmacen ? '*' : ''}
                    </label>
                    <select
                        value={almacenId}
                        onChange={(e) => onAlmacenChange?.(e.target.value)}
                        className={className}
                        disabled={disabled || (requiereSucursal && !sucursalId)}
                    >
                        <option value="">Selecciona almacén</option>
                        {almacenesFiltrados.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.codigo} — {a.nombre}
                            </option>
                        ))}
                    </select>
                </div>
            )}
        </div>
    );
}
