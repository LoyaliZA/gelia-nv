import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import { geliaCardClass } from '../../utils/geliaTheme';
import { Download, ArrowLeft } from 'lucide-react';

export default function Auditoria({ auth, logs, filters }) {
    const handleFilter = (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        router.get(route('woocommerce.auditoria'), Object.fromEntries(fd), { preserveState: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Auditoría — Sincronizar Precios" />
            <div className="max-w-[1200px] mx-auto p-4 md:p-8 space-y-6">
                <div className="flex items-center gap-4">
                    <Link href={route('woocommerce.index')} className="p-2 rounded-xl border theme-border theme-text-muted hover:theme-text-main"><ArrowLeft className="w-5 h-5" /></Link>
                    <h1 className="text-2xl font-black italic uppercase theme-text-main">Auditoría de Sincronización</h1>
                </div>

                <form onSubmit={handleFilter} className={`${geliaCardClass()} p-4 flex flex-wrap gap-3`}>
                    <input name="search" defaultValue={filters.search || ''} placeholder="Buscar ID o estado..." className="px-4 py-2 text-sm theme-surface border theme-border rounded-xl flex-1 min-w-[200px]" />
                    <input type="date" name="fecha_inicio" defaultValue={filters.fecha_inicio || ''} className="px-4 py-2 text-sm theme-surface border theme-border rounded-xl" />
                    <input type="date" name="fecha_fin" defaultValue={filters.fecha_fin || ''} className="px-4 py-2 text-sm theme-surface border theme-border rounded-xl" />
                    <button type="submit" className="px-6 py-2 rounded-xl text-[10px] font-black uppercase text-white" style={{ backgroundColor: 'var(--color-primario)' }}>Filtrar</button>
                </form>

                <div className={`${geliaCardClass()} overflow-hidden`}>
                    <table className="w-full text-xs">
                        <thead>
                            <tr className="text-[10px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                                <th className="p-4 text-left">ID</th>
                                <th className="p-4 text-left">Estado</th>
                                <th className="p-4 text-left">Progreso</th>
                                <th className="p-4 text-left">Fecha</th>
                                <th className="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-b theme-border/50">
                                    <td className="p-4 font-bold theme-text-main">#{log.id}</td>
                                    <td className="p-4"><span className={`px-2 py-1 rounded text-[10px] font-black uppercase ${log.estado === 'completado' ? 'bg-emerald-500/10 text-emerald-600' : log.estado === 'error' ? 'bg-red-500/10 text-red-500' : 'theme-element theme-text-muted'}`}>{log.estado}</span></td>
                                    <td className="p-4 theme-text-muted">{log.procesados} / {log.total_productos}</td>
                                    <td className="p-4 theme-text-muted">{new Date(log.created_at).toLocaleString()}</td>
                                    <td className="p-4 text-right">
                                        <a href={route('woocommerce.auditoria.descargar', log.id)} className="inline-flex items-center gap-1 text-[10px] font-black uppercase hover:underline" style={{ color: 'var(--color-primario)' }}>
                                            <Download className="w-3 h-3" /> CSV
                                        </a>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
