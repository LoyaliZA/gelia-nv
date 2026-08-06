import React, { useState, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import { BarChart3, Search, Upload } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import WizardImportacionCatalogo from '@/Components/Almacenes/WizardImportacionCatalogo';
import { IMPORTACION_CATALOGOS } from '@/config/importacionCatalogos';
import { geliaCardClass, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';

export default function Index({ auth, ventas, almacenes, filtros, total_monto }) {
    const [busqueda, setBusqueda] = useState(filtros?.q || '');
    const [almacenId, setAlmacenId] = useState(filtros?.almacen_id || '');
    const [periodo, setPeriodo] = useState(filtros?.periodo || '');
    const [showWizard, setShowWizard] = useState(false);
    const lista = ventas?.data || [];
    const puedeImportar = auth?.user?.permissions?.includes('reportes.ventas.importar')
        || auth?.user?.roles?.includes('Super Admin');

    const paramsBase = useCallback((extra = {}) => ({
        q: busqueda,
        almacen_id: almacenId || undefined,
        periodo: periodo || undefined,
        ...extra,
    }), [busqueda, almacenId, periodo]);

    const aplicar = (extra = {}) => {
        router.get(route('reportes.ventas.index'), paramsBase(extra), { preserveState: true, replace: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Ventas de productos" />
            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6">
                <header className={geliaCardClass('p-6 space-y-4')}>
                    <div className="flex flex-col md:flex-row justify-between gap-4">
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>Reportes</p>
                            <h1 className="text-2xl font-black italic uppercase theme-text-main flex items-center gap-3 m-0">
                                <BarChart3 className="w-7 h-7" style={{ color: 'var(--color-primario)' }} />
                                Ventas
                            </h1>
                            <p className="text-[10px] font-bold theme-text-muted uppercase mt-1">
                                Snapshot importado del ERP · Total filtrado: {Number(total_monto || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}
                            </p>
                        </div>
                        {puedeImportar && (
                            <button type="button" onClick={() => setShowWizard(true)} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}>
                                <Upload className="w-4 h-4" /> Importar
                            </button>
                        )}
                    </div>
                    <form
                        onSubmit={(e) => { e.preventDefault(); aplicar({ page: 1 }); }}
                        className="flex flex-wrap gap-2 items-end"
                    >
                        <div className="relative flex-1 min-w-[180px]">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                            <input value={busqueda} onChange={(e) => setBusqueda(e.target.value)} placeholder="SKU o nombre" className="theme-input w-full pl-10 py-2 text-[11px] font-bold" />
                        </div>
                        <select value={almacenId} onChange={(e) => setAlmacenId(e.target.value)} className="theme-input px-3 py-2 text-[11px] font-bold">
                            <option value="">Todos los almacenes</option>
                            {almacenes.map((a) => <option key={a.id} value={a.id}>{a.codigo} — {a.nombre}</option>)}
                        </select>
                        <input value={periodo} onChange={(e) => setPeriodo(e.target.value)} placeholder="YYYY-MM" className="theme-input px-3 py-2 text-[11px] font-bold w-28" />
                        <button type="submit" className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}>Filtrar</button>
                    </form>
                </header>

                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left min-w-[800px]">
                            <thead>
                                <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                    <th className="px-4 py-4">Periodo</th>
                                    <th className="px-4 py-4">Producto</th>
                                    <th className="px-4 py-4">Almacén</th>
                                    <th className="px-4 py-4 text-right">Cantidad</th>
                                    <th className="px-4 py-4 text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                {lista.length === 0 ? (
                                    <tr><td colSpan={5} className="px-4 py-16 text-center theme-text-muted text-sm font-bold uppercase">Sin ventas cargadas</td></tr>
                                ) : lista.map((v) => (
                                    <tr key={v.id} className="border-b theme-border">
                                        <td className="px-4 py-3 text-[11px] font-bold">{v.periodo}</td>
                                        <td className="px-4 py-3">
                                            <span className="font-black text-sm block">{v.producto?.descripcion}</span>
                                            <span className="text-[10px] theme-text-muted">SKU: {v.producto?.sku}</span>
                                        </td>
                                        <td className="px-4 py-3 text-[11px] font-bold">{v.almacen?.codigo || v.almacen?.nombre}</td>
                                        <td className="px-4 py-3 text-right text-[11px] font-bold">{v.cantidad_vendida ?? '—'}</td>
                                        <td className="px-4 py-3 text-right text-[11px] font-bold">{Number(v.monto_venta).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {lista.length > 0 && <GeliaPaginacion paginator={ventas} onIrAPagina={(p) => aplicar({ page: p })} embedded />}
                </div>
            </div>

            {showWizard && (
                <WizardImportacionCatalogo
                    config={IMPORTACION_CATALOGOS.ventas}
                    onClose={() => setShowWizard(false)}
                />
            )}
        </AppLayout>
    );
}
