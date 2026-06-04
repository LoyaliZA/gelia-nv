import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { PackageSearch, Upload, Search, Database } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import { geliaCardClass, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';
import WizardImportacion from './Partials/WizardImportacion';

export default function Index({ auth, productos, almacenes, filtros }) {
    const [busqueda, setBusqueda] = useState('');
    const [almacenId, setAlmacenId] = useState(filtros?.almacen_id || '');
    const [showWizard, setShowWizard] = useState(false);

    const lista = productos?.data || [];

    const handleFiltroAlmacen = (e) => {
        const value = e.target.value;
        setAlmacenId(value);
        router.get(route('admin.catalogo-maestro.index'), { almacen_id: value }, {
            preserveState: true,
            replace: true
        });
    };

    const irAPagina = (pagina) => {
        router.get(route('admin.catalogo-maestro.index'), { page: pagina, almacen_id: almacenId }, {
            preserveState: true,
        });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Catálogo Maestro de Productos" />

            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6">
                <header className={geliaCardClass('p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4')}>
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>
                                Catálogo Maestro
                            </p>
                        </div>
                        <h1 className="text-2xl sm:text-3xl font-black italic uppercase theme-text-main flex items-center gap-3">
                            <Database className="w-8 h-8" style={{ color: 'var(--color-primario)' }} />
                            Productos, Costos y Precios
                        </h1>
                        <p className="theme-text-muted text-[10px] font-bold uppercase tracking-widest mt-2">
                            Repositorio centralizado de inventarios externos.
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                        <select 
                            value={almacenId} 
                            onChange={handleFiltroAlmacen}
                            className="theme-input px-4 py-2 text-[11px] font-bold uppercase"
                        >
                            <option value="">Todos los almacenes</option>
                            {almacenes.map(a => (
                                <option key={a.id} value={a.id}>{a.codigo} - {a.nombre}</option>
                            ))}
                        </select>
                        <button
                            onClick={() => setShowWizard(true)}
                            className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact shrink-0`}
                        >
                            <Upload className="w-4 h-4" /> Importar Inventario
                        </button>
                    </div>
                </header>

                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left min-w-[800px]">
                            <thead>
                                <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                    <th className="px-4 py-4">SKU / Producto</th>
                                    <th className="px-4 py-4">Almacén</th>
                                    <th className="px-4 py-4">Categoría</th>
                                    <th className="px-4 py-4 text-right">Existencia</th>
                                    <th className="px-4 py-4 text-right">Costo</th>
                                    <th className="px-4 py-4 text-right">Precio Venta</th>
                                </tr>
                            </thead>
                            <tbody>
                                {lista.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-16 text-center">
                                            <PackageSearch className="w-10 h-10 theme-text-muted mx-auto mb-3 opacity-50" />
                                            <p className="font-black italic uppercase theme-text-main text-sm">Sin registros</p>
                                            <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase tracking-widest">
                                                Importa un archivo de inventario para comenzar
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    lista.map((producto) => (
                                        <tr key={producto.id} className={`border-b theme-border hover:bg-black/5 dark:hover:bg-white/5 ${!producto.activo ? 'opacity-50 grayscale' : ''}`}>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col">
                                                    <span className="font-black text-sm theme-text-main line-clamp-1">{producto.descripcion}</span>
                                                    <span className="text-[10px] font-bold theme-text-muted">SKU: {producto.sku} {!producto.activo && '(Inactivo)'}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-[11px] font-bold theme-text-muted uppercase">
                                                {producto.almacen?.nombre || 'N/A'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="px-2 py-1 bg-black/5 dark:bg-white/5 rounded-md text-[10px] font-bold uppercase theme-text-main">
                                                    {producto.categoria?.nombre || 'N/A'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right text-[11px] font-bold theme-text-main">
                                                {producto.existencia}
                                            </td>
                                            <td className="px-4 py-3 text-right text-[11px] font-bold text-red-500">
                                                ${parseFloat(producto.costo).toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3 text-right text-[11px] font-bold text-green-500">
                                                ${parseFloat(producto.precio_venta).toFixed(2)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    {lista.length > 0 && (
                        <GeliaPaginacion paginator={productos} onIrAPagina={irAPagina} embedded />
                    )}
                </div>
            </div>

            {showWizard && (
                <WizardImportacion
                    almacenes={almacenes}
                    onClose={() => setShowWizard(false)}
                />
            )}
        </AppLayout>
    );
}
