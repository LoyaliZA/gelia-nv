import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Tags, Edit2, Trash2, Plus, Save, Upload, X } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import ModalImportarCatalogo from '@/Components/Catalogos/ModalImportarCatalogo';
import { IMPORTACION_CATALOGOS } from '@/config/importacionCatalogos';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';

const formVacio = (extensionesDisponibles = []) => ({
    nombre: '',
    atributo_ids: [],
    extensiones: extensionesDisponibles.map((e) => ({
        codigo: e.codigo,
        habilitada: false,
        heredable: true,
    })),
});

export default function TablaCategoriasProducto({ datos = [], atributos = [], extensiones = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [modalImportar, setModalImportar] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const { data, setData, post, put, processing, reset, errors } = useForm(formVacio(extensiones));

    const abrirEditar = (item) => {
        setItemActual(item);
        const ids = (item.categoria_atributos || item.categoriaAtributos || [])
            .map((ca) => ca.atributo_id)
            .filter(Boolean);
        const asignadas = (item.categoria_extensiones || item.categoriaExtensiones || []);
        setData({
            nombre: item.nombre || '',
            atributo_ids: ids,
            extensiones: extensiones.map((e) => {
                const row = asignadas.find((a) => (a.extension?.codigo || a.extension_codigo) === e.codigo);
                return {
                    codigo: e.codigo,
                    habilitada: row ? !!row.habilitada : false,
                    heredable: row ? row.heredable !== false : true,
                };
            }),
        });
        setModalAbierto(true);
    };

    const toggleAtributo = (id) => {
        const n = Number(id);
        const set = new Set((data.atributo_ids || []).map(Number));
        if (set.has(n)) set.delete(n);
        else set.add(n);
        setData('atributo_ids', Array.from(set));
    };

    const patchExtension = (codigo, patch) => {
        setData(
            'extensiones',
            (data.extensiones || []).map((e) => (e.codigo === codigo ? { ...e, ...patch } : e))
        );
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const opts = {
            onSuccess: () => { setModalAbierto(false); reset(); },
        };
        const payloadExt = (data.extensiones || []).filter((e) => e.habilitada);
        const transform = (form) => ({ ...form, extensiones: payloadExt });
        if (itemActual) {
            put(route('admin.catalogos.categorias_producto.update', itemActual.id), { ...opts, transform });
        } else {
            post(route('admin.catalogos.categorias_producto.store'), { ...opts, transform });
        }
    };

    const conteoAttrs = (item) => (item.categoria_atributos || item.categoriaAtributos || []).length;
    const conteoExt = (item) => (item.categoria_extensiones || item.categoriaExtensiones || []).filter((x) => x.habilitada !== false).length;

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando categoría_" />
            <div className="p-6 border-b theme-border flex justify-between flex-wrap gap-4">
                <div>
                    <h2 className="text-xl font-black italic uppercase m-0 flex items-center gap-2 theme-text-main"><Tags className="w-5 h-5" /> Categorías Producto_</h2>
                    <p className="text-[10px] font-bold theme-text-muted mt-1 mb-0 uppercase tracking-wide">
                        Atributos y extensiones (p. ej. Perfumería) se asignan aquí; no son universales.
                    </p>
                </div>
                <div className="flex gap-2">
                    <button type="button" onClick={() => setModalImportar(true)} className="flex items-center gap-2 px-5 py-3 rounded-2xl font-black uppercase text-xs theme-element border theme-border"><Upload className="w-4 h-4" /> Importar</button>
                    <button type="button" onClick={() => { setItemActual(null); reset(); setData(formVacio(extensiones)); setModalAbierto(true); }} className="px-6 py-3 rounded-2xl text-white font-black uppercase text-xs" style={{ backgroundColor: 'var(--color-primario)' }}><Plus className="w-4 h-4 inline" /> Nuevo</button>
                </div>
            </div>
            <table className="w-full">
                <thead>
                    <tr className="border-b theme-border text-left text-[10px] font-black uppercase theme-text-muted">
                        <th className="px-6 py-3">Nombre</th>
                        <th className="px-6 py-3">Especs.</th>
                        <th className="px-6 py-3">Extensiones</th>
                        <th className="px-6 py-3" />
                    </tr>
                </thead>
                <tbody>
                    {datos.map((item) => (
                        <tr key={item.id} className="border-b theme-border">
                            <td className="px-6 py-4 font-black theme-text-main">{item.nombre}</td>
                            <td className="px-6 py-4 text-xs font-bold theme-text-muted">{conteoAttrs(item)}</td>
                            <td className="px-6 py-4 text-xs font-bold theme-text-muted">{conteoExt(item)}</td>
                            <td className="px-6 py-4 text-right">
                                <button type="button" onClick={() => abrirEditar(item)} className="p-2 theme-element border theme-border rounded-xl mr-2"><Edit2 className="w-4 h-4" /></button>
                                <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2 theme-element border theme-border rounded-xl"><Trash2 className="w-4 h-4" /></button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {modalAbierto && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-lg w-full modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-between items-center shrink-0 px-6 pt-6 pb-3 border-b theme-border">
                            <h3 className="text-xl font-black italic uppercase theme-text-main m-0">{itemActual ? 'Editar' : 'Nueva'} Categoría</h3>
                            <button type="button" onClick={() => setModalAbierto(false)} className="p-2 rounded-full hover:bg-black/5"><X className="w-5 h-5" /></button>
                        </div>
                        <form onSubmit={handleSubmit} className="flex flex-col min-h-0 flex-1 overflow-hidden">
                            <div className="gelia-modal-body px-6 py-4 space-y-4 custom-scrollbar">
                                <input required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className="theme-input w-full px-4 py-3 font-bold" placeholder="Nombre categoría" />
                                {errors.nombre && <p className="text-xs text-red-500 dark:text-red-400">{errors.nombre}</p>}

                                <div className="space-y-2">
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Extensiones</p>
                                    {extensiones.length === 0 && (
                                        <p className="text-xs theme-text-muted m-0">No hay extensiones registradas en el sistema.</p>
                                    )}
                                    {(data.extensiones || []).map((ext) => {
                                        const meta = extensiones.find((e) => e.codigo === ext.codigo);
                                        return (
                                            <div key={ext.codigo} className="border theme-border rounded-xl p-3 space-y-2">
                                                <label className="flex gap-2 items-center text-sm font-bold theme-text-main">
                                                    <input
                                                        type="checkbox"
                                                        checked={!!ext.habilitada}
                                                        onChange={(e) => patchExtension(ext.codigo, { habilitada: e.target.checked })}
                                                        disabled={meta && meta.habilitada === false}
                                                    />
                                                    <span>{meta?.nombre || ext.codigo}</span>
                                                </label>
                                                {ext.habilitada && (
                                                    <label className="flex gap-2 items-center text-xs font-bold theme-text-muted pl-6">
                                                        <input
                                                            type="checkbox"
                                                            checked={ext.heredable !== false}
                                                            onChange={(e) => patchExtension(ext.codigo, { heredable: e.target.checked })}
                                                        />
                                                        Heredable a subcategorías
                                                    </label>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>

                                <div className="space-y-2">
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Especificaciones de esta categoría</p>
                                    {atributos.length === 0 && (
                                        <p className="text-xs theme-text-muted m-0">Primero crea atributos en la pestaña «Atributos Producto».</p>
                                    )}
                                    <div className="max-h-48 overflow-y-auto border theme-border rounded-xl p-3 space-y-2">
                                        {atributos.filter((a) => a.estado !== false).map((attr) => (
                                            <label key={attr.id} className="flex gap-2 items-center text-sm font-bold theme-text-main">
                                                <input
                                                    type="checkbox"
                                                    checked={(data.atributo_ids || []).map(Number).includes(Number(attr.id))}
                                                    onChange={() => toggleAtributo(attr.id)}
                                                />
                                                <span>{attr.nombre}</span>
                                                <span className="text-[10px] theme-text-muted font-bold uppercase">{attr.tipo_dato}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            </div>
                            <div className="gelia-modal-footer px-6 py-4">
                                <button type="submit" className="w-full py-3 text-white rounded-xl font-black uppercase" style={{ backgroundColor: 'var(--color-primario)' }}><Save className="w-4 h-4 inline" /> Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalEliminar(false)}>
                    <div className={`${THEME_MODAL_SHELL} p-8 text-center modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <p className="theme-text-main mb-4">¿Eliminar «{itemActual?.nombre}»?</p>
                        <button type="button" onClick={() => router.delete(route('admin.catalogos.categorias_producto.destroy', itemActual.id), { onSuccess: () => setModalEliminar(false) })} className="px-6 py-3 bg-red-600 text-white rounded-xl font-black uppercase text-[10px]">Eliminar</button>
                    </div>
                </div>, document.body
            )}
            {modalImportar && <ModalImportarCatalogo config={IMPORTACION_CATALOGOS.categorias_producto} onClose={() => setModalImportar(false)} />}
        </div>
    );
}
