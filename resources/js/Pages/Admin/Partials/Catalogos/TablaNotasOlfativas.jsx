import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Flower2, Edit2, Trash2, Plus, Save } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';

const formVacio = () => ({ nombre: '', descripcion: '', estado: true });

export default function TablaNotasOlfativas({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const { data, setData, post, put, processing, reset, errors } = useForm(formVacio());

    const handleSubmit = (e) => {
        e.preventDefault();
        const opts = { onSuccess: () => { setModalAbierto(false); reset(); } };
        if (itemActual) {
            put(route('admin.catalogos.notas_olfativas.update', itemActual.id), opts);
        } else {
            post(route('admin.catalogos.notas_olfativas.store'), opts);
        }
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando nota_" />
            <div className="p-6 border-b theme-border flex justify-between flex-wrap gap-4">
                <div>
                    <h2 className="text-xl font-black italic uppercase m-0 flex items-center gap-2 theme-text-main"><Flower2 className="w-5 h-5" /> Notas Olfativas_</h2>
                    <p className="text-[10px] font-bold theme-text-muted mt-1 mb-0 uppercase tracking-wide">
                        Catálogo para categorías con extensión perfumería.
                    </p>
                </div>
                <button type="button" onClick={() => { setItemActual(null); reset(); setData(formVacio()); setModalAbierto(true); }} className="px-6 py-3 rounded-2xl text-white font-black uppercase text-xs" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Plus className="w-4 h-4 inline" /> Nuevo
                </button>
            </div>
            <table className="w-full">
                <tbody>
                    {datos.map((item) => (
                        <tr key={item.id} className="border-b theme-border">
                            <td className="px-6 py-4 font-black theme-text-main">{item.nombre}</td>
                            <td className="px-6 py-4 text-xs theme-text-muted">{item.descripcion || '—'}</td>
                            <td className="px-6 py-4 text-[10px] font-bold">{item.estado ? 'Activo' : 'Inactivo'}</td>
                            <td className="px-6 py-4 text-right">
                                <button type="button" onClick={() => { setItemActual(item); setData({ nombre: item.nombre, descripcion: item.descripcion || '', estado: !!item.estado }); setModalAbierto(true); }} className="p-2 theme-element border theme-border rounded-xl mr-2"><Edit2 className="w-4 h-4" /></button>
                                <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2 theme-element border theme-border rounded-xl"><Trash2 className="w-4 h-4" /></button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {modalAbierto && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-md p-8 modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-xl font-black italic uppercase theme-text-main mb-6">{itemActual ? 'Editar' : 'Nueva'} Nota</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <input required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className="theme-input w-full px-4 py-3 font-bold" placeholder="Nombre (Bergamota)" />
                            {errors.nombre && <p className="text-xs text-red-500">{errors.nombre}</p>}
                            <textarea value={data.descripcion || ''} onChange={(e) => setData('descripcion', e.target.value)} className="theme-input w-full px-4 py-3 font-bold min-h-[80px]" placeholder="Descripción opcional" />
                            <label className="flex gap-2 items-center"><input type="checkbox" checked={!!data.estado} onChange={(e) => setData('estado', e.target.checked)} /><span className="font-bold text-sm">Activo</span></label>
                            <button type="submit" className="w-full py-3 text-white rounded-xl font-black uppercase" style={{ backgroundColor: 'var(--color-primario)' }}><Save className="w-4 h-4 inline" /> Guardar</button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalEliminar(false)}>
                    <div className={`${THEME_MODAL_SHELL} p-8 text-center modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <p className="theme-text-main mb-4">¿Desactivar «{itemActual?.nombre}»?</p>
                        <button type="button" onClick={() => router.delete(route('admin.catalogos.notas_olfativas.destroy', itemActual.id), { onSuccess: () => setModalEliminar(false) })} className="px-6 py-3 bg-red-600 text-white rounded-xl font-black uppercase text-[10px]">Confirmar</button>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
