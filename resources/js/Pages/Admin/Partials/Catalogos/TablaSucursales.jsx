import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Building, Edit2, Trash2, Plus, X, Save, AlertTriangle, Upload } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import ModalImportarCatalogo from '@/Components/Catalogos/ModalImportarCatalogo';
import { IMPORTACION_CATALOGOS } from '@/config/importacionCatalogos';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';

export default function TablaSucursales({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [modalImportar, setModalImportar] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const { data, setData, post, put, processing, reset, errors } = useForm({ codigo: '', nombre: '', activo: true });

    const abrirNuevo = () => { setItemActual(null); reset(); setModalAbierto(true); };
    const abrirEditar = (item) => {
        setItemActual(item);
        setData({ codigo: item.codigo, nombre: item.nombre, activo: item.activo });
        setModalAbierto(true);
    };
    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual ? route('admin.catalogos.sucursales.update', itemActual.id) : route('admin.catalogos.sucursales.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando sucursal_" />
            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between flex-wrap gap-4">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <Building className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase m-0">Sucursales_</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase mt-0.5">{datos.length} registros</p>
                    </div>
                </div>
                <div className="flex gap-2">
                    <button type="button" onClick={() => setModalImportar(true)} className="flex items-center gap-2 px-5 py-3 rounded-2xl font-black uppercase text-xs theme-element border theme-border">
                        <Upload className="w-4 h-4" /> Importar
                    </button>
                    <button onClick={abrirNuevo} className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-xs text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                        <Plus className="w-4 h-4" /> Nuevo
                    </button>
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-6 py-4 text-left text-[9px] font-black uppercase theme-text-muted">Código / Nombre</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black uppercase theme-text-muted">Status</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black uppercase theme-text-muted">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border">
                                <td className="px-6 py-5">
                                    <p className="text-sm font-black uppercase theme-text-main">{item.nombre}</p>
                                    <p className="text-[9px] theme-text-muted font-bold">{item.codigo}</p>
                                </td>
                                <td className="px-6 py-5 text-[10px] font-bold theme-text-main">{item.activo ? 'Activo' : 'Inactivo'}</td>
                                <td className="px-6 py-5 text-right">
                                    <button onClick={() => abrirEditar(item)} className="p-2 theme-element border theme-border rounded-xl mr-2"><Edit2 className="w-4 h-4" /></button>
                                    <button onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2 theme-element border theme-border rounded-xl"><Trash2 className="w-4 h-4" /></button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {modalAbierto && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-md p-8 modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-xl font-black italic uppercase theme-text-main mb-6">{itemActual ? 'Editar' : 'Nueva'} Sucursal</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Código *</label>
                                <input required value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} className="theme-input w-full mt-1 px-4 py-3 font-bold" />
                                {errors.codigo && <p className="text-xs text-red-500 dark:text-red-400">{errors.codigo}</p>}
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Nombre *</label>
                                <input required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className="theme-input w-full mt-1 px-4 py-3 font-bold" />
                            </div>
                            <label className="flex items-center gap-2">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                <span className="text-sm font-bold theme-text-main">Activo</span>
                            </label>
                            <button type="submit" className="w-full py-3 text-white rounded-xl font-black uppercase text-[11px]" style={{ backgroundColor: 'var(--color-primario)' }}><Save className="w-4 h-4 inline mr-2" />Guardar</button>
                        </form>
                    </div>
                </div>, document.body
            )}
            {modalEliminar && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalEliminar(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-sm p-8 text-center modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <AlertTriangle className="w-12 h-12 text-red-500 mx-auto mb-4" />
                        <p className="mb-6 theme-text-main">¿Eliminar «{itemActual?.nombre}»?</p>
                        <div className="flex gap-3">
                            <button onClick={() => setModalEliminar(false)} className="flex-1 py-3 border theme-border rounded-xl font-black text-[10px] uppercase theme-text-main">Cancelar</button>
                            <button onClick={() => router.delete(route('admin.catalogos.sucursales.destroy', itemActual.id), { onSuccess: () => setModalEliminar(false) })} className="flex-1 py-3 bg-red-600 text-white rounded-xl font-black text-[10px] uppercase">Eliminar</button>
                        </div>
                    </div>
                </div>, document.body
            )}
            {modalImportar && (
                <ModalImportarCatalogo config={IMPORTACION_CATALOGOS.sucursales} onClose={() => setModalImportar(false)} />
            )}
        </div>
    );
}
