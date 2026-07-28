import React, { useState, useEffect, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Database, ArrowLeft, Search, Edit2, UploadCloud, Plus, Users } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import ModalEditarDatosFiscales from '../Partials/ModalEditarDatosFiscales';
import ModalEditarReceptorFiscal from '../Partials/ModalEditarReceptorFiscal';
import ModalImportarDatosFiscales from '../Partials/ModalImportarDatosFiscales';
import { BTN_PRIMARY, BTN_SECONDARY } from '../Partials/facturasStyles';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import {
    geliaCardClass,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
} from '../../../utils/geliaTheme';

const TABS = [
    { id: 'clientes', label: 'Clientes' },
    { id: 'receptores', label: 'Receptores (terceros)' },
];

export default function DatosFiscalesIndex({
    tab = 'clientes',
    clientes,
    receptores,
    filtros = {},
    catalogos = { regimen_fiscal: [], uso_cfdi: [] },
}) {
    const [editandoCliente, setEditandoCliente] = useState(null);
    const [editandoReceptor, setEditandoReceptor] = useState(null);
    const [creandoReceptor, setCreandoReceptor] = useState(false);
    const [importando, setImportando] = useState(false);
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        setBusqueda(filtros.q || '');
    }, [filtros.q]);

    const recargar = useCallback((params) => {
        router.get(route('facturas.datos_fiscales.index'), { tab, ...params }, {
            only: [tab === 'clientes' ? 'clientes' : 'receptores', 'filtros'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            showProgress: false,
            onStart: () => setCargando(true),
            onFinish: () => setCargando(false),
        });
    }, [tab]);

    useEffect(() => {
        const t = setTimeout(() => {
            if (busqueda !== (filtros.q || '')) {
                recargar({ q: busqueda.trim() || undefined, page: 1 });
            }
        }, 400);
        return () => clearTimeout(t);
    }, [busqueda, filtros.q, recargar]);

    const cambiarTab = (nuevoTab) => {
        if (nuevoTab === tab) return;
        router.get(route('facturas.datos_fiscales.index'), { tab: nuevoTab, q: busqueda.trim() || undefined, page: 1 }, {
            preserveScroll: true,
            onStart: () => setCargando(true),
            onFinish: () => setCargando(false),
        });
    };

    const paginador = tab === 'clientes' ? clientes : receptores;

    const irAPagina = (pagina) => {
        if (pagina < 1 || pagina > (paginador?.last_page || 1)) return;
        recargar({ q: filtros.q || undefined, page: pagina });
    };

    const lista = paginador?.data || [];
    const cardHeader = geliaCardClass('p-6 md:p-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4');

    return (
        <AppLayout>
            <Head title="Datos fiscales" />

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
                                RFC, régimen y razón social de clientes y receptores
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            onClick={() => setImportando(true)}
                            className={`${BTN_SECONDARY} shrink-0`}
                        >
                            <UploadCloud className="w-4 h-4 shrink-0" /> Importar
                        </button>
                        {tab === 'receptores' && (
                            <button
                                type="button"
                                onClick={() => setCreandoReceptor(true)}
                                className={`${BTN_PRIMARY} shrink-0`}
                            >
                                <Plus className="w-4 h-4 shrink-0" /> Nuevo receptor
                            </button>
                        )}
                    </div>
                </header>

                <section className={`${geliaCardClass('overflow-hidden')} ${cargando ? 'opacity-90' : ''}`}>
                    <div className={`${GELIA_SEGMENT_TABS_SCROLL} p-3 md:p-4 border-b theme-border`}>
                        <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label="Origen de datos fiscales">
                            {TABS.map((t) => (
                                <button
                                    key={t.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={tab === t.id}
                                    onClick={() => cambiarTab(t.id)}
                                    className="gelia-segment-btn whitespace-nowrap"
                                    data-active={tab === t.id}
                                >
                                    {t.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="p-4 md:p-5 border-b theme-border">
                        <label htmlFor="df-busqueda" className="theme-label ml-1">
                            {tab === 'clientes' ? 'Buscar cliente' : 'Buscar receptor'}
                        </label>
                        <div className="theme-field-with-icon mt-1.5 max-w-xl">
                            <Search className="theme-field-icon" aria-hidden />
                            <input
                                id="df-busqueda"
                                type="search"
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                placeholder={tab === 'clientes'
                                    ? 'Número, nombre, RFC o razón social…'
                                    : 'Código interno, RFC o razón social…'}
                                className="theme-input w-full pr-4 py-3 normal-case tracking-normal font-bold text-sm"
                            />
                        </div>
                    </div>

                    {tab === 'clientes' ? (
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
                                                        onClick={() => setEditandoCliente(c)}
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
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse min-w-[640px]">
                                <thead>
                                    <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                        <th className="px-4 md:px-6 py-4 text-left">Código</th>
                                        <th className="px-4 md:px-6 py-4 text-left">RFC</th>
                                        <th className="px-4 md:px-6 py-4 text-left hidden md:table-cell">Razón social</th>
                                        <th className="px-4 md:px-6 py-4 text-left">Estado</th>
                                        <th className="px-4 md:px-6 py-4 text-right">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lista.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-6 py-16 text-center">
                                                <Users className="w-10 h-10 mx-auto mb-3 theme-text-muted opacity-40" />
                                                <p className="text-sm font-black italic uppercase theme-text-main m-0">Sin receptores</p>
                                                <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-2 m-0">
                                                    Crea uno nuevo o ajusta la búsqueda
                                                </p>
                                            </td>
                                        </tr>
                                    ) : (
                                        lista.map((r) => (
                                            <tr
                                                key={r.id}
                                                className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors"
                                            >
                                                <td className="px-4 md:px-6 py-4 min-w-[110px]">
                                                    <p className="text-xs font-black theme-text-main m-0">{r.codigo_interno}</p>
                                                </td>
                                                <td className="px-4 md:px-6 py-4 text-xs font-mono font-bold theme-text-main whitespace-nowrap">
                                                    {r.rfc || '—'}
                                                </td>
                                                <td className="px-4 md:px-6 py-4 text-xs font-bold theme-text-main hidden md:table-cell max-w-[260px] truncate">
                                                    {r.nombre_razon_social || '—'}
                                                </td>
                                                <td className="px-4 md:px-6 py-4">
                                                    <span
                                                        className={`inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border ${
                                                            r.activo
                                                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30'
                                                                : 'bg-red-500/15 text-red-700 dark:text-red-300 border-red-500/30'
                                                        }`}
                                                    >
                                                        {r.activo ? 'Activo' : 'Inactivo'}
                                                    </span>
                                                </td>
                                                <td className="px-4 md:px-6 py-4 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => setEditandoReceptor(r)}
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
                    )}

                    {(paginador?.last_page || 1) > 1 && (
                        <GeliaPaginacion paginator={paginador} onIrAPagina={irAPagina} embedded />
                    )}
                </section>
            </GeliaPageShell>

            {editandoCliente && (
                <ModalEditarDatosFiscales
                    cliente={editandoCliente}
                    catalogos={catalogos}
                    onClose={() => setEditandoCliente(null)}
                />
            )}

            {(editandoReceptor || creandoReceptor) && (
                <ModalEditarReceptorFiscal
                    receptor={editandoReceptor}
                    catalogos={catalogos}
                    onClose={() => {
                        setEditandoReceptor(null);
                        setCreandoReceptor(false);
                    }}
                />
            )}

            {importando && (
                <ModalImportarDatosFiscales
                    tipo={tab}
                    onClose={() => setImportando(false)}
                />
            )}
        </AppLayout>
    );
}
