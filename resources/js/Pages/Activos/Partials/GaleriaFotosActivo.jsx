import React, { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Camera, ChevronDown, ChevronUp, Trash2, Upload, X } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import { compressImageToWebp, validateImageSource } from '../../../utils/compressImage';
import useDispositivoCampo from './useDispositivoCampo';
import LightboxFotos from './LightboxFotos';
import { BTN_SECONDARY_CLASS, BTN_TOUCH_CLASS, LABEL_CLASS } from './activosFormStyles';

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
    modoCapturaCampo = true,
    colapsada = false,
}) {
    const inputRef = useRef(null);
    const [previewUrls, setPreviewUrls] = useState([]);
    const [lightboxIndex, setLightboxIndex] = useState(null);
    const [expandida, setExpandida] = useState(!colapsada);
    const [subiendo, setSubiendo] = useState(false);
    const [comprimiendo, setComprimiendo] = useState(false);
    const [error, setError] = useState(null);
    const { esCampo } = useDispositivoCampo();

    const esDirecto = modo === 'directo';
    const capturaMovil = modoCapturaCampo && esCampo && !esDirecto;
    const total = fotosExistentes.length + (esDirecto ? 0 : nuevasFotos.length);
    const espacio = maxFotos - total;
    const ocupado = subiendo || comprimiendo;
    const mostrarColapsada = colapsada && !expandida;

    const urlFoto = (foto) => foto.url || `/storage/${foto.ruta}`;

    const urlsExistentes = fotosExistentes.map(urlFoto);
    const urlsNuevas = esDirecto ? [] : previewUrls;
    const todasLasUrls = [...urlsExistentes, ...urlsNuevas];

    const indicePrincipal = (() => {
        const idx = fotosExistentes.findIndex((f) => f.es_principal);
        return idx >= 0 ? idx : 0;
    })();

    const miniaturaUrl = urlsExistentes[indicePrincipal] || urlsExistentes[0] || urlsNuevas[0] || null;

    const comprimirLista = async (files) => {
        setComprimiendo(true);
        const comprimidos = [];

        try {
            for (const file of files) {
                const msg = validateImageSource(file, 'Fotografía');
                if (msg) {
                    setError(msg);
                    continue;
                }
                const comprimido = await compressImageToWebp(file, {
                    maxDimension: 1600,
                    maxBytes: 800 * 1024,
                    quality: 0.82,
                });
                comprimidos.push(comprimido);
            }
        } catch {
            setError('No se pudo comprimir una o más fotografías.');
        } finally {
            setComprimiendo(false);
        }

        return comprimidos;
    };

    const validarCantidad = (lista) => {
        if (!lista.length) return [];

        if (capturaMovil && lista.length > 1) {
            setError('Toma una fotografía a la vez en dispositivos móviles.');
            return lista.slice(0, 1);
        }

        if (lista.length > espacio) {
            setError(`Máximo ${maxFotos} fotografías por activo. Espacio disponible: ${espacio}.`);
            return lista.slice(0, espacio);
        }

        setError(null);
        return lista;
    };

    const subirDirecto = async (files) => {
        const lista = validarCantidad(Array.from(files || []));
        if (!lista.length || !activoId) return;

        const comprimidos = await comprimirLista(lista);
        if (!comprimidos.length) return;

        setSubiendo(true);
        router.post(
            route('activos.fotos.store', activoId),
            { fotos: comprimidos },
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

    const handleFiles = async (files) => {
        const lista = validarCantidad(Array.from(files || []));
        if (!lista.length) return;

        if (esDirecto) {
            await subirDirecto(lista);
            return;
        }

        const comprimidos = await comprimirLista(lista);
        if (!comprimidos.length) return;

        const merged = [...nuevasFotos, ...comprimidos];
        onChangeNuevas?.(merged);

        comprimidos.forEach((file) => {
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

    const abrirLightbox = (index = 0) => {
        if (!todasLasUrls.length) return;
        setLightboxIndex(index);
    };

    const labelSubir = capturaMovil ? 'Tomar foto' : 'Subir fotos';

    const wrapperClass = variant === 'hero'
        ? 'space-y-3'
        : 'border-t theme-border pt-4 space-y-3';

    const gridClass = variant === 'hero'
        ? 'grid grid-cols-2 gap-3'
        : 'grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3';

    return (
        <div className={`relative ${wrapperClass}`}>
            <GeliaLoader isVisible={ocupado} message={comprimiendo ? 'Comprimiendo_' : 'Subiendo fotos_'} />

            {mostrarColapsada ? (
                <div className="flex items-center gap-3">
                    {miniaturaUrl ? (
                        <button
                            type="button"
                            onClick={() => abrirLightbox(indicePrincipal >= 0 && indicePrincipal < urlsExistentes.length ? indicePrincipal : 0)}
                            className="relative shrink-0 w-16 h-16 rounded-xl overflow-hidden border theme-border bg-black/5"
                        >
                            <img src={miniaturaUrl} alt="" className="w-full h-full object-cover" />
                            {total > 1 && (
                                <span className="absolute bottom-0 inset-x-0 text-[8px] font-black uppercase text-center bg-black/60 text-white py-0.5">
                                    +{total - 1}
                                </span>
                            )}
                        </button>
                    ) : (
                        <div className="shrink-0 w-16 h-16 rounded-xl border-2 border-dashed theme-border flex items-center justify-center">
                            <Camera className="w-5 h-5 theme-text-muted opacity-40" />
                        </div>
                    )}

                    <div className="flex-1 min-w-0">
                        <p className={`${LABEL_CLASS} m-0 truncate`}>Fotografías ({total}/{maxFotos})</p>
                        {total > 1 && (
                            <span className="inline-block mt-1 text-[8px] font-black uppercase px-1.5 py-0.5 rounded bg-black/10 dark:bg-white/10 theme-text-muted">
                                {total} fotos
                            </span>
                        )}
                    </div>

                    <button
                        type="button"
                        onClick={() => setExpandida(true)}
                        className={`${BTN_SECONDARY_CLASS} flex items-center gap-1 text-[10px] shrink-0`}
                    >
                        Ver fotos
                        <ChevronDown className="w-3 h-3" />
                    </button>
                </div>
            ) : (
                <>
                    <div className="flex items-center justify-between">
                        <p className={LABEL_CLASS}>Fotografías ({total}/{maxFotos})</p>
                        <div className="flex items-center gap-2">
                            {colapsada && (
                                <button
                                    type="button"
                                    onClick={() => setExpandida(false)}
                                    className={`${BTN_SECONDARY_CLASS} flex items-center gap-1 text-[10px]`}
                                >
                                    Contraer
                                    <ChevronUp className="w-3 h-3" />
                                </button>
                            )}
                            {editable && espacio > 0 && (
                                <button
                                    type="button"
                                    onClick={() => inputRef.current?.click()}
                                    disabled={ocupado}
                                    className={`${capturaMovil ? BTN_TOUCH_CLASS : BTN_SECONDARY_CLASS} flex items-center gap-1 text-[10px] disabled:opacity-50`}
                                >
                                    {capturaMovil ? <Camera className="w-4 h-4" /> : <Upload className="w-3 h-3" />}
                                    {capturaMovil ? labelSubir : (esDirecto ? 'Subir' : 'Agregar')}
                                </button>
                            )}
                        </div>
                    </div>

                    {error && (
                        <p className="text-[10px] font-bold text-red-600 dark:text-red-400">{error}</p>
                    )}

                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/jpg"
                        multiple={!capturaMovil}
                        capture={capturaMovil ? 'environment' : undefined}
                        className="hidden"
                        disabled={ocupado}
                        onChange={(e) => { handleFiles(e.target.files); e.target.value = ''; }}
                    />

                    <div className={gridClass}>
                        {fotosExistentes.map((foto, index) => (
                            <div key={foto.id} className="relative group rounded-xl overflow-hidden border theme-border aspect-square bg-black/5">
                                <button type="button" onClick={() => abrirLightbox(index)} className="w-full h-full">
                                    <img src={urlFoto(foto)} alt={foto.nombre_original || 'Foto'} className="w-full h-full object-cover" />
                                </button>
                                {foto.es_principal && (
                                    <span className="absolute top-1 left-1 text-[8px] font-black uppercase px-1.5 py-0.5 rounded bg-black/60 text-white">Principal</span>
                                )}
                                {editable && activoId && !ocupado && (
                                    <button type="button" onClick={() => eliminarExistente(foto.id)} className="absolute top-1 right-1 p-1 rounded-full bg-red-600 text-white opacity-0 group-hover:opacity-100 transition-opacity">
                                        <Trash2 className="w-3 h-3" />
                                    </button>
                                )}
                            </div>
                        ))}

                        {!esDirecto && nuevasFotos.map((file, index) => (
                            <div key={`new-${index}`} className="relative rounded-xl overflow-hidden border theme-border aspect-square">
                                <button type="button" onClick={() => abrirLightbox(urlsExistentes.length + index)} className="w-full h-full">
                                    <img src={previewUrls[index] || URL.createObjectURL(file)} alt="" className="w-full h-full object-cover" />
                                </button>
                                <button type="button" onClick={() => quitarNueva(index)} className="absolute top-1 right-1 p-1 rounded-full bg-red-600 text-white">
                                    <X className="w-3 h-3" />
                                </button>
                            </div>
                        ))}

                        {total === 0 && editable && (
                            <button
                                type="button"
                                onClick={() => !ocupado && inputRef.current?.click()}
                                disabled={ocupado}
                                className={`aspect-square rounded-xl border-2 border-dashed theme-border flex flex-col items-center justify-center gap-2 theme-text-muted hover:theme-text-main transition-colors disabled:opacity-50 col-span-2 ${capturaMovil ? 'min-h-[120px]' : ''}`}
                            >
                                <Camera className="w-8 h-8 opacity-40" />
                                <span className="text-[10px] font-black uppercase">{labelSubir}</span>
                            </button>
                        )}

                        {total > 0 && editable && espacio > 0 && variant === 'hero' && (
                            <button
                                type="button"
                                onClick={() => !ocupado && inputRef.current?.click()}
                                disabled={ocupado}
                                className="aspect-square rounded-xl border-2 border-dashed theme-border flex flex-col items-center justify-center gap-1 theme-text-muted hover:theme-text-main transition-colors disabled:opacity-50"
                            >
                                {capturaMovil ? <Camera className="w-6 h-6 opacity-40" /> : <Upload className="w-6 h-6 opacity-40" />}
                                <span className="text-[9px] font-black uppercase">{capturaMovil ? '+ Foto' : `+${espacio}`}</span>
                            </button>
                        )}
                    </div>
                </>
            )}

            {mostrarColapsada && editable && espacio > 0 && (
                <>
                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/jpg"
                        multiple={!capturaMovil}
                        capture={capturaMovil ? 'environment' : undefined}
                        className="hidden"
                        disabled={ocupado}
                        onChange={(e) => { handleFiles(e.target.files); e.target.value = ''; }}
                    />
                    {error && (
                        <p className="text-[10px] font-bold text-red-600 dark:text-red-400 mt-2">{error}</p>
                    )}
                </>
            )}

            {lightboxIndex !== null && todasLasUrls.length > 0 && (
                <LightboxFotos
                    fotos={todasLasUrls}
                    indiceInicial={lightboxIndex}
                    onCerrar={() => setLightboxIndex(null)}
                />
            )}
        </div>
    );
}
