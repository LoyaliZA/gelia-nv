import React, { useState, useRef, useEffect, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { ImageIcon, Edit2, Trash2, Plus, X, Save, AlertTriangle, Upload } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';

const VECTOR_SLUGS = ['blob', 'blobscene', 'circle', 'layered', 'peaks', 'polygon', 'square', 'stacked', 'steps', 'wave'];

const thLeft = 'px-6 py-4 text-[9px] font-black uppercase tracking-widest theme-text-muted text-left';
const thRight = 'px-6 py-4 text-[9px] font-black uppercase tracking-widest theme-text-muted text-right';
const inputClass =
    'w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md';

export default function TablaFondos({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const [previewUrl, setPreviewUrl] = useState(null);
    const fileInputRef = useRef(null);

    const { data, setData, post, processing, reset, errors } = useForm({
        nombre: '',
        slug: '',
        tipo: 'vector',
        valor: 'blob',
        archivo: null,
        activo: true,
        orden: 0,
    });

    useEffect(() => () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
    }, [previewUrl]);

    const limpiarPreview = () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        setPreviewUrl(null);
    };

    const cerrarModal = () => {
        limpiarPreview();
        setModalAbierto(false);
    };

    const abrirNuevo = () => {
        setItemActual(null);
        limpiarPreview();
        reset();
        setData({ nombre: '', slug: '', tipo: 'vector', valor: 'blob', archivo: null, activo: true, orden: 0 });
        if (fileInputRef.current) fileInputRef.current.value = '';
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        limpiarPreview();
        setData({
            nombre: item.nombre,
            slug: item.slug,
            tipo: item.tipo,
            valor: item.valor,
            archivo: null,
            activo: item.activo,
            orden: item.orden ?? 0,
        });
        if (fileInputRef.current) fileInputRef.current.value = '';
        setModalAbierto(true);
    };

    const handleArchivoChange = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        limpiarPreview();
        setData('archivo', file);
        setPreviewUrl(URL.createObjectURL(file));
    };

    const handleTipoChange = (tipo) => {
        setData({ ...data, tipo, archivo: null, valor: tipo === 'vector' ? (data.valor || 'blob') : data.valor });
        limpiarPreview();
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const previewSrc = useMemo(() => {
        if (data.tipo === 'vector') {
            return `/assets/backgrounds/${data.valor || 'blob'}_pc.svg`;
        }
        if (previewUrl) return previewUrl;
        if (itemActual?.tipo === 'imagen' && itemActual?.preview) return itemActual.preview;
        return null;
    }, [data.tipo, data.valor, previewUrl, itemActual]);

    const handleSubmit = (e) => {
        e.preventDefault();
        const ruta = itemActual
            ? route('admin.personalizacion.fondos.update', itemActual.id)
            : route('admin.personalizacion.fondos.store');

        post(ruta, {
            forceFormData: true,
            onSuccess: () => {
                limpiarPreview();
                setModalAbierto(false);
                reset();
            },
        });
    };

    const confirmDelete = () => {
        post(route('admin.personalizacion.fondos.destroy', itemActual.id), {
            _method: 'delete',
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando fondo_" />

            <div className="p-8 md:p-10 border-b theme-border flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <ImageIcon className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                            Fondos Predeterminados_
                        </h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-1">{datos.length} fondos en catálogo</p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={abrirNuevo}
                    className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-[11px] tracking-widest transition-all hover:scale-105 shadow-xl text-white outline-none border border-black/10"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    <Plus className="w-4 h-4" /> Nuevo fondo
                </button>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className={thLeft}>Vista_</th>
                            <th className={thLeft}>Nombre_</th>
                            <th className={thLeft}>Tipo_</th>
                            <th className={thRight}>Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="group border-b theme-border last:border-0 hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/30 transition-all">
                                <td className="px-6 py-4">
                                    <div className="w-20 h-14 rounded-xl overflow-hidden border theme-border shadow-sm">
                                        <img src={item.preview} alt={item.nombre} className="w-full h-full object-cover" />
                                    </div>
                                </td>
                                <td className="px-6 py-4">
                                    <p className="text-sm font-black theme-text-main uppercase italic">{item.nombre}</p>
                                    <p className="text-[9px] font-bold theme-text-muted uppercase">{item.slug}</p>
                                </td>
                                <td className="px-6 py-4">
                                    <span className="text-[9px] font-black uppercase px-2 py-1 rounded-full theme-element border theme-border theme-text-muted">{item.tipo}</span>
                                </td>
                                <td className="px-6 py-4 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            onClick={() => abrirEditar(item)}
                                            className="p-2.5 theme-element border theme-border rounded-xl transition-all outline-none shadow-sm hover:scale-105 hover:border-[var(--color-primario)] group/btn"
                                        >
                                            <Edit2 className="w-4 h-4 theme-text-main group-hover/btn:text-[var(--color-primario)] transition-colors" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setItemActual(item); setModalEliminar(true); }}
                                            className="p-2.5 theme-element border theme-border rounded-xl transition-all outline-none shadow-sm hover:bg-red-500 hover:border-red-500 group/del"
                                        >
                                            <Trash2 className="w-4 h-4 theme-text-main group-hover/del:text-white transition-colors" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={cerrarModal}>
                    <div className="w-full max-w-lg theme-surface border theme-border shadow-2xl rounded-[2.5rem] max-h-[90vh] overflow-y-auto modal-pop" onClick={(e) => e.stopPropagation()}>
                        <div className="p-6 border-b theme-border flex justify-between items-center sticky top-0 theme-surface z-10">
                            <h2 className="text-xl font-black italic uppercase theme-text-main m-0">{itemActual ? 'Editar' : 'Nuevo'} Fondo_</h2>
                            <button type="button" onClick={cerrarModal} className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-8 space-y-5">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Nombre</label>
                                <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={inputClass} required />
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Tipo</label>
                                <select value={data.tipo} onChange={(e) => handleTipoChange(e.target.value)} className={inputClass}>
                                    <option value="vector">Vectorial (SVG del sistema)</option>
                                    <option value="imagen">Imagen subida</option>
                                </select>
                            </div>

                            {data.tipo === 'vector' ? (
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Diseño vectorial</label>
                                    <select value={data.valor} onChange={(e) => setData('valor', e.target.value)} className={inputClass}>
                                        {VECTOR_SLUGS.map((slug) => (
                                            <option key={slug} value={slug}>{slug}</option>
                                        ))}
                                    </select>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                        {itemActual ? 'Reemplazar imagen (opcional)' : 'Imagen'}
                                    </label>
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg,image/webp"
                                        className="hidden"
                                        onChange={handleArchivoChange}
                                    />
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="flex items-center justify-center gap-3 w-full h-14 rounded-2xl border-2 border-dashed theme-border theme-element cursor-pointer hover:border-[var(--color-primario)] transition-all outline-none hover:shadow-md"
                                    >
                                        <Upload className="w-5 h-5 theme-text-main" />
                                        <span className="text-[11px] font-black uppercase tracking-widest theme-text-main">
                                            {data.archivo?.name || (itemActual?.tipo === 'imagen' ? 'Elegir nueva imagen' : 'Seleccionar imagen (.jpg, .png, .webp)')}
                                        </span>
                                    </button>
                                    {errors.archivo && <p className="text-xs text-red-500">{errors.archivo}</p>}
                                </div>
                            )}

                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Vista previa</label>
                                <div className="relative w-full h-36 rounded-2xl overflow-hidden border-2 theme-border theme-element">
                                    {previewSrc ? (
                                        <img src={previewSrc} alt="Vista previa del fondo" className="w-full h-full object-cover" />
                                    ) : (
                                        <div className="w-full h-full flex flex-col items-center justify-center gap-2 opacity-50">
                                            <ImageIcon className="w-8 h-8 theme-text-muted" />
                                            <span className="text-[9px] font-black uppercase theme-text-muted tracking-widest">Sin vista previa</span>
                                        </div>
                                    )}
                                    <span className="absolute bottom-2 right-2 text-[8px] font-black uppercase px-2 py-1 rounded-full bg-black/60 text-white">
                                        {data.tipo === 'vector' ? 'SVG' : 'Imagen'}
                                    </span>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Orden</label>
                                    <input type="number" min="0" value={data.orden} onChange={(e) => setData('orden', Number(e.target.value))} className={inputClass} />
                                </div>
                                <div className="flex items-end pb-2">
                                    <button type="button" className="gelia-switch shrink-0 scale-110 shadow-sm" data-active={data.activo} onClick={() => setData('activo', !data.activo)}>
                                        <div className="gelia-switch-thumb shadow-md" />
                                    </button>
                                    <span className="ml-3 text-[10px] font-black uppercase theme-text-muted">Activo</span>
                                </div>
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-[11px] text-white flex items-center justify-center gap-2 transition-all hover:scale-105 shadow-xl disabled:opacity-60 disabled:scale-100 outline-none border border-black/10"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={() => setModalEliminar(false)}>
                    <div className="w-full max-w-md theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-8 space-y-6 modal-pop" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center gap-4">
                            <AlertTriangle className="w-8 h-8 text-red-500 shrink-0" />
                            <p className="text-sm theme-text-muted m-0">¿Eliminar el fondo «{itemActual?.nombre}»?</p>
                        </div>
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={() => setModalEliminar(false)}
                                className="flex-1 py-3 rounded-xl font-black uppercase text-xs theme-element border theme-border theme-text-main hover:border-[var(--color-primario)] transition-colors outline-none shadow-sm"
                            >
                                Cancelar
                            </button>
                            <button type="button" onClick={confirmDelete} className="flex-1 py-3 rounded-xl font-black uppercase text-xs text-white bg-red-500 hover:bg-red-600 transition-colors outline-none shadow-sm">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
