import React, { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { useForm } from '@inertiajs/react';
import { ImageIcon, Edit2, Trash2, Upload } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import {
    INPUT_CLASS,
    THEME_BTN_PRIMARY,
    TH_LEFT,
    TH_RIGHT,
    BTN_ICON_ACTION,
    BTN_ICON_DANGER,
    EstadoVacio,
    ModalPersonalizacion,
    ModalConfirmarEliminar,
    CampoFormulario,
} from './personalizacionShared';

const VECTOR_SLUGS = ['blob', 'blobscene', 'circle', 'layered', 'peaks', 'polygon', 'square', 'stacked', 'steps', 'wave'];

function TarjetaFondoMobile({ item, onEditar, onEliminar }) {
    return (
        <article className="p-4 rounded-2xl theme-element border theme-border flex gap-4 lg:hidden">
            <div className="w-20 h-14 rounded-xl overflow-hidden border theme-border shrink-0">
                <img src={item.preview} alt={item.nombre} className="w-full h-full object-cover" />
            </div>
            <div className="min-w-0 flex-1 flex flex-col gap-2">
                <div>
                    <p className="text-sm font-black theme-text-main uppercase italic m-0 truncate">{item.nombre}</p>
                    <p className="text-[9px] font-bold theme-text-muted uppercase m-0">{item.slug}</p>
                </div>
                <span className="text-[9px] font-black uppercase px-2 py-1 rounded-full theme-element border theme-border theme-text-muted w-fit">{item.tipo}</span>
                <div className="flex gap-2 pt-2 border-t theme-border">
                    <button type="button" onClick={() => onEditar(item)} className={`${BTN_ICON_ACTION} flex-1 flex justify-center`}>
                        <Edit2 className="w-4 h-4" />
                    </button>
                    <button type="button" onClick={() => onEliminar(item)} className={`${BTN_ICON_DANGER} flex-1 flex justify-center`}>
                        <Trash2 className="w-4 h-4 theme-text-main group-hover/del:text-white" />
                    </button>
                </div>
            </div>
        </article>
    );
}

export default function TablaFondos({ catalogo = {}, registrarAbrir }) {
    const datos = catalogo?.data ?? [];
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

    const abrirNuevo = useCallback(() => {
        setItemActual(null);
        limpiarPreview();
        reset();
        setData({ nombre: '', slug: '', tipo: 'vector', valor: 'blob', archivo: null, activo: true, orden: 0 });
        if (fileInputRef.current) fileInputRef.current.value = '';
        setModalAbierto(true);
    }, [limpiarPreview, reset, setData]);

    useEffect(() => {
        if (!registrarAbrir) return undefined;
        registrarAbrir.current = abrirNuevo;
        return () => {
            if (registrarAbrir.current === abrirNuevo) {
                registrarAbrir.current = null;
            }
        };
    }, [registrarAbrir, abrirNuevo]);

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
        if (data.tipo === 'vector') return `/assets/backgrounds/${data.valor || 'blob'}_pc.svg`;
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

            {datos.length === 0 ? (
                <EstadoVacio
                    icon={ImageIcon}
                    titulo="Sin fondos en catálogo"
                    mensaje="Agrega vectores del sistema o imágenes personalizadas para el perfil."
                    accionLabel="Nuevo fondo"
                    onAccion={abrirNuevo}
                />
            ) : (
                <>
                    <div className="lg:hidden p-4 md:p-6 space-y-3">
                        {datos.map((item) => (
                            <TarjetaFondoMobile key={item.id} item={item} onEditar={abrirEditar} onEliminar={(i) => { setItemActual(i); setModalEliminar(true); }} />
                        ))}
                    </div>
                    <div className="hidden lg:block overflow-x-auto">
                        <table className="w-full border-collapse min-w-[560px]">
                            <thead>
                                <tr className="border-b-2 border-[var(--color-primario)]/30">
                                    <th className={TH_LEFT}>Vista</th>
                                    <th className={TH_LEFT}>Nombre</th>
                                    <th className={TH_LEFT}>Tipo</th>
                                    <th className={TH_RIGHT}>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {datos.map((item) => (
                                    <tr key={item.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="w-20 h-14 rounded-xl overflow-hidden border theme-border shadow-sm">
                                                <img src={item.preview} alt={item.nombre} className="w-full h-full object-cover" />
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <p className="text-sm font-black theme-text-main uppercase italic m-0">{item.nombre}</p>
                                            <p className="text-[9px] font-bold theme-text-muted uppercase m-0">{item.slug}</p>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="text-[9px] font-black uppercase px-2 py-1 rounded-full theme-element border theme-border theme-text-muted">{item.tipo}</span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex justify-end gap-2">
                                                <button type="button" onClick={() => abrirEditar(item)} className={BTN_ICON_ACTION}>
                                                    <Edit2 className="w-4 h-4" />
                                                </button>
                                                <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className={BTN_ICON_DANGER}>
                                                    <Trash2 className="w-4 h-4 theme-text-main group-hover/del:text-white" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            <ModalPersonalizacion abierto={modalAbierto} onClose={cerrarModal} titulo={itemActual ? 'Editar fondo' : 'Nuevo fondo'} tamano="max-w-lg">
                <form onSubmit={handleSubmit} className="flex flex-col flex-1 min-h-0">
                    <div className="gelia-modal-body p-5 md:p-8 space-y-5">
                        <CampoFormulario label="Nombre">
                            <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={INPUT_CLASS} required />
                        </CampoFormulario>
                        <CampoFormulario label="Tipo">
                            <select value={data.tipo} onChange={(e) => handleTipoChange(e.target.value)} className={INPUT_CLASS}>
                                <option value="vector">Vectorial (SVG del sistema)</option>
                                <option value="imagen">Imagen subida</option>
                            </select>
                        </CampoFormulario>
                        {data.tipo === 'vector' ? (
                            <CampoFormulario label="Diseño vectorial">
                                <select value={data.valor} onChange={(e) => setData('valor', e.target.value)} className={INPUT_CLASS}>
                                    {VECTOR_SLUGS.map((slug) => (
                                        <option key={slug} value={slug}>{slug}</option>
                                    ))}
                                </select>
                            </CampoFormulario>
                        ) : (
                            <CampoFormulario label={itemActual ? 'Reemplazar imagen (opcional)' : 'Imagen'} error={errors.archivo}>
                                <input ref={fileInputRef} type="file" accept="image/jpeg,image/png,image/jpg,image/webp" className="hidden" onChange={handleArchivoChange} />
                                <button
                                    type="button"
                                    onClick={() => fileInputRef.current?.click()}
                                    className="flex items-center justify-center gap-3 w-full min-h-[3.5rem] rounded-2xl border-2 border-dashed theme-border theme-element hover:border-[var(--color-primario)] transition-all outline-none"
                                >
                                    <Upload className="w-5 h-5 theme-text-main shrink-0" />
                                    <span className="text-[11px] font-black uppercase tracking-widest theme-text-main text-center px-2">
                                        {data.archivo?.name || (itemActual?.tipo === 'imagen' ? 'Elegir nueva imagen' : 'Seleccionar imagen')}
                                    </span>
                                </button>
                            </CampoFormulario>
                        )}
                        <CampoFormulario label="Vista previa">
                            <div className="relative w-full h-36 rounded-2xl overflow-hidden border-2 theme-border theme-element">
                                {previewSrc ? (
                                    <img src={previewSrc} alt="Vista previa" className="w-full h-full object-cover" />
                                ) : (
                                    <div className="w-full h-full flex flex-col items-center justify-center gap-2 opacity-50">
                                        <ImageIcon className="w-8 h-8 theme-text-muted" />
                                        <span className="text-[9px] font-black uppercase theme-text-muted">Sin vista previa</span>
                                    </div>
                                )}
                                <span className="absolute bottom-2 right-2 text-[8px] font-black uppercase px-2 py-1 rounded-full bg-black/60 text-white">
                                    {data.tipo === 'vector' ? 'SVG' : 'Imagen'}
                                </span>
                            </div>
                        </CampoFormulario>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <CampoFormulario label="Orden">
                                <input type="number" min="0" value={data.orden} onChange={(e) => setData('orden', Number(e.target.value))} className={INPUT_CLASS} />
                            </CampoFormulario>
                            <div className="flex items-end gap-3 pb-1">
                                <button type="button" className="gelia-switch shrink-0 scale-110 shadow-sm" data-active={data.activo} onClick={() => setData('activo', !data.activo)}>
                                    <div className="gelia-switch-thumb shadow-md" />
                                </button>
                                <span className="text-[10px] font-black uppercase theme-text-muted">Activo</span>
                            </div>
                        </div>
                    </div>
                    <div className="gelia-modal-footer p-5 md:p-8">
                        <button type="submit" disabled={processing} className={`${THEME_BTN_PRIMARY} w-full`}>
                            {processing ? 'Guardando...' : 'Guardar fondo'}
                        </button>
                    </div>
                </form>
            </ModalPersonalizacion>

            <ModalConfirmarEliminar
                abierto={modalEliminar}
                onClose={() => setModalEliminar(false)}
                onConfirm={confirmDelete}
                titulo="Eliminar fondo"
                mensaje={`¿Eliminar el fondo «${itemActual?.nombre}»? Esta acción no se puede deshacer.`}
            />
        </div>
    );
}
