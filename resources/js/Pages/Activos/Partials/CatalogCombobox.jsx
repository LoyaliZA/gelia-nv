import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { INPUT_CLASS } from './activosFormStyles';

export default function CatalogCombobox({ field, value, onChange, onItemSelect, tipoActivoId, marcaId, marcaNombre, readOnly }) {
    const [q, setQ] = useState(value || '');
    const [opciones, setOpciones] = useState([]);
    const [abierto, setAbierto] = useState(false);
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        setQ(value || '');
    }, [value]);

    useEffect(() => {
        if (!tipoActivoId || readOnly) return undefined;

        const timer = setTimeout(async () => {
            setCargando(true);
            try {
                if (field.type === 'catalog_modelo') {
                    if (!marcaId && !marcaNombre?.trim()) {
                        setOpciones([]);
                        return;
                    }
                    const { data } = await axios.get(route('api.activos.modelos'), {
                        params: {
                            marca_id: marcaId || undefined,
                            marca_nombre: !marcaId ? marcaNombre?.trim() : undefined,
                            tipo_id: tipoActivoId,
                            q: q.trim() || undefined,
                        },
                    });
                    setOpciones(data);
                } else {
                    const { data } = await axios.get(route('api.activos.marcas'), {
                        params: { tipo_id: tipoActivoId, q: q.trim() || undefined },
                    });
                    setOpciones(data);
                }
            } catch {
                setOpciones([]);
            } finally {
                setCargando(false);
            }
        }, 250);

        return () => clearTimeout(timer);
    }, [q, tipoActivoId, marcaId, marcaNombre, field.type, readOnly]);

    if (readOnly) {
        return <p className="text-sm theme-text-main py-2">{value || '—'}</p>;
    }

    const texto = q.trim();
    const hayCoincidenciaExacta = opciones.some(
        (item) => item.nombre.toLowerCase() === texto.toLowerCase(),
    );
    const mostrarCrearNueva = texto.length > 0 && !hayCoincidenciaExacta;
    const etiquetaNueva = field.type === 'catalog_marca' ? 'marca' : 'modelo';

    const elegir = (item) => {
        onChange(item.nombre);
        onItemSelect?.(item);
        setQ(item.nombre);
        setAbierto(false);
    };

    const confirmarTexto = () => {
        if (!texto) {
            onChange('');
            return;
        }
        onChange(texto);
        setQ(texto);
        setAbierto(false);
    };

    const usarNuevaOpcion = () => {
        confirmarTexto();
    };

    const modeloBloqueado = field.type === 'catalog_modelo' && !marcaId && !marcaNombre?.trim();

    return (
        <div className="relative space-y-1">
            <input
                type="text"
                value={q}
                onChange={(e) => { setQ(e.target.value); setAbierto(true); }}
                onFocus={() => setAbierto(true)}
                onBlur={() => setTimeout(() => { confirmarTexto(); setAbierto(false); }, 200)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        confirmarTexto();
                    }
                }}
                placeholder={modeloBloqueado ? 'Seleccione marca primero' : 'Buscar o escribir...'}
                disabled={modeloBloqueado}
                className={INPUT_CLASS}
            />
            <p className="text-[9px] theme-text-muted px-0.5">
                Escriba libremente; al guardar el activo se añadirá al catálogo si es nuevo.
            </p>
            {abierto && (opciones.length > 0 || mostrarCrearNueva || cargando) && (
                <div className="absolute z-50 mt-1 w-full max-h-44 overflow-y-auto rounded-xl border theme-border theme-surface shadow-xl">
                    {cargando && opciones.length === 0 && !mostrarCrearNueva && (
                        <p className="px-3 py-2 text-xs theme-text-muted">Buscando...</p>
                    )}
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
                    {mostrarCrearNueva && (
                        <button
                            type="button"
                            onMouseDown={usarNuevaOpcion}
                            className="w-full text-left px-3 py-2 text-sm font-bold border-t theme-border"
                            style={{ color: 'var(--color-primario)' }}
                        >
                            Usar «{texto}» (nueva {etiquetaNueva})
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
