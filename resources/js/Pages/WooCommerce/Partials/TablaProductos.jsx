import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { CloudDownload, Pencil, Info } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import GeliaLoader from '../../../Components/GeliaLoader';
import ModalEditarPrecio from './ModalEditarPrecio';

export default function TablaProductos({ productos, filters, permisos, configuracion }) {
    const [consultandoId, setConsultandoId] = useState(null);
    const [mensaje, setMensaje] = useState(null);
    const [mensajeTipo, setMensajeTipo] = useState('success');
    const [productoEditar, setProductoEditar] = useState(null);

    const puedeSincronizar = permisos?.sincronizar;
    const credencialesOk = configuracion?.credenciales_configuradas;

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const handleSearch = (e) => {
        e.preventDefault();
        const q = e.target.search.value;
        router.get(route('woocommerce.index'), { ...filters, search: q }, { preserveState: true });
    };

    const mostrarMensaje = (texto, tipo = 'success') => {
        setMensaje(texto);
        setMensajeTipo(tipo);
        setTimeout(() => setMensaje(null), 5000);
    };

    const consultarPrecio = async (producto) => {
        if (!credencialesOk) {
            mostrarMensaje('Configura las credenciales REST de WooCommerce antes de consultar.', 'error');
            return;
        }

        setConsultandoId(producto.id);
        setMensaje(null);

        try {
            const response = await fetch(route('woocommerce.productos.consultar', producto.id), {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Error al consultar la API.');
            }

            mostrarMensaje(data.message || 'Precio actualizado desde WooCommerce.');
            router.reload({ only: ['productos'] });
        } catch (err) {
            mostrarMensaje(err.message, 'error');
        } finally {
            setConsultandoId(null);
        }
    };

    return (
        <div className={`${geliaCardClass()} p-6 md:p-8 relative`}>
            <GeliaLoader
                isVisible={consultandoId !== null}
                message="Consultando precio en WooCommerce_"
            />

            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                <h2 className="text-lg font-black uppercase theme-text-main">Inventario Local ({productos.total})</h2>
                <form onSubmit={handleSearch} className="flex gap-2 w-full md:w-auto">
                    <input
                        name="search"
                        defaultValue={filters.search || ''}
                        placeholder="Buscar SKU o nombre..."
                        className="flex-1 md:w-64 px-4 py-2 text-sm theme-surface border theme-border rounded-xl theme-text-main"
                    />
                    <button
                        type="submit"
                        className="px-4 py-2 rounded-xl text-[10px] font-black uppercase text-white"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Buscar
                    </button>
                </form>
            </div>

            {puedeSincronizar && !credencialesOk && (
                <div className="mb-4 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-600 text-xs font-bold flex items-center gap-2">
                    <Info className="w-4 h-4 shrink-0" />
                    Configura URL y credenciales REST para usar consulta y edición individual.
                </div>
            )}

            {mensaje && (
                <div className={`mb-4 p-3 rounded-xl text-xs font-bold flex items-center gap-2 ${
                    mensajeTipo === 'error'
                        ? 'bg-red-500/10 border border-red-500/20 text-red-500'
                        : 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600'
                }`}>
                    {mensaje}
                </div>
            )}

            <div className="overflow-x-auto rounded-2xl border theme-border">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="text-[10px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                            <th className="p-3 text-left">SKU</th>
                            <th className="p-3 text-left">Nombre</th>
                            <th className="p-3 text-right">Normal</th>
                            <th className="p-3 text-right">Rebaja</th>
                            <th className="p-3 text-center">Tipo</th>
                            {puedeSincronizar && <th className="p-3 text-center">Acciones</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {productos.data.map((p) => (
                            <tr key={p.id} className="border-b theme-border/50 hover:bg-black/5 dark:hover:bg-white/5">
                                <td className="p-3 font-bold theme-text-main">{p.sku}</td>
                                <td className="p-3 theme-text-muted truncate max-w-[200px]">{p.nombre}</td>
                                <td className="p-3 text-right theme-text-main">${Number(p.precio_normal ?? 0).toFixed(2)}</td>
                                <td className="p-3 text-right text-emerald-500">${Number(p.precio_rebajado ?? 0).toFixed(2)}</td>
                                <td className="p-3 text-center">
                                    <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase theme-element">{p.tipo}</span>
                                </td>
                                {puedeSincronizar && (
                                    <td className="p-3">
                                        <div className="flex items-center justify-center gap-1.5">
                                            <button
                                                type="button"
                                                onClick={() => consultarPrecio(p)}
                                                disabled={consultandoId === p.id}
                                                title="Consultar precio en WooCommerce"
                                                className="p-1.5 rounded-lg border border-blue-500/30 bg-blue-500/10 text-blue-500 hover:bg-blue-600 hover:text-white transition-all disabled:opacity-50"
                                            >
                                                <CloudDownload className="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setProductoEditar(p)}
                                                title="Editar y subir precio a WooCommerce"
                                                className="p-1.5 rounded-lg border border-purple-500/30 bg-purple-500/10 text-purple-500 hover:bg-purple-600 hover:text-white transition-all"
                                            >
                                                <Pencil className="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {productos.last_page > 1 && (
                <div className="flex justify-center gap-2 mt-4">
                    {productos.links?.map((link, i) => (
                        link.url ? (
                            <button
                                key={i}
                                onClick={() => router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`px-3 py-1 rounded text-xs font-bold ${link.active ? 'text-white' : 'theme-text-muted border theme-border'}`}
                                style={link.active ? { backgroundColor: 'var(--color-primario)' } : {}}
                            />
                        ) : null
                    ))}
                </div>
            )}

            {productoEditar && (
                <ModalEditarPrecio
                    producto={productoEditar}
                    onClose={() => setProductoEditar(null)}
                />
            )}
        </div>
    );
}
