import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Search, Package } from 'lucide-react';
import { INPUT_CLASS, BTN_SECONDARY_CLASS } from './activosFormStyles';

export default function SelectorActivoPadre({ value, onChange, excluirId = null, placeholder = 'Buscar activo principal...' }) {
    const [q, setQ] = useState('');
    const [resultados, setResultados] = useState([]);
    const [abierto, setAbierto] = useState(false);
    const [seleccionado, setSeleccionado] = useState(null);

    useEffect(() => {
        if (!value) {
            setSeleccionado(null);
            return;
        }
        if (seleccionado?.id === Number(value)) return;

        axios.get(route('api.activos.buscar'), { params: { q: '', excluir_id: excluirId || undefined } })
            .then(({ data }) => {
                const found = data.find((a) => a.id === Number(value));
                if (found) setSeleccionado(found);
            })
            .catch(() => {});
    }, [value, excluirId]);

    useEffect(() => {
        if (!abierto) return undefined;
        const timer = setTimeout(() => {
            axios.get(route('api.activos.buscar'), {
                params: { q: q || undefined, excluir_id: excluirId || undefined },
            })
                .then(({ data }) => setResultados(data))
                .catch(() => setResultados([]));
        }, 250);
        return () => clearTimeout(timer);
    }, [q, abierto, excluirId]);

    const elegir = (activo) => {
        setSeleccionado(activo);
        onChange(activo.id);
        setAbierto(false);
        setQ('');
    };

    const limpiar = () => {
        setSeleccionado(null);
        onChange('');
        setQ('');
    };

    return (
        <div className="relative">
            {seleccionado ? (
                <div className="flex items-center justify-between rounded-xl px-3 py-2 theme-element border theme-border">
                    <div className="flex items-center gap-2 min-w-0">
                        <Package className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div className="min-w-0">
                            <p className="text-sm font-bold theme-text-main truncate">{seleccionado.nombre}</p>
                            <p className="text-[10px] theme-text-muted truncate font-mono">{seleccionado.folio}</p>
                        </div>
                    </div>
                    <button type="button" onClick={limpiar} className={`${BTN_SECONDARY_CLASS} text-[10px] ml-2`}>Cambiar</button>
                </div>
            ) : (
                <>
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                        <input
                            type="text"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            onFocus={() => setAbierto(true)}
                            placeholder={placeholder}
                            className={`${INPUT_CLASS} pl-10`}
                        />
                    </div>
                    {abierto && (
                        <div className="absolute z-50 mt-1 w-full max-h-48 overflow-y-auto rounded-xl border theme-border theme-surface shadow-xl">
                            {resultados.length === 0 ? (
                                <p className="px-3 py-2 text-xs theme-text-muted italic">Sin resultados</p>
                            ) : resultados.map((activo) => (
                                <button
                                    key={activo.id}
                                    type="button"
                                    onClick={() => elegir(activo)}
                                    className="w-full text-left px-3 py-2 hover:bg-black/5 dark:hover:bg-white/5 border-b theme-border last:border-0"
                                >
                                    <p className="text-sm font-bold theme-text-main">{activo.nombre}</p>
                                    <p className="text-[10px] theme-text-muted font-mono">{activo.folio}{activo.tipo ? ` · ${activo.tipo}` : ''}</p>
                                </button>
                            ))}
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
