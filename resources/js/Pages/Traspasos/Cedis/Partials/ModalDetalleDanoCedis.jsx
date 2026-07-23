import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, AlertTriangle, Camera, ImagePlus, Trash2 } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_LABEL } from '../../../../utils/geliaTheme';

export default function ModalDetalleDanoCedis({ traspaso, producto, onClose, onExito }) {
    const [previews, setPreviews] = useState([]);
    const { data, setData, post, processing, errors, reset } = useForm({
        solicitud_traspaso_producto_id: producto.id,
        motivo: '',
        fotos: [],
    });

    const agregarFotos = (lista) => {
        const nuevos = Array.from(lista || []).filter((f) => f.type?.startsWith('image/'));
        if (nuevos.length === 0) return;
        const merged = [...(data.fotos || []), ...nuevos].slice(0, 8);
        setData('fotos', merged);
        setPreviews((prev) => {
            const extra = nuevos.map((f) => URL.createObjectURL(f));
            return [...prev, ...extra].slice(0, 8);
        });
    };

    const quitar = (idx) => {
        setData('fotos', (data.fotos || []).filter((_, i) => i !== idx));
        setPreviews((prev) => {
            const copy = [...prev];
            const [removed] = copy.splice(idx, 1);
            if (removed) URL.revokeObjectURL(removed);
            return copy;
        });
    };

    const cerrar = () => {
        previews.forEach((u) => URL.revokeObjectURL(u));
        reset();
        setPreviews([]);
        onClose();
    };

    const enviar = (e) => {
        e.preventDefault();
        post(route('traspasos.cedis.detalle_dano', traspaso.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                previews.forEach((u) => URL.revokeObjectURL(u));
                onExito?.();
                onClose();
            },
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-end sm:items-center p-0 sm:p-4`} onClick={cerrar}>
            <div
                className={`${THEME_MODAL_SHELL} w-full max-w-lg max-h-[92dvh] flex flex-col rounded-t-3xl sm:rounded-3xl`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="min-w-0">
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                            <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0" />
                            Detalle / daño
                        </h2>
                        <p className="text-xs theme-text-muted font-bold mt-1 m-0">{traspaso.folio}</p>
                        <p className="text-sm font-black theme-text-main mt-2 m-0 tabular-nums break-all">{producto.sku}</p>
                        <p className="text-sm font-bold theme-text-main m-0 mt-0.5 leading-snug break-words">{producto.descripcion}</p>
                    </div>
                    <button type="button" onClick={cerrar} className="p-3 rounded-2xl theme-element border theme-border outline-none" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={enviar} className="flex-1 overflow-y-auto p-5 space-y-4 custom-scrollbar">
                    <div>
                        <label className={THEME_LABEL}>Describe el detalle o daño de este producto</label>
                        <textarea
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            rows={4}
                            className="w-full rounded-2xl border theme-border theme-element px-4 py-3 text-base font-bold theme-text-main outline-none"
                            placeholder="Ej. Rayadura en tapa, derrame en caja…"
                            required
                        />
                        {errors.motivo && <p className="text-xs text-red-500 font-bold mt-1 m-0">{errors.motivo}</p>}
                        {errors.solicitud_traspaso_producto_id && (
                            <p className="text-xs text-red-500 font-bold mt-1 m-0">{errors.solicitud_traspaso_producto_id}</p>
                        )}
                    </div>

                    <div>
                        <p className={`${THEME_LABEL} mb-2`}>Fotografías (cámara o galería)</p>
                        <div className="flex flex-col gap-3">
                            <label className="flex flex-col items-center justify-center gap-2 min-h-[7rem] rounded-2xl border-2 border-dashed theme-border px-4 py-6 cursor-pointer active:scale-[0.99] transition-transform">
                                <Camera className="w-8 h-8" style={{ color: 'var(--color-primario)' }} />
                                <span className="text-xs font-black uppercase tracking-widest theme-text-main">Tomar foto</span>
                                <input
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    className="hidden"
                                    onChange={(e) => {
                                        agregarFotos(e.target.files);
                                        e.target.value = '';
                                    }}
                                />
                            </label>
                            <label className="inline-flex items-center justify-center gap-2 py-3 rounded-2xl border theme-border theme-element text-xs font-black uppercase tracking-widest cursor-pointer">
                                <ImagePlus className="w-4 h-4" /> Galería
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    className="hidden"
                                    onChange={(e) => {
                                        agregarFotos(e.target.files);
                                        e.target.value = '';
                                    }}
                                />
                            </label>
                        </div>
                        {errors.fotos && <p className="text-xs text-red-500 font-bold mt-1 m-0">{errors.fotos}</p>}
                        {errors['fotos.0'] && <p className="text-xs text-red-500 font-bold mt-1 m-0">{errors['fotos.0']}</p>}

                        {previews.length > 0 && (
                            <div className="grid grid-cols-3 gap-2 mt-3">
                                {previews.map((url, idx) => (
                                    <div key={url} className="relative aspect-square rounded-xl overflow-hidden border theme-border">
                                        <img src={url} alt="" className="w-full h-full object-cover" />
                                        <button
                                            type="button"
                                            onClick={() => quitar(idx)}
                                            className="absolute top-1 right-1 p-1.5 rounded-lg bg-black/60 text-white outline-none"
                                            aria-label="Quitar foto"
                                        >
                                            <Trash2 className="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full py-4 rounded-2xl text-white text-sm font-black uppercase tracking-widest outline-none disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        {processing ? 'Enviando…' : 'Reportar detalle/daño'}
                    </button>
                </form>
            </div>
        </div>,
        document.body,
    );
}
