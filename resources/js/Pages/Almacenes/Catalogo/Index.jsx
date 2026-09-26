import React from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import TablaAlmacenes from '@/Pages/Admin/Partials/Catalogos/TablaAlmacenes';
import { geliaCardClass } from '@/utils/geliaTheme';

export default function Index({
    auth,
    sucursales,
    almacenes,
    tipos_almacen,
    filtros,
    sin_sucursales_operables,
    puede_gestionar,
}) {
    const onSucursal = (sucursalId) => {
        router.get(route('almacenes.catalogo.index'), { sucursal_id: sucursalId || undefined }, { preserveState: true });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Almacenes por sucursal" />
            <div className="max-w-6xl mx-auto p-4 md:p-8 space-y-4">
                {sin_sucursales_operables ? (
                    <div className={geliaCardClass('p-8 text-center')}>
                        <p className="text-sm font-bold theme-text-main">Sin sucursales operables asignadas a tu usuario.</p>
                        <p className="text-[11px] font-bold theme-text-muted mt-2">Solicita acceso a una sucursal para gestionar almacenes en este módulo.</p>
                    </div>
                ) : (
                    <div className={geliaCardClass('overflow-hidden')}>
                        <div className="p-4 border-b theme-border flex flex-wrap gap-3 items-center">
                            <label className="text-[10px] font-black uppercase theme-text-muted">Sucursal</label>
                            <select
                                value={filtros?.sucursal_id || ''}
                                onChange={(e) => onSucursal(e.target.value)}
                                className="theme-input px-3 py-2 text-[11px] font-bold"
                            >
                                <option value="">Todas mis sucursales</option>
                                {sucursales.map((s) => (
                                    <option key={s.id} value={s.id}>{s.nombre}</option>
                                ))}
                            </select>
                        </div>
                        <TablaAlmacenes
                            datos={almacenes}
                            sucursales={sucursales}
                            tipos_almacen={tipos_almacen}
                            mostrarImportar={false}
                            mostrarEliminar={false}
                            puedeGestionar={puede_gestionar}
                            rutas={{
                                store: 'almacenes.catalogo.store',
                                update: 'almacenes.catalogo.update',
                            }}
                            sucursalIdPorDefecto={filtros?.sucursal_id || ''}
                        />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
