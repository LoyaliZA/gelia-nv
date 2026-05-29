import React, { useState, useEffect, useCallback } from 'react';
import { useForm } from '@inertiajs/react';
import { Volume2, Edit2, Trash2, Play } from 'lucide-react';
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

function TarjetaTonoMobile({ item, onEditar, onEliminar, onPreview }) {
    return (
        <article className="p-4 rounded-2xl theme-element border theme-border space-y-3 lg:hidden">
            <div className="min-w-0">
                <p className="text-sm font-black theme-text-main uppercase italic leading-tight m-0">{item.nombre}</p>
                <p className="text-[9px] font-bold theme-text-muted uppercase mt-1 m-0">{item.slug}</p>
                <p className="text-[10px] font-bold theme-text-muted mt-2 truncate m-0">{item.archivo}</p>
            </div>
            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase w-fit ${item.activo ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'}`}>
                {item.activo ? 'Activo' : 'Inactivo'}
            </span>
            <div className="flex gap-2 pt-2 border-t theme-border">
                <button type="button" onClick={() => onPreview(item.path)} className={`${BTN_ICON_ACTION} flex-1 flex justify-center`} title="Probar">
                    <Play className="w-4 h-4" />
                </button>
                <button type="button" onClick={() => onEditar(item)} className={`${BTN_ICON_ACTION} flex-1 flex justify-center`}>
                    <Edit2 className="w-4 h-4" />
                </button>
                <button type="button" onClick={() => onEliminar(item)} className={`${BTN_ICON_DANGER} flex-1 flex justify-center`}>
                    <Trash2 className="w-4 h-4 theme-text-main group-hover/del:text-white" />
                </button>
            </div>
        </article>
    );
}

export default function TablaTonos({ catalogo = {}, registrarAbrir }) {
    const datos = catalogo?.data ?? [];
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, processing, reset, errors } = useForm({
        nombre: '',
        slug: '',
        archivo: null,
        activo: true,
        orden: 0,
    });

    const abrirNuevo = useCallback(() => {
        setItemActual(null);
        reset();
        setModalAbierto(true);
    }, [reset]);

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
        setData({
            nombre: item.nombre,
            slug: item.slug,
            archivo: null,
            activo: item.activo,
            orden: item.orden ?? 0,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const ruta = itemActual
            ? route('admin.personalizacion.tonos.update', itemActual.id)
            : route('admin.personalizacion.tonos.store');

        post(ruta, {
            forceFormData: true,
            onSuccess: () => { setModalAbierto(false); reset(); },
        });
    };

    const confirmDelete = () => {
        post(route('admin.personalizacion.tonos.destroy', itemActual.id), {
            _method: 'delete',
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    const previewTono = (path) => {
        if (!path) return;
        const audio = new Audio(path);
        audio.play().catch(() => {});
    };

    const solicitarEliminar = (item) => {
        setItemActual(item);
        setModalEliminar(true);
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando tono_" />

            {datos.length === 0 ? (
                <EstadoVacio
                    icon={Volume2}
                    titulo="Sin tonos registrados"
                    mensaje="Sube el primer archivo de audio para las alertas del sistema."
                    accionLabel="Subir tono"
                    onAccion={abrirNuevo}
                />
            ) : (
                <>
                    <div className="lg:hidden p-4 md:p-6 space-y-3">
                        {datos.map((item) => (
                            <TarjetaTonoMobile
                                key={item.id}
                                item={item}
                                onEditar={abrirEditar}
                                onEliminar={solicitarEliminar}
                                onPreview={previewTono}
                            />
                        ))}
                    </div>

                    <div className="hidden lg:block overflow-x-auto">
                        <table className="w-full border-collapse min-w-[640px]">
                            <thead>
                                <tr className="border-b-2 border-[var(--color-primario)]/30">
                                    <th className={TH_LEFT}>Nombre / slug</th>
                                    <th className={TH_LEFT}>Archivo</th>
                                    <th className={TH_LEFT}>Estado</th>
                                    <th className={TH_RIGHT}>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {datos.map((item) => (
                                    <tr key={item.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors">
                                        <td className="px-6 py-4">
                                            <p className="text-sm font-black theme-text-main uppercase italic leading-tight m-0">{item.nombre}</p>
                                            <p className="text-[9px] font-bold theme-text-muted uppercase mt-0.5 m-0">{item.slug}</p>
                                        </td>
                                        <td className="px-6 py-4 text-xs font-bold theme-text-muted max-w-[200px] truncate">{item.archivo}</td>
                                        <td className="px-6 py-4">
                                            <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[9px] font-black uppercase ${item.activo ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'}`}>
                                                {item.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex justify-end gap-2">
                                                <button type="button" onClick={() => previewTono(item.path)} className={BTN_ICON_ACTION} title="Probar">
                                                    <Play className="w-4 h-4" />
                                                </button>
                                                <button type="button" onClick={() => abrirEditar(item)} className={BTN_ICON_ACTION}>
                                                    <Edit2 className="w-4 h-4" />
                                                </button>
                                                <button type="button" onClick={() => solicitarEliminar(item)} className={BTN_ICON_DANGER}>
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

            <ModalPersonalizacion
                abierto={modalAbierto}
                onClose={() => setModalAbierto(false)}
                titulo={itemActual ? 'Editar tono' : 'Nuevo tono'}
            >
                <form onSubmit={handleSubmit} className="flex flex-col flex-1 min-h-0">
                    <div className="gelia-modal-body p-5 md:p-8 space-y-5">
                        <CampoFormulario label="Nombre" error={errors.nombre}>
                            <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={INPUT_CLASS} required />
                        </CampoFormulario>
                        <CampoFormulario label="Slug (opcional)">
                            <input value={data.slug} onChange={(e) => setData('slug', e.target.value)} className={INPUT_CLASS} placeholder="campana-clasica" />
                        </CampoFormulario>
                        <CampoFormulario label={itemActual ? 'Reemplazar archivo (opcional)' : 'Archivo de audio'} error={errors.archivo}>
                            <input
                                type="file"
                                accept=".mp3,.wav,.ogg,audio/*"
                                onChange={(e) => setData('archivo', e.target.files[0])}
                                className="w-full text-xs theme-text-main file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-[var(--color-primario)] file:text-white file:cursor-pointer"
                                required={!itemActual}
                            />
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
                            {processing ? 'Guardando...' : 'Guardar tono'}
                        </button>
                    </div>
                </form>
            </ModalPersonalizacion>

            <ModalConfirmarEliminar
                abierto={modalEliminar}
                onClose={() => setModalEliminar(false)}
                onConfirm={confirmDelete}
                titulo="Eliminar tono"
                mensaje={`Se eliminará «${itemActual?.nombre}» y su archivo del servidor.`}
            />
        </div>
    );
}
