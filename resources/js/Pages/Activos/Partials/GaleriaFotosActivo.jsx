import React, { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Camera, Trash2, Upload, X } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import { BTN_SECONDARY_CLASS, LABEL_CLASS } from './activosFormStyles';

const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

export default function GaleriaFotosActivo({
    fotosExistentes = [],
    nuevasFotos = [],
    onChangeNuevas,
    maxFotos = 5,
    editable = true,
    activoId = null,
    modo = 'formulario',
    variant = 'default',
}) {
    const inputRef = useRef(null);
    const [previewUrls, setPreviewUrls] = useState([]);
    const [lightbox, setLightbox] = useState(null);
    const [subiendo, setSubiendo] = useState(false);
    const [error, setError] = useState(null);

    const esDirecto = modo === 'directo';
    const total = fotosExistentes.length + (esDirecto ? 0 : nuevasFotos.length);
    const espacio = maxFotos - total;

    const validarArchivos = (files) => {
        const lista = Array.from(files || []);
        if (!lista.length) return [];

        const invalidos = lista.filter((f) => !TIPOS_PERMITIDOS.includes(f.type));
        if (invalidos.length) {
            setError('Solo se permiten imágenes JPEG, PNG o WebP.');
            return [];
        }

        if (lista.length > espacio) {
            setError(`Máximo ${maxFotos} fotografías por activo. Espacio disponible: ${espacio}.`);
            return lista.slice(0, espacio);
        }

        setError(null);
        return lista;
    };

    const subirDirecto = (files) => {
        const lista = validarArchivos(files);
        if (!lista.length || !activoId) return;

        setSubiendo(true);
        router.post(
            route('activos.fotos.store', activoId),
            { fotos: lista },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setSubiendo(false),
                onError: (errors) => {
                    const msg = errors.fotos || errors.message || 'No se pudieron subir las fotografías.';
                    setError(Array.isArray(msg) ? msg[0] : msg);
                },
            }
        );
    };

    const handleFiles = (files) => {
        if (esDirecto) {
            subirDirecto(files);
            return;
        }

        const lista = validarArchivos(files);
        if (!lista.length) return;

        const merged = [...nuevasFotos, ...lista];
        onChangeNuevas?.(merged);

        lista.forEach((file) => {
            const url = URL.createObjectURL(file);
            setPreviewUrls((prev) => [...prev, url]);
        });
    };

    const quitarNueva = (index) => {
        const next = nuevasFotos.filter((_, i) => i !== index);
        onChangeNuevas?.(next);
        setPreviewUrls((prev) => prev.filter((_, i) => i !== index));
    };

    const eliminarExistente = (fotoId) => {
        if (!activoId || !confirm('¿Eliminar esta fotografía?')) return;
        router.delete(route('activos.fotos.destroy', [activoId, fotoId]), { preserveScroll: true });
    };

    const urlFoto = (foto) => foto.url || `/storage/${foto.ruta}`;

    const wrapperClass = variant === 'hero'
        ? 'space-y-3'
        : 'border-t theme-border pt-4 space-y-3';

    const gridClass = variant === 'hero'
        ? 'grid grid-cols-2 gap-3'
        : 'grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3';

    return (
        <div className={`relative ${wrapperClass}`}>
            <GeliaLoader isVisible={subiendo} message="Subiendo fotos_" />

            <div className="flex items-center justify-between">
                <p className={LABEL_CLASS}>Fotografías ({total}/{maxFotos})</p>
                {editable && espacio > 0 && (
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        disabled={subiendo}
                        className={`${BTN_SECONDARY_CLASS} flex items-center gap-1 text-[10px] disabled:opacity-50`}
                    >
                        <Upload className="w-3 h-3" /> {esDirecto ? 'Subir' : 'Agregar'}
                    </button>
                )}
            </div>

            {error && (
                <p className="text-[10px] font-bold text-red-600 dark:text-red-400">{error}</p>
            )}

            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp,image/jpg"
                multiple
                className="hidden"
                disabled={subiendo}
                onChange={(e) => { handleFiles(e.target.files); e.target.value = ''; }}
            />

            <div className={gridClass}>
                {fotosExistentes.map((foto) => (
                    <div key={foto.id} className="relative group rounded-xl overflow-hidden border theme-border aspect-square bg-black/5">
                        <button type="button" onClick={() => setLightbox(urlFoto(foto))} className="w-full h-full">
                            <img src={urlFoto(foto)} alt={foto.nombre_original || 'Foto'} className="w-full h-full object-cover" />
                        </button>
                        {foto.es_principal && (
                            <span className="absolute top-1 left-1 text-[8px] font-black uppercase px-1.5 py-0.5 rounded bg-black/60 text-white">Principal</span>
                        )}
                        {editable && activoId && !subiendo && (
                            <button type="button" onClick={() => eliminarExistente(foto.id)} className="absolute top-1 right-1 p-1 rounded-full bg-red-600 text-white opacity-0 group-hover:opacity-100 transition-opacity">
                                <Trash2 className="w-3 h-3" />
                            </button>
                        )}
                    </div>
                ))}

                {!esDirecto && nuevasFotos.map((file, index) => (
                    <div key={`new-${index}`} className="relative rounded-xl overflow-hidden border theme-border aspect-square">
                        <img src={previewUrls[index] || URL.createObjectURL(file)} alt="" className="w-full h-full object-cover" />
                        <button type="button" onClick={() => quitarNueva(index)} className="absolute top-1 right-1 p-1 rounded-full bg-red-600 text-white">
                            <X className="w-3 h-3" />
                        </button>
                    </div>
                ))}

                {total === 0 && editable && (
                    <button
                        type="button"
                        onClick={() => !subiendo && inputRef.current?.click()}
                        disabled={subiendo}
                        className="aspect-square rounded-xl border-2 border-dashed theme-border flex flex-col items-center justify-center gap-2 theme-text-muted hover:theme-text-main transition-colors disabled:opacity-50 col-span-2"
                    >
                        <Camera className="w-8 h-8 opacity-40" />
                        <span className="text-[10px] font-black uppercase">Subir fotos</span>
                    </button>
                )}

                {total > 0 && editable && espacio > 0 && variant === 'hero' && (
                    <button
                        type="button"
                        onClick={() => !subiendo && inputRef.current?.click()}
                        disabled={subiendo}
                        className="aspect-square rounded-xl border-2 border-dashed theme-border flex flex-col items-center justify-center gap-1 theme-text-muted hover:theme-text-main transition-colors disabled:opacity-50"
                    >
                        <Upload className="w-6 h-6 opacity-40" />
                        <span className="text-[9px] font-black uppercase">+{espacio}</span>
                    </button>
                )}
            </div>

            {lightbox && (
                <div className="fixed inset-0 z-[200] bg-black/90 flex items-center justify-center p-4" onClick={() => setLightbox(null)}>
                    <img src={lightbox} alt="" className="max-w-full max-h-[90vh] object-contain rounded-xl" />
                </div>
            )}
        </div>
    );
}
