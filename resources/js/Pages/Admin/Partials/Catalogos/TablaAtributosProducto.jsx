import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { ListTree, Edit2, Trash2, Plus, Save, X } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_BTN_PRIMARY, THEME_INPUT, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_SELECT } from '@/utils/geliaTheme';

const TIPOS = [
    { value: 'texto', label: 'Texto' },
    { value: 'texto_largo', label: 'Texto largo' },
    { value: 'entero', label: 'Entero' },
    { value: 'decimal', label: 'Decimal' },
    { value: 'booleano', label: 'Sí/No' },
    { value: 'fecha', label: 'Fecha' },
    { value: 'opcion', label: 'Opción (lista)' },
    { value: 'medida', label: 'Medida (valor + unidad)' },
];

const formVacio = () => ({
    nombre: '',
    slug: '',
    tipo_dato: 'texto',
    permite_multiples: false,
    dimension_unidad: '',
    filtrable: true,
    visible_en_ficha: true,
    estado: true,
    opciones: [],
});

export default function TablaAtributosProducto({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const { data, setData, post, put, processing, reset, errors } = useForm(formVacio());

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setData(formVacio());
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            nombre: item.nombre || '',
            slug: item.slug || '',
            tipo_dato: item.tipo_dato || 'texto',
            permite_multiples: !!item.permite_multiples,
            dimension_unidad: item.dimension_unidad || '',
            filtrable: item.filtrable !== false,
            visible_en_ficha: item.visible_en_ficha !== false,
            estado: item.estado !== false,
            opciones: (item.opciones || []).map((o) => ({
                id: o.id,
                nombre: o.nombre,
                estado: o.estado !== false,
            })),
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const opts = { onSuccess: () => { setModalAbierto(false); reset(); } };
        if (itemActual) {
            put(route('admin.catalogos.atributos_producto.update', itemActual.id), opts);
        } else {
            post(route('admin.catalogos.atributos_producto.store'), opts);
        }
    };

    const setOpcion = (idx, patch) => {
        const next = [...(data.opciones || [])];
        next[idx] = { ...next[idx], ...patch };
        setData('opciones', next);
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando atributo_" />
            <div className="p-6 border-b theme-border flex justify-between flex-wrap gap-4">
                <div>
                    <h2 className="text-xl font-black italic uppercase m-0 flex items-center gap-2 theme-text-main">
                        <ListTree className="w-5 h-5" /> Atributos Producto_
                    </h2>
                    <p className="text-[10px] font-bold theme-text-muted mt-1 mb-0 uppercase tracking-wide">
                        Defínelos aquí; asígnalos a cada categoría en «Categorías Producto».
                    </p>
                </div>
                <button type="button" onClick={abrirNuevo} className={`${THEME_BTN_PRIMARY} px-6 py-3 rounded-2xl font-black uppercase text-xs`}>
                    <Plus className="w-4 h-4 inline" /> Nuevo
                </button>
            </div>
            <table className="w-full">
                <thead>
                    <tr className="border-b theme-border text-left text-[10px] font-black uppercase theme-text-muted">
                        <th className="px-6 py-3">Nombre</th>
                        <th className="px-6 py-3">Tipo</th>
                        <th className="px-6 py-3">Opciones</th>
                        <th className="px-6 py-3">Estado</th>
                        <th className="px-6 py-3" />
                    </tr>
                </thead>
                <tbody>
                    {datos.map((item) => (
                        <tr key={item.id} className="border-b theme-border">
                            <td className="px-6 py-4 font-black theme-text-main">{item.nombre}</td>
                            <td className="px-6 py-4 text-xs font-bold theme-text-main">{item.tipo_dato}</td>
                            <td className="px-6 py-4 text-xs font-bold theme-text-muted">
                                {item.tipo_dato === 'opcion' ? (item.opciones || []).filter((o) => o.estado !== false).length : '—'}
                            </td>
                            <td className="px-6 py-4 text-[10px] font-bold theme-text-main">{item.estado ? 'Activo' : 'Inactivo'}</td>
                            <td className="px-6 py-4 text-right whitespace-nowrap">
                                <button type="button" onClick={() => abrirEditar(item)} className="p-2 theme-element border theme-border rounded-xl mr-2"><Edit2 className="w-4 h-4" /></button>
                                <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2 theme-element border theme-border rounded-xl"><Trash2 className="w-4 h-4" /></button>
                            </td>
                        </tr>
                    ))}
                    {datos.length === 0 && (
                        <tr><td colSpan={5} className="px-6 py-8 text-sm theme-text-muted">Sin atributos. Crea el primero o revisa la migración de semillas.</td></tr>
                    )}
                </tbody>
            </table>

            {modalAbierto && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-lg w-full flex flex-col modal-pop theme-text-main`} onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-between items-center shrink-0 px-6 pt-6 pb-3 border-b theme-border">
                            <h3 className="text-xl font-black italic uppercase theme-text-main m-0">{itemActual ? 'Editar' : 'Nuevo'} Atributo</h3>
                            <button
                                type="button"
                                onClick={() => setModalAbierto(false)}
                                className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none"
                                aria-label="Cerrar"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="flex flex-col min-h-0 flex-1 overflow-hidden">
                            <div className="gelia-modal-body px-6 py-4 space-y-3 custom-scrollbar">
                                <input required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={`${THEME_INPUT} w-full px-4 py-3 font-bold`} placeholder="Nombre (ej. Volumen, Material)" />
                                {errors.nombre && <p className="text-xs text-red-500 dark:text-red-400">{errors.nombre}</p>}
                                <input value={data.slug} onChange={(e) => setData('slug', e.target.value)} className={`${THEME_INPUT} w-full px-4 py-3 font-bold`} placeholder="Slug (opcional)" />
                                <select value={data.tipo_dato} onChange={(e) => setData('tipo_dato', e.target.value)} className={`${THEME_SELECT} w-full px-4 py-3 font-bold`}>
                                    {TIPOS.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                                </select>
                                {data.tipo_dato === 'medida' && (
                                    <input value={data.dimension_unidad || ''} onChange={(e) => setData('dimension_unidad', e.target.value)} className={`${THEME_INPUT} w-full px-4 py-3 font-bold`} placeholder="Dimensión unidad (volumen, peso…)" />
                                )}
                                {data.tipo_dato === 'opcion' && (
                                    <div className="space-y-2 border theme-border rounded-xl p-3 theme-element">
                                        <div className="flex justify-between items-center">
                                            <p className="text-[10px] font-black uppercase theme-text-muted m-0">Opciones a elegir</p>
                                            <button type="button" className="text-[10px] font-black uppercase theme-text-muted hover:theme-text-main" onClick={() => setData('opciones', [...(data.opciones || []), { nombre: '', estado: true }])}>+ Opción</button>
                                        </div>
                                        <label className="flex gap-2 items-center text-xs font-bold theme-text-main">
                                            <input type="checkbox" checked={!!data.permite_multiples} onChange={(e) => setData('permite_multiples', e.target.checked)} />
                                            Permitir varias opciones
                                        </label>
                                        {(data.opciones || []).map((op, idx) => (
                                            <div key={op.id || `n-${idx}`} className="flex gap-2 items-center">
                                                <input value={op.nombre} onChange={(e) => setOpcion(idx, { nombre: e.target.value })} className={`${THEME_INPUT} flex-1 px-3 py-2 text-sm font-bold`} placeholder="Nombre opción" />
                                                <button type="button" className="text-red-500 dark:text-red-400 text-xs font-bold shrink-0" onClick={() => setData('opciones', data.opciones.filter((_, i) => i !== idx))}>Quitar</button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                <label className="flex gap-2 items-center text-sm font-bold theme-text-main"><input type="checkbox" checked={!!data.visible_en_ficha} onChange={(e) => setData('visible_en_ficha', e.target.checked)} /> Visible en ficha</label>
                                <label className="flex gap-2 items-center text-sm font-bold theme-text-main"><input type="checkbox" checked={!!data.filtrable} onChange={(e) => setData('filtrable', e.target.checked)} /> Filtrable</label>
                                <label className="flex gap-2 items-center text-sm font-bold theme-text-main"><input type="checkbox" checked={!!data.estado} onChange={(e) => setData('estado', e.target.checked)} /> Activo</label>
                            </div>
                            <div className="gelia-modal-footer px-6 py-4">
                                <button type="submit" className={`${THEME_BTN_PRIMARY} w-full py-3 flex justify-center items-center gap-2`}>
                                    <Save className="w-4 h-4" /> Guardar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalEliminar(false)}>
                    <div className={`${THEME_MODAL_SHELL} p-8 text-center modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <p className="theme-text-main mb-4">¿Eliminar o desactivar «{itemActual?.nombre}»?</p>
                        <button
                            type="button"
                            onClick={() => router.delete(route('admin.catalogos.atributos_producto.destroy', itemActual.id), { onSuccess: () => setModalEliminar(false) })}
                            className="px-6 py-3 bg-red-600 text-white rounded-xl font-black uppercase text-[10px]"
                        >
                            Confirmar
                        </button>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
