import React, { useEffect, useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import { Bot, Search, User, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
} from '@/utils/geliaTheme';

const LABELS = {
    general: 'General (todos los autenticados)',
    usuarios: 'Usuarios específicos',
    super_admin: 'Solo Super Admin',
};

export default function Acceso({ auth, acceso_modo = 'super_admin', usuarios = [], modos = [] }) {
    const { data, setData, put, processing, errors } = useForm({
        acceso_modo,
        user_ids: usuarios.map((u) => u.id),
    });

    const [seleccionados, setSeleccionados] = useState(usuarios);
    const [q, setQ] = useState('');
    const [resultados, setResultados] = useState([]);
    const [buscando, setBuscando] = useState(false);

    useEffect(() => {
        setData('user_ids', seleccionados.map((u) => u.id));
    }, [seleccionados]);

    useEffect(() => {
        if (data.acceso_modo !== 'usuarios') return undefined;
        const t = setTimeout(() => {
            setBuscando(true);
            axios
                .get(route('admin.gelia_ai.usuarios'), { params: { q: q || undefined } })
                .then(({ data: rows }) => setResultados(rows))
                .catch(() => setResultados([]))
                .finally(() => setBuscando(false));
        }, 250);
        return () => clearTimeout(t);
    }, [q, data.acceso_modo]);

    const idsSet = useMemo(() => new Set(seleccionados.map((u) => u.id)), [seleccionados]);

    const agregar = (u) => {
        if (idsSet.has(u.id)) return;
        setSeleccionados((prev) => [...prev, u]);
    };

    const quitar = (id) => {
        setSeleccionados((prev) => prev.filter((u) => u.id !== id));
    };

    const guardar = (e) => {
        e.preventDefault();
        put(route('admin.gelia_ai.acceso.update'));
    };

    return (
        <AppLayout user={auth.user}>
            <Head title="Acceso GELIA" />

            <GeliaPageShell className="space-y-8 py-6 md:py-10">
                <GeliaTituloCard
                    eyebrow="Administración"
                    title="Acceso"
                    titleHighlight="GELIA"
                    description="Define quién puede abrir el asistente. La API key y la URL se configuran en Configuración del sistema (grupo DeepSeek)."
                    icon={Bot}
                />

                <form onSubmit={guardar} className={geliaCardClass('p-6 md:p-8 space-y-6 max-w-2xl')}>
                    <div className="space-y-2">
                        <label className={THEME_LABEL}>Modo de acceso</label>
                        <select
                            className={THEME_SELECT}
                            value={data.acceso_modo}
                            onChange={(e) => setData('acceso_modo', e.target.value)}
                        >
                            {(modos.length ? modos : Object.keys(LABELS)).map((modo) => (
                                <option key={modo} value={modo}>
                                    {LABELS[modo] || modo}
                                </option>
                            ))}
                        </select>
                        {errors.acceso_modo && (
                            <p className="text-xs font-bold text-red-500 m-0">{errors.acceso_modo}</p>
                        )}
                    </div>

                    {data.acceso_modo === 'usuarios' && (
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <label className={THEME_LABEL}>Agregar usuarios</label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                    <input
                                        type="search"
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        placeholder="Buscar por nombre o correo…"
                                        className={`${THEME_INPUT} pl-10`}
                                    />
                                </div>
                                <div className="rounded-xl border theme-border max-h-48 overflow-y-auto theme-surface">
                                    {buscando && (
                                        <p className="px-3 py-2 text-xs theme-text-muted m-0">Buscando…</p>
                                    )}
                                    {!buscando && resultados.length === 0 && (
                                        <p className="px-3 py-2 text-xs theme-text-muted italic m-0">Sin resultados</p>
                                    )}
                                    {resultados.map((u) => (
                                        <button
                                            key={u.id}
                                            type="button"
                                            onClick={() => agregar(u)}
                                            disabled={idsSet.has(u.id)}
                                            className="w-full text-left px-3 py-2 hover:bg-black/5 dark:hover:bg-white/5 border-b theme-border last:border-0 disabled:opacity-40"
                                        >
                                            <p className="text-sm font-bold theme-text-main m-0">{u.name}</p>
                                            <p className="text-[10px] theme-text-muted m-0">{u.email}</p>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <p className={`${THEME_LABEL} m-0`}>Seleccionados ({seleccionados.length})</p>
                                {seleccionados.length === 0 ? (
                                    <p className="text-xs theme-text-muted italic m-0">Nadie aún.</p>
                                ) : (
                                    <ul className="space-y-2 m-0 p-0 list-none">
                                        {seleccionados.map((u) => (
                                            <li
                                                key={u.id}
                                                className="flex items-center justify-between gap-2 rounded-xl px-3 py-2 theme-element border theme-border"
                                            >
                                                <div className="flex items-center gap-2 min-w-0">
                                                    <User className="w-4 h-4 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-bold theme-text-main truncate m-0">{u.name}</p>
                                                        <p className="text-[10px] theme-text-muted truncate m-0">{u.email}</p>
                                                    </div>
                                                </div>
                                                <button
                                                    type="button"
                                                    className={`${THEME_BTN_SECONDARY} !px-2 !py-1`}
                                                    onClick={() => quitar(u.id)}
                                                    aria-label="Quitar"
                                                >
                                                    <X className="w-3.5 h-3.5" />
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </div>
                    )}

                    <div className="flex flex-wrap gap-3 pt-2">
                        <button type="submit" className={THEME_BTN_PRIMARY} disabled={processing}>
                            {processing ? 'Guardando…' : 'Guardar acceso'}
                        </button>
                    </div>
                </form>
            </GeliaPageShell>
        </AppLayout>
    );
}
