import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { MapPin, Search, ClipboardList, ClipboardCheck } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
} from '../../../utils/geliaTheme';

export default function Index({ clientes = { data: [] }, filtros = {} }) {
    const { auth } = usePage().props;
    const can = (p) => auth?.user?.permissions?.includes(p)
        || auth?.user?.roles?.includes('Admin')
        || auth?.user?.roles?.includes('Super Admin')
        || auth?.user?.roles?.includes('Super admin (admin)');

    const [q, setQ] = useState(filtros.q || '');
    const debounce = useRef(null);

    useEffect(() => {
        setQ(filtros.q || '');
    }, [filtros.q]);

    useEffect(() => {
        if (debounce.current) clearTimeout(debounce.current);
        debounce.current = setTimeout(() => {
            const valor = q.trim();
            if (valor === (filtros.q || '')) return;
            router.get(route('control_pedidos.direcciones.index'), {
                q: valor || undefined,
            }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 350);
        return () => clearTimeout(debounce.current);
    }, [q, filtros.q]);

    const irCliente = (id) => {
        router.get(route('control_pedidos.direcciones.cliente', id));
    };

    const filas = clientes?.data || [];

    return (
        <AppLayout>
            <Head title="Direcciones · Auxiliar" />
            <GeliaPageShell className="space-y-6">
                <header className={`${geliaCardClass()} p-6 md:p-8`}>
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>
                                Gestión Pedidos
                            </p>
                            <h1 className="text-3xl md:text-4xl font-black italic tracking-tighter uppercase theme-text-main m-0 mt-1">
                                Direcciones
                            </h1>
                            <p className="text-sm theme-text-muted mt-2 m-0">
                                Clientes y direcciones registradas. Use la búsqueda para filtrar.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {can('control_pedidos.auditar') && (
                                <button
                                    type="button"
                                    className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}
                                    onClick={() => router.get(route('control_pedidos.auditar.index'))}
                                >
                                    <ClipboardCheck className="w-4 h-4" />
                                    Auditar
                                </button>
                            )}
                            {can('clientes.direcciones.revisar_solicitudes') && (
                                <button
                                    type="button"
                                    className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}
                                    onClick={() => router.get(route('control_pedidos.direcciones.solicitudes.index'))}
                                >
                                    <ClipboardList className="w-4 h-4" />
                                    Solicitudes
                                </button>
                            )}
                        </div>
                    </div>
                </header>

                <div className={`${geliaCardClass()} p-5 md:p-6`}>
                    <label className="block">
                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Buscar cliente</span>
                        <div className="theme-field-with-icon relative mt-2">
                            <Search className="theme-field-icon w-4 h-4" />
                            <input
                                type="text"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Número o nombre…"
                                className={`${THEME_INPUT} w-full py-3.5`}
                                autoComplete="off"
                            />
                        </div>
                    </label>
                </div>

                <div className={`${geliaCardClass()} overflow-hidden`}>
                    {filas.length === 0 ? (
                        <div className="p-12 text-center space-y-3">
                            <MapPin className="w-10 h-10 mx-auto theme-text-muted opacity-40" />
                            <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted italic m-0">
                                {filtros.q ? 'Sin coincidencias con este filtro_' : 'No hay clientes registrados_'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse min-w-[640px]">
                                <thead>
                                    <tr className="border-b theme-border">
                                        <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Número_</th>
                                        <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                                        <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Direcciones activas_</th>
                                        <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted text-right">Acción_</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filas.map((c) => (
                                        <tr
                                            key={c.id}
                                            className="border-b theme-border hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                                        >
                                            <td className="p-4 font-mono text-xs font-black theme-text-muted">
                                                {c.numero_cliente}
                                            </td>
                                            <td className="p-4 text-sm font-bold theme-text-main">
                                                {c.nombre}
                                            </td>
                                            <td className="p-4 text-sm theme-text-muted">
                                                {c.direcciones_activas_count ?? 0}
                                            </td>
                                            <td className="p-4 text-right">
                                                <button
                                                    type="button"
                                                    className={THEME_BTN_PRIMARY}
                                                    onClick={() => irCliente(c.id)}
                                                >
                                                    Abrir
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="p-4 border-t theme-border">
                        <GeliaPaginacion
                            paginator={clientes}
                            onIrAPagina={(page) => router.get(route('control_pedidos.direcciones.index'), {
                                q: filtros.q || undefined,
                                page,
                            }, {
                                preserveState: true,
                                preserveScroll: true,
                            })}
                        />
                    </div>
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
