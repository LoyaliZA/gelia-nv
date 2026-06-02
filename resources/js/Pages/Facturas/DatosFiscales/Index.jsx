import React, { useState, useEffect, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Database, ArrowLeft, Search, Edit2 } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import ModalEditarDatosFiscales from '../Partials/ModalEditarDatosFiscales';
import { BTN_SECONDARY } from '../Partials/facturasStyles';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';

export default function DatosFiscalesIndex({ clientes, filtros = {} }) {
    const [editando, setEditando] = useState(null);
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        setBusqueda(filtros.q || '');
    }, [filtros.q]);

    const recargar = useCallback((params) => {
        router.get(route('facturas.datos_fiscales.index'), params, {
            only: ['clientes', 'filtros'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            showProgress: false,
            onStart: () => setCargando(true),
            onFinish: () => setCargando(false),
        });
    }, []);

    useEffect(() => {
        const t = setTimeout(() => {
            if (busqueda !== (filtros.q || '')) {
                recargar({ q: busqueda.trim() || undefined, page: 1 });
            }
        }, 400);
        return () => clearTimeout(t);
    }, [busqueda, filtros.q, recargar]);

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > (clientes?.last_page || 1)) return;
        recargar({ q: filtros.q || undefined, page: pagina });
    };

    const lista = clientes?.data || [];
    const cardHeader = geliaCardClass('p-6 md:p-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4');
    const cardTabla = geliaCardClass('overflow-hidden');

    return (
        <AppLayout>
            <Head title="Datos fiscales de clientes" />

            <GeliaPageShell className="space-y-6 md:space-y-8">
                <header className={cardHeader}>
                    <div className="min-w-0 flex items-start gap-4">
                        <Link
                            href={route('facturas.index')}
                            className={`${BTN_SECONDARY} shrink-0`}
                        >
                            <ArrowLeft className="w-4 h-4 shrink-0" /> Facturas
                        </Link>
                        <div className="min-w-0">
                            <div className="flex items-center gap-3 mb-2">
                                <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                                <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>
                                    Catálogo fiscal
                                </p>
                            </div>
                            <h1 className="text-2xl sm:text-3xl md:text-4xl font-black italic uppercase tracking-tighter theme-text-main m-0 leading-none flex items-center gap-3 flex-wrap">
                                <Database className="w-7 h-7 md:w-8 md:h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                Datos fiscales
                            </h1>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                                RFC, régimen y razón social por cliente
                            </p>
                        </div>
                    </div>
                </header>

                <section className={`${geliaCardClass('overflow-hidden')} ${cargando ? 'opacity-90' : ''}`}>
                    <div className="p-4 md:p-5 border-b theme-border">
                        <label htmlFor="df-busqueda" className="theme-label ml-1">
                            Buscar cliente
                        </label>
                        <div className="theme-field-with-icon mt-1.5 max-w-xl">
                            <Search className="theme-field-icon" aria-hidden />
                            <input
                                id="df-busqueda"
                                type="search"
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                placeholder="Número, nombre, RFC o razón social…"
                                className="theme-input w-full pr-4 py-3 normal-case tracking-normal font-bold text-sm"
                            />
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse min-w-[640px]">
                            <thead>
                                <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                    <th className="px-4 md:px-6 py-4 text-left">Cliente</th>
                                    <th className="px-4 md:px-6 py-4 text-left">RFC</th>
                                    <th className="px-4 md:px-6 py-4 text-left hidden md:table-cell">Razón social</th>
                                    <th className="px-4 md:px-6 py-4 text-left hidden lg:table-cell">Correo</th>
                                    <th className="px-4 md:px-6 py-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {lista.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-6 py-16 text-center">
                                            <Database className="w-10 h-10 mx-auto mb-3 theme-text-muted opacity-40" />
                                            <p className="text-sm font-black italic uppercase theme-text-main m-0">Sin clientes</p>
                                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                                                Ajusta la búsqueda para ver resultados
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    lista.map((c) => (
                                        <tr
                                            key={c.id}
                                            className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors"
                                        >
                                            <td className="px-4 md:px-6 py-4 min-w-[140px]">
                                                <p className="text-xs font-black theme-text-main m-0">{c.numero_cliente}</p>
                                                <p className="text-[10px] theme-text-muted m-0 truncate max-w-[200px]">{c.nombre}</p>
                                            </td>
                                            <td className="px-4 md:px-6 py-4 text-xs font-mono font-bold theme-text-main whitespace-nowrap">
                                                {c.rfc || '—'}
                                            </td>
                                            <td className="px-4 md:px-6 py-4 text-xs font-bold theme-text-main hidden md:table-cell max-w-[220px] truncate">
                                                {c.nombre_razon_social || '—'}
                                            </td>
                                            <td className="px-4 md:px-6 py-4 text-xs theme-text-muted hidden lg:table-cell max-w-[200px] truncate">
                                                {c.correo_electronico || '—'}
                                            </td>
                                            <td className="px-4 md:px-6 py-4 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => setEditando(c)}
                                                    className={`${BTN_SECONDARY} !py-2 !px-3`}
                                                >
                                                    <Edit2 className="w-3.5 h-3.5 shrink-0" /> Editar
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {(clientes?.last_page || 1) > 1 && (
                        <GeliaPaginacion paginator={clientes} onIrAPagina={irAPagina} embedded />
                    )}
                </section>
            </GeliaPageShell>

            {editando && (
                <ModalEditarDatosFiscales cliente={editando} onClose={() => setEditando(null)} />
            )}
        </AppLayout>
    );
}
