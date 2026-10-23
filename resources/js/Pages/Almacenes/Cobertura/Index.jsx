import React, { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Layers, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import EnlacesContextoAlmacen from '@/Components/Almacenes/EnlacesContextoAlmacen';
import { geliaCardClass, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';
import { hrefFichaProducto, puedeVerFichaProducto } from '@/utils/fichaProducto';

function textoCantidad(fila) {
    if (!fila.tiene_inventario) return 'Sin registro de inventario';
    if (!fila.tiene_cantidad) return 'Existencia sin informar';
    return fila.existencia;
}

export default function Index({ auth, filas, sucursales, almacenes, filtros, sin_sucursales_operables }) {
    const [sucursalId, setSucursalId] = useState(filtros?.sucursal_id || '');
    const [almacenId, setAlmacenId] = useState(filtros?.almacen_id || '');
    const [busqueda, setBusqueda] = useState(filtros?.q || '');
    const [seleccion, setSeleccion] = useState([]);
    const lista = filas?.data || [];
    const verFicha = puedeVerFichaProducto(auth);
    const puedeAsignar = auth?.user?.permissions?.includes('almacenes.inventarios.gestionar')
        || auth?.user?.permissions?.includes('catalogos.gestionar')
        || auth?.user?.roles?.includes('Super Admin');

    const almacenesFiltrados = useMemo(() => {
        if (!sucursalId) return almacenes;
        return almacenes.filter((a) => String(a.sucursal_id) === String(sucursalId));
    }, [almacenes, sucursalId]);

    const consultar = (params) => {
        router.get(route('almacenes.cobertura.index'), {
            sucursal_id: params.sucursal_id || undefined,
            almacen_id: params.almacen_id || undefined,
            q: params.q || undefined,
            asignacion: params.asignacion || undefined,
            costo: params.costo || undefined,
            activo: params.activo || undefined,
            page: params.page,
        }, { preserveState: true });
    };

    const paramsActuales = (extra = {}) => ({
        sucursal_id: sucursalId,
        almacen_id: almacenId,
        q: busqueda,
        asignacion: filtros?.asignacion,
        costo: filtros?.costo,
        activo: filtros?.activo,
        ...extra,
    });

    const alCambiarSucursal = (valor) => {
        setSucursalId(valor);
        setAlmacenId('');
        setSeleccion([]);
        consultar(paramsActuales({ sucursal_id: valor, almacen_id: '', page: 1 }));
    };

    const alCambiarAlmacen = (valor) => {
        setAlmacenId(valor);
        setSeleccion([]);
        consultar(paramsActuales({ almacen_id: valor, page: 1 }));
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
            <Head title="Productos por almacén" />
            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6">
                <header className={geliaCardClass('p-6 space-y-4')}>
                    <h1 className="text-2xl font-black italic uppercase theme-text-main flex items-center gap-3">
                        <Layers className="w-7 h-7" style={{ color: 'var(--color-primario)' }} />
                        Productos por almacén
                    </h1>
                    <p className="text-[11px] font-bold theme-text-muted">Cruce de productos con asignación, costos y cantidades de referencia. Elige un almacén para ver faltantes.</p>
                    {!sin_sucursales_operables && (
                        <EnlacesContextoAlmacen
                            auth={auth}
                            sucursalId={sucursalId}
                            almacenId={almacenId}
                            q={busqueda}
                            actual="cobertura"
                        />
                    )}
                </header>
                {sin_sucursales_operables ? (
                    <div className={geliaCardClass('p-8 text-center')}>
                        <p className="text-sm font-bold theme-text-main">Sin sucursales operables asignadas a tu usuario.</p>
                        <p className="text-[11px] font-bold theme-text-muted mt-2">Solicita acceso a una sucursal para consultar productos por almacén en este módulo.</p>
                    </div>
                ) : (
                    <div className={geliaCardClass('overflow-x-auto')}>
                        <div className="p-4 border-b theme-border space-y-3">
                            <div className="flex flex-wrap gap-2">
                                <select value={sucursalId} onChange={(e) => alCambiarSucursal(e.target.value)} className="theme-input px-3 py-2 text-[11px] font-bold">
                                    <option value="">Todas las sucursales</option>
                                    {sucursales.map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                                </select>
                                <select value={almacenId} onChange={(e) => alCambiarAlmacen(e.target.value)} className="theme-input px-3 py-2 text-[11px] font-bold">
                                    <option value="">Todos los almacenes</option>
                                    {almacenesFiltrados.map((a) => <option key={a.id} value={a.id}>{a.codigo} — {a.nombre}</option>)}
                                </select>
                                <input value={busqueda} onChange={(e) => setBusqueda(e.target.value)} placeholder="Buscar…" className="theme-input px-3 py-2 text-[11px] font-bold flex-1 min-w-[160px]" />
                                <button type="button" onClick={() => consultar(paramsActuales({ page: 1 }))} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}><Search className="w-4 h-4" /></button>
                                <button type="button" onClick={() => consultar(paramsActuales({ asignacion: 'sin', page: 1 }))} className="px-3 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl">Sin asignar</button>
                                <button type="button" onClick={() => consultar(paramsActuales({ costo: 'sin', page: 1 }))} className="px-3 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl">Sin costo</button>
                            </div>
                            {!almacenId && (
                                <p className="text-[11px] font-bold theme-text-muted">Elige un almacén para evaluar asignación, costo y existencia. Sin esa elección, los indicadores no corresponden a un almacén.</p>
                            )}
                            {almacenId && (
                                <p className="text-[11px] font-bold theme-text-muted">La ubicación de asignación que se muestra aquí es independiente de la ubicación de inventario. Esta pantalla no la edita.</p>
                            )}
                            {almacenId && puedeAsignar && seleccion.length > 0 && (
                                <button type="button" onClick={asignarSeleccion} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}>
                                    Asignar {seleccion.length} al almacén
                                </button>
                            )}
                        </div>
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
                                            {verFicha ? (
                                                <Link
                                                    href={hrefFichaProducto(f.id, {
                                                        desde: 'cobertura',
                                                        sucursal_id: sucursalId || undefined,
                                                        almacen_id: almacenId || undefined,
                                                        q: busqueda || undefined,
                                                        asignacion: filtros?.asignacion,
                                                        costo: filtros?.costo,
                                                        activo: filtros?.activo,
                                                        page: filas?.current_page,
                                                    })}
                                                    className="block theme-text-main"
                                                >
                                                    {f.descripcion}
                                                </Link>
                                            ) : (
                                                <span className="block">{f.descripcion}</span>
                                            )}
                                            <span className="text-[10px] theme-text-muted">SKU {f.sku}</span>
                                        </td>
                                        <td className="px-4 py-2">{almacenId ? (f.asignado ? `Asignado${f.ubicacion ? ` · Ubicación de asignación ${f.ubicacion}` : ''}` : 'Sin asignar') : '—'}</td>
                                        <td className="px-4 py-2">{almacenId && f.costo != null ? Number(f.costo).toFixed(2) : (almacenId ? 'Sin costo' : '—')}</td>
                                        <td className="px-4 py-2">{almacenId ? textoCantidad(f) : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <GeliaPaginacion
                            paginator={filas}
                            onIrAPagina={(pagina) => consultar(paramsActuales({ page: pagina }))}
                            embedded
                        />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
