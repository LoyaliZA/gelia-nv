import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { INPUT_CLASS } from './activosFormStyles';

export default function CatalogCombobox({ field, value, onChange, onItemSelect, tipoActivoId, marcaId, marcaNombre, readOnly }) {
    const [q, setQ] = useState(value || '');
    const [opciones, setOpciones] = useState([]);
    const [abierto, setAbierto] = useState(false);

    useEffect(() => {
        setQ(value || '');
    }, [value]);

    useEffect(() => {
        if (!tipoActivoId || readOnly) return undefined;

        const timer = setTimeout(async () => {
            try {
                if (field.type === 'catalog_modelo') {
                    if (!marcaId) { setOpciones([]); return; }
                    const { data } = await axios.get(route('api.activos.modelos'), { params: { marca_id: marcaId, q: q || undefined } });
                    setOpciones(data);
                } else {
                    const { data } = await axios.get(route('api.activos.marcas'), { params: { tipo_id: tipoActivoId, q: q || undefined } });
                    setOpciones(data);
                }
            } catch {
                setOpciones([]);
            }
        }, 250);

        return () => clearTimeout(timer);
    }, [q, tipoActivoId, marcaId, field.type, readOnly]);

    if (readOnly) {
        return <p className="text-sm theme-text-main py-2">{value || '—'}</p>;
    }

    const elegir = (item) => {
        onChange(item.nombre);
        onItemSelect?.(item);
        setQ(item.nombre);
        setAbierto(false);
    };

    const confirmarTexto = () => {
        onChange(q.trim());
        onItemSelect?.(null);
        setAbierto(false);
    };

    return (
        <div className="relative">
            <input
                type="text"
                value={q}
                onChange={(e) => { setQ(e.target.value); setAbierto(true); }}
                onFocus={() => setAbierto(true)}
                onBlur={() => setTimeout(() => setAbierto(false), 200)}
                onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), confirmarTexto())}
                placeholder={field.type === 'catalog_modelo' && !marcaId && !marcaNombre?.trim() ? 'Seleccione marca primero' : 'Buscar o escribir...'}
                disabled={field.type === 'catalog_modelo' && !marcaId && !marcaNombre?.trim()}
                className={INPUT_CLASS}
            />
            {abierto && opciones.length > 0 && (
                <div className="absolute z-50 mt-1 w-full max-h-40 overflow-y-auto rounded-xl border theme-border theme-surface shadow-xl">
                    {opciones.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onMouseDown={() => elegir(item)}
                            className="w-full text-left px-3 py-2 text-sm theme-text-main hover:bg-black/5 dark:hover:bg-white/5 border-b theme-border last:border-0"
                        >
                            {item.nombre}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
