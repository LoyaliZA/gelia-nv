import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Puzzle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';

export default function TablaExtensionesProducto({ datos = [] }) {
    const [processing, setProcessing] = useState(false);

    const toggle = (item, habilitada) => {
        setProcessing(true);
        router.put(route('admin.catalogos.extensiones_producto.update', item.id), { habilitada }, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Actualizando extensión_" />
            <div className="p-6 border-b theme-border">
                <h2 className="text-xl font-black italic uppercase m-0 flex items-center gap-2 theme-text-main">
                    <Puzzle className="w-5 h-5" /> Extensiones Producto_
                </h2>
                <p className="text-[10px] font-bold theme-text-muted mt-1 mb-0 uppercase tracking-wide">
                    Capacidades especializadas del código. Asigna cada una a categorías (no se crean desde aquí).
                </p>
            </div>
            <table className="w-full">
                <thead>
                    <tr className="border-b theme-border text-left text-[10px] font-black uppercase theme-text-muted">
                        <th className="px-6 py-3">Nombre</th>
                        <th className="px-6 py-3">Código</th>
                        <th className="px-6 py-3">Categorías</th>
                        <th className="px-6 py-3">Global</th>
                    </tr>
                </thead>
                <tbody>
                    {datos.map((item) => (
                        <tr key={item.id} className="border-b theme-border">
                            <td className="px-6 py-4">
                                <p className="font-black theme-text-main m-0">{item.nombre}</p>
                                {item.descripcion && <p className="text-xs theme-text-muted m-0 mt-1">{item.descripcion}</p>}
                            </td>
                            <td className="px-6 py-4 text-xs font-bold theme-text-muted">{item.codigo}</td>
                            <td className="px-6 py-4 text-xs font-bold theme-text-main">{item.categorias_asignadas_count ?? 0}</td>
                            <td className="px-6 py-4">
                                <label className="flex gap-2 items-center text-sm font-bold">
                                    <input
                                        type="checkbox"
                                        checked={!!item.habilitada}
                                        onChange={(e) => toggle(item, e.target.checked)}
                                    />
                                    {item.habilitada ? 'Habilitada' : 'Deshabilitada'}
                                </label>
                            </td>
                        </tr>
                    ))}
                    {datos.length === 0 && (
                        <tr><td colSpan={4} className="px-6 py-8 text-sm theme-text-muted">Sin extensiones registradas.</td></tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
