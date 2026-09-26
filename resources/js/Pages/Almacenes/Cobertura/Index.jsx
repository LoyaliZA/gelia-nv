import React, { useState, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import { Layers, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import { geliaCardClass, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';

export default function Index({ auth, filas, sucursales, almacenes, filtros }) {
    const [sucursalId, setSucursalId] = useState(filtros?.sucursal_id || '');
    const [almacenId, setAlmacenId] = useState(filtros?.almacen_id || '');
    const [busqueda, setBusqueda] = useState(filtros?.q || '');
    const [seleccion, setSeleccion] = useState([]);
    const lista = filas?.data || [];

    const almacenesFiltrados = useMemo(() => {
        if (!sucursalId) return almacenes;
        return almacenes.filter((a) => String(a.sucursal_id) === String(sucursalId));
    }, [almacenes, sucursalId]);

    const aplicar = (extra = {}) => {
        router.get(route('almacenes.cobertura.index'), {
            sucursal_id: sucursalId,
            almacen_id: almacenId,
            q: busqueda,
            asignacion: filtros?.asignacion,
            costo: filtros?.costo,
            activo: filtros?.activo,
            ...extra,
        }, { preserveState: true });
    };

    const toggle = (id) => {
        setSeleccion((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const asignarSeleccion = () => {
        if (!almacenId || seleccion.length === 0) return;
        router.post(route('almacenes.cobertura.asignar'), {
            almacen_id: almacenId,
            producto_ids: seleccion,
        }, { preserveScroll: true, onSuccess: () => setSeleccion([]) });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Cobertura por almacén" />
            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6">
                <header className={geliaCardClass('p-6 space-y-4')}>
                    <h1 className="text-2xl font-black italic uppercase theme-text-main flex items-center gap-3">
                        <Layers className="w-7 h-7" style={{ color: 'var(--color-primario)' }} />
                        Cobertura por almacén
                    </h1>
                    <p className="text-[11px] font-bold theme-text-muted">Cruce de productos con asignación, costos y cantidades de referencia. Selecciona un almacén para ver faltantes.</p>
                    <div className="flex flex-wrap gap-2">
                        <select value={sucursalId} onChange={(e) => { setSucursalId(e.target.value); setAlmacenId(''); }} className="theme-input px-3 py-2 text-[11px] font-bold">
                            <option value="">Todas las sucursales</option>
                            {sucursales.map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                        </select>
                        <select value={almacenId} onChange={(e) => setAlmacenId(e.target.value)} className="theme-input px-3 py-2 text-[11px] font-bold">
                            <option value="">Todos los almacenes</option>
                            {almacenesFiltrados.map((a) => <option key={a.id} value={a.id}>{a.codigo} — {a.nombre}</option>)}
                        </select>
                        <input value={busqueda} onChange={(e) => setBusqueda(e.target.value)} placeholder="Buscar…" className="theme-input px-3 py-2 text-[11px] font-bold flex-1 min-w-[160px]" />
                        <button type="button" onClick={() => aplicar({ page: 1 })} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}><Search className="w-4 h-4" /></button>
                        <button type="button" onClick={() => aplicar({ asignacion: 'sin', page: 1 })} className="px-3 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl">Sin asignar</button>
                        <button type="button" onClick={() => aplicar({ costo: 'sin', page: 1 })} className="px-3 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl">Sin costo</button>
                    </div>
                    {almacenId && seleccion.length > 0 && (
                        <button type="button" onClick={asignarSeleccion} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}>
                            Asignar {seleccion.length} al almacén
                        </button>
                    )}
                </header>
                <div className={geliaCardClass('overflow-x-auto')}>
                    <table className="w-full text-left min-w-[900px]">
                        <thead>
                            <tr className="border-b theme-border text-[10px] font-black uppercase theme-text-muted">
                                <th className="px-4 py-3">Sel.</th>
                                <th className="px-4 py-3">Producto</th>
                                <th className="px-4 py-3">Asignación</th>
                                <th className="px-4 py-3">Costo</th>
                                <th className="px-4 py-3">Cantidad ref.</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lista.map((f) => (
                                <tr key={f.id} className="border-b theme-border text-[11px] font-bold">
                                    <td className="px-4 py-2">
                                        {almacenId && !f.asignado && (
                                            <input type="checkbox" checked={seleccion.includes(f.id)} onChange={() => toggle(f.id)} />
                                        )}
                                    </td>
                                    <td className="px-4 py-2">
                                        <span className="block">{f.descripcion}</span>
                                        <span className="text-[10px] theme-text-muted">SKU {f.sku}</span>
                                    </td>
                                    <td className="px-4 py-2">{almacenId ? (f.asignado ? `Asignado${f.ubicacion ? ` · ${f.ubicacion}` : ''}` : 'Sin asignar') : '—'}</td>
                                    <td className="px-4 py-2">{almacenId && f.costo != null ? Number(f.costo).toFixed(2) : (almacenId ? 'Sin costo' : '—')}</td>
                                    <td className="px-4 py-2">
                                        {almacenId ? (f.tiene_cantidad ? f.existencia : 'Sin dato') : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <GeliaPaginacion links={filas?.links} />
                </div>
            </div>
        </AppLayout>
    );
}
