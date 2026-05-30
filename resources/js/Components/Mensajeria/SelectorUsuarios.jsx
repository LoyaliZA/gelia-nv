import React, { useEffect, useRef, useState } from 'react';
import { Search } from 'lucide-react';
import MensajeriaService from '@/Services/MensajeriaService';
import AvatarUsuario from './AvatarUsuario';

export default function SelectorUsuarios({ seleccionados = [], onToggle, multiple = false, placeholder = 'Buscar usuario...' }) {
    const [q, setQ] = useState('');
    const [resultados, setResultados] = useState([]);
    const [cargando, setCargando] = useState(false);
    const debounceRef = useRef(null);

    useEffect(() => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(async () => {
            setCargando(true);
            try {
                const usuarios = await MensajeriaService.buscarUsuarios(q);
                setResultados(usuarios);
            } finally {
                setCargando(false);
            }
        }, 300);

        return () => clearTimeout(debounceRef.current);
    }, [q]);

    const idsSeleccionados = seleccionados.map((u) => u.id);

    return (
        <div>
            <div className="relative mb-2">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 opacity-40" />
                <input
                    type="text"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder={placeholder}
                    className="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm theme-element theme-text-main border theme-border outline-none focus:border-[var(--color-primario)] placeholder:theme-text-muted"
                />
            </div>

            {seleccionados.length > 0 && (
                <div className="flex flex-wrap gap-2 mb-3">
                    {seleccionados.map((u) => (
                        <span
                            key={u.id}
                            className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-[var(--color-primario)] text-white"
                        >
                            {u.name}
                            <button type="button" onClick={() => onToggle(u)} className="opacity-70 hover:opacity-100">×</button>
                        </span>
                    ))}
                </div>
            )}

            <div className="max-h-48 overflow-y-auto space-y-1">
                {cargando && <p className="text-xs theme-text-muted text-center py-2">Buscando...</p>}
                {!cargando && resultados.map((u) => {
                    const activo = idsSeleccionados.includes(u.id);
                    return (
                        <button
                            key={u.id}
                            type="button"
                            onClick={() => onToggle(u)}
                            className={`w-full flex items-center gap-3 px-3 py-2 rounded-xl text-left transition-colors theme-text-main ${
                                activo ? 'bg-[var(--color-primario)]/15 border border-[var(--color-primario)]' : 'hover:theme-element border border-transparent'
                            }`}
                        >
                            <AvatarUsuario
                                foto={u.foto_perfil}
                                nombre={u.name}
                                className="w-8 h-8"
                                iconClassName="w-4 h-4"
                            />
                            <div className="min-w-0">
                                <p className="text-sm font-bold truncate theme-text-main">{u.name}</p>
                                <p className="text-[10px] theme-text-muted truncate">@{u.username || u.email}</p>
                            </div>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
