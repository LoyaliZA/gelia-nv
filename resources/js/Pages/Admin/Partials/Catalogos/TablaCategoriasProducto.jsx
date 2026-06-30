import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Tags, Edit2, Trash2, Plus, Save, AlertTriangle, Upload } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import ModalImportarCatalogo from '@/Components/Catalogos/ModalImportarCatalogo';
import { IMPORTACION_CATALOGOS } from '@/config/importacionCatalogos';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';

export default function TablaCategoriasProducto({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [modalImportar, setModalImportar] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const { data, setData, post, put, processing, reset, errors } = useForm({ nombre: '' });

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual ? route('admin.catalogos.categorias_producto.update', itemActual.id) : route('admin.catalogos.categorias_producto.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando categoría_" />
            <div className="p-6 border-b theme-border flex justify-between flex-wrap gap-4">
                <h2 className="text-xl font-black italic uppercase m-0 flex items-center gap-2 theme-text-main"><Tags className="w-5 h-5" /> Categorías Producto_</h2>
                <div className="flex gap-2">
                    <button type="button" onClick={() => setModalImportar(true)} className="flex items-center gap-2 px-5 py-3 rounded-2xl font-black uppercase text-xs theme-element border theme-border"><Upload className="w-4 h-4" /> Importar</button>
                    <button onClick={() => { setItemActual(null); reset(); setModalAbierto(true); }} className="px-6 py-3 rounded-2xl text-white font-black uppercase text-xs" style={{ backgroundColor: 'var(--color-primario)' }}><Plus className="w-4 h-4 inline" /> Nuevo</button>
                </div>
            </div>
            <table className="w-full">
                <tbody>
                    {datos.map((item) => (
                        <tr key={item.id} className="border-b theme-border">
                            <td className="px-6 py-4 font-black theme-text-main">{item.nombre}</td>
                            <td className="px-6 py-4 text-right">
                                <button onClick={() => { setItemActual(item); setData({ nombre: item.nombre }); setModalAbierto(true); }} className="p-2 theme-element border theme-border rounded-xl mr-2"><Edit2 className="w-4 h-4" /></button>
                                <button onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2 theme-element border theme-border rounded-xl"><Trash2 className="w-4 h-4" /></button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {modalAbierto && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-md p-8 modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-xl font-black italic uppercase theme-text-main mb-6">{itemActual ? 'Editar' : 'Nueva'} Categoría</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <input required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className="theme-input w-full px-4 py-3 font-bold" placeholder="Nombre categoría" />
                            {errors.nombre && <p className="text-xs text-red-500 dark:text-red-400">{errors.nombre}</p>}
                            <button type="submit" className="w-full py-3 text-white rounded-xl font-black uppercase" style={{ backgroundColor: 'var(--color-primario)' }}><Save className="w-4 h-4 inline" /> Guardar</button>
                        </form>
                    </div>
                </div>, document.body
            )}
            {modalEliminar && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalEliminar(false)}>
                    <div className={`${THEME_MODAL_SHELL} p-8 text-center modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <p className="theme-text-main mb-4">¿Eliminar «{itemActual?.nombre}»?</p>
                        <button onClick={() => router.delete(route('admin.catalogos.categorias_producto.destroy', itemActual.id), { onSuccess: () => setModalEliminar(false) })} className="px-6 py-3 bg-red-600 text-white rounded-xl font-black uppercase text-[10px]">Eliminar</button>
                    </div>
                </div>, document.body
            )}
            {modalImportar && <ModalImportarCatalogo config={IMPORTACION_CATALOGOS.categorias_producto} onClose={() => setModalImportar(false)} />}
        </div>
    );
}
