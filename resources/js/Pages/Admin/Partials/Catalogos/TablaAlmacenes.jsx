import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Boxes, Edit2, Trash2, Plus, Save, AlertTriangle, Upload } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import ModalImportarCatalogo from '@/Components/Catalogos/ModalImportarCatalogo';
import { IMPORTACION_CATALOGOS } from '@/config/importacionCatalogos';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';

export default function TablaAlmacenes({ datos = [], sucursales = [], tipos_almacen = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [modalImportar, setModalImportar] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const { data, setData, post, put, processing, reset, errors } = useForm({
        codigo: '', nombre: '', sucursal_id: '', tipo_almacen_id: '', activo: true, visible_en_pedidos: false,
    });

    const abrirNuevo = () => { setItemActual(null); reset(); setModalAbierto(true); };
    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            codigo: item.codigo,
            nombre: item.nombre,
            sucursal_id: item.sucursal_id || '',
            tipo_almacen_id: item.tipo_almacen_id || '',
            activo: item.activo ?? true,
            visible_en_pedidos: item.visible_en_pedidos ?? false,
        });
        setModalAbierto(true);
    };
    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual ? route('admin.catalogos.almacenes.update', itemActual.id) : route('admin.catalogos.almacenes.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando almacén_" />
            <div className="p-6 border-b theme-border flex justify-between flex-wrap gap-4">
                <h2 className="text-xl font-black italic uppercase m-0 flex items-center gap-2 theme-text-main"><Boxes className="w-5 h-5" /> Almacenes_</h2>
                <div className="flex gap-2">
                    <button type="button" onClick={() => setModalImportar(true)} className="flex items-center gap-2 px-5 py-3 rounded-2xl font-black uppercase text-xs theme-element border theme-border"><Upload className="w-4 h-4" /> Importar</button>
                    <button onClick={abrirNuevo} className="px-6 py-3 rounded-2xl text-white font-black uppercase text-xs" style={{ backgroundColor: 'var(--color-primario)' }}><Plus className="w-4 h-4 inline" /> Nuevo</button>
                </div>
            </div>
            <table className="w-full">
                <thead><tr className="border-b theme-border text-[9px] font-black uppercase theme-text-muted"><th className="px-6 py-3 text-left">Código / Nombre</th><th className="px-6 py-3 text-left">Sucursal</th><th className="px-6 py-3 text-left">Tipo</th><th className="px-6 py-3 text-left">Pedidos</th><th className="px-6 py-3 text-right">Acciones</th></tr></thead>
                <tbody>
                    {datos.map((item) => (
                        <tr key={item.id} className="border-b theme-border">
                            <td className="px-6 py-4"><p className="font-black theme-text-main">{item.nombre}</p><p className="text-[9px] theme-text-muted">{item.codigo}</p></td>
                            <td className="px-6 py-4 text-sm font-bold theme-text-main">{item.sucursal?.nombre || '—'}</td>
                            <td className="px-6 py-4 text-sm font-bold theme-text-main">{item.tipo_almacen?.nombre || '—'}</td>
                            <td className="px-6 py-4">
                                <span className={`inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase ${item.visible_en_pedidos ? 'bg-emerald-500/10 text-emerald-600' : 'theme-text-muted'}`}>
                                    {item.visible_en_pedidos ? 'Visible' : 'Oculto'}
                                </span>
                            </td>
                            <td className="px-6 py-4 text-right">
                                <button onClick={() => abrirEditar(item)} className="p-2 theme-element border theme-border rounded-xl mr-2"><Edit2 className="w-4 h-4" /></button>
                                <button onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2 theme-element border theme-border rounded-xl"><Trash2 className="w-4 h-4" /></button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {modalAbierto && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-md p-8 max-h-[90vh] overflow-y-auto modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-xl font-black italic uppercase theme-text-main mb-6">{itemActual ? 'Editar' : 'Nuevo'} Almacén</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <input required value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} placeholder="Código *" className="theme-input w-full px-4 py-3 font-bold" />
                            <input required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Nombre *" className="theme-input w-full px-4 py-3 font-bold" />
                            <select value={data.sucursal_id} onChange={(e) => setData('sucursal_id', e.target.value)} className="theme-input w-full px-4 py-3 font-bold">
                                <option value="">Sucursal</option>
                                {sucursales.map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                            </select>
                            <select value={data.tipo_almacen_id} onChange={(e) => setData('tipo_almacen_id', e.target.value)} className="theme-input w-full px-4 py-3 font-bold">
                                <option value="">Tipo almacén</option>
                                {tipos_almacen.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                            </select>
                            <label className="flex gap-2 items-center"><input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} /><span className="font-bold text-sm theme-text-main">Activo</span></label>
                            <label className="flex gap-2 items-center"><input type="checkbox" checked={data.visible_en_pedidos} onChange={(e) => setData('visible_en_pedidos', e.target.checked)} /><span className="font-bold text-sm theme-text-main">Visible en Gestión de pedidos</span></label>
                            {errors.codigo && <p className="text-xs text-red-500 dark:text-red-400">{errors.codigo}</p>}
                            <button type="submit" className="w-full py-3 text-white rounded-xl font-black uppercase" style={{ backgroundColor: 'var(--color-primario)' }}><Save className="w-4 h-4 inline" /> Guardar</button>
                        </form>
                    </div>
                </div>, document.body
            )}
            {modalEliminar && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalEliminar(false)}>
                    <div className={`${THEME_MODAL_SHELL} p-8 text-center modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <AlertTriangle className="w-10 h-10 text-red-500 mx-auto mb-4" />
                        <p className="theme-text-main mb-4">¿Eliminar «{itemActual?.nombre}»?</p>
                        <button onClick={() => router.delete(route('admin.catalogos.almacenes.destroy', itemActual.id), { onSuccess: () => setModalEliminar(false) })} className="px-6 py-3 bg-red-600 text-white rounded-xl font-black uppercase text-[10px]">Eliminar</button>
                    </div>
                </div>, document.body
            )}
            {modalImportar && <ModalImportarCatalogo config={IMPORTACION_CATALOGOS.almacenes} onClose={() => setModalImportar(false)} />}
        </div>
    );
}
