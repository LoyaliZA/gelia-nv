import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Crop } from 'lucide-react';
import {
    blobToFile,
    cropAndResizeToBlob,
    fitOutputSize,
    squareCropNatural,
} from '../../../utils/editImageCanvas';

/**
 * Editor simple: recorte libre + presets 1280 cuadrado / lado máx 1280.
 */
export default function ImageEditModal({ file, onClose, onSave }) {
    const imgRef = useRef(null);
    const [objectUrl, setObjectUrl] = useState(null);
    const [natural, setNatural] = useState({ w: 0, h: 0 });
    const [crop, setCrop] = useState({ x: 0, y: 0, w: 0, h: 0 });
    const [drag, setDrag] = useState(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [display, setDisplay] = useState({ w: 0, h: 0 });

    useEffect(() => {
        const url = URL.createObjectURL(file);
        setObjectUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    const scale = useMemo(() => {
        if (!natural.w || !display.w) return 1;
        return display.w / natural.w;
    }, [natural.w, display.w]);

    const onImgLoad = (e) => {
        const img = e.target;
        const nw = img.naturalWidth;
        const nh = img.naturalHeight;
        setNatural({ w: nw, h: nh });
        setCrop({ x: 0, y: 0, w: nw, h: nh });
        setDisplay({ w: img.clientWidth, h: img.clientHeight });
    };

    useEffect(() => {
        const onResize = () => {
            if (imgRef.current) {
                setDisplay({ w: imgRef.current.clientWidth, h: imgRef.current.clientHeight });
            }
        };
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, []);

    const clampCrop = (c) => {
        const w = Math.max(20, Math.min(c.w, natural.w));
        const h = Math.max(20, Math.min(c.h, natural.h));
        const x = Math.max(0, Math.min(c.x, natural.w - w));
        const y = Math.max(0, Math.min(c.y, natural.h - h));
        return { x, y, w, h };
    };

    const applySquare1280 = () => {
        const c = squareCropNatural(natural.w, natural.h);
        setCrop(c);
    };

    const applyFit1280 = () => {
        setCrop({ x: 0, y: 0, w: natural.w, h: natural.h });
    };

    const applyFull = () => {
        setCrop({ x: 0, y: 0, w: natural.w, h: natural.h });
    };

    const onPointerDown = (e, mode) => {
        e.preventDefault();
        e.stopPropagation();
        const rect = imgRef.current.getBoundingClientRect();
        setDrag({
            mode,
            startX: e.clientX,
            startY: e.clientY,
            origin: { ...crop },
            rect,
        });
    };

    useEffect(() => {
        if (!drag) return undefined;

        const onMove = (e) => {
            const dx = (e.clientX - drag.startX) / scale;
            const dy = (e.clientY - drag.startY) / scale;
            if (drag.mode === 'move') {
                setCrop(clampCrop({
                    ...drag.origin,
                    x: drag.origin.x + dx,
                    y: drag.origin.y + dy,
                }));
            } else if (drag.mode === 'se') {
                setCrop(clampCrop({
                    ...drag.origin,
                    w: drag.origin.w + dx,
                    h: drag.origin.h + dy,
                }));
            }
        };
        const onUp = () => setDrag(null);
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
        return () => {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
        };
    }, [drag, scale, natural]);

    const guardar = async (preset) => {
        if (!imgRef.current || !natural.w) return;
        setSaving(true);
        setError(null);
        try {
            let c = { ...crop };
            let outW;
            let outH;

            if (preset === 'square1280') {
                c = squareCropNatural(natural.w, natural.h);
                outW = 1280;
                outH = 1280;
            } else if (preset === 'fit1280') {
                c = { x: 0, y: 0, w: natural.w, h: natural.h };
                const fit = fitOutputSize(natural.w, natural.h, 1280);
                outW = fit.outW;
                outH = fit.outH;
            } else {
                // Usar recorte actual; si es cuadrado y grande, bajar a 1280
                if (Math.abs(c.w - c.h) < 2 && Math.max(c.w, c.h) > 1280) {
                    outW = 1280;
                    outH = 1280;
                } else if (Math.max(c.w, c.h) > 1280) {
                    const fit = fitOutputSize(c.w, c.h, 1280);
                    outW = fit.outW;
                    outH = fit.outH;
                } else {
                    outW = Math.round(c.w);
                    outH = Math.round(c.h);
                }
            }

            const blob = await cropAndResizeToBlob(imgRef.current, c, { outW, outH, mime: 'image/webp' });
            const nextFile = await blobToFile(blob, file.name);
            onSave?.(nextFile);
            onClose?.();
        } catch (err) {
            setError(err.message || 'No se pudo editar');
        } finally {
            setSaving(false);
        }
    };

    const boxStyle = {
        left: `${crop.x * scale}px`,
        top: `${crop.y * scale}px`,
        width: `${crop.w * scale}px`,
        height: `${crop.h * scale}px`,
    };

    return createPortal(
        <div className="fixed inset-0 z-[220] flex items-center justify-center p-4 bg-black/70 backdrop-blur-md">
            <div className="w-full max-w-3xl theme-surface border theme-border rounded-[2rem] p-5 md:p-8 max-h-[92vh] overflow-y-auto relative space-y-4">
                <button type="button" onClick={onClose} className="absolute top-5 right-5 p-2 theme-text-muted hover:theme-text-main">
                    <X className="w-5 h-5" />
                </button>

                <div className="flex items-center gap-2 pr-10">
                    <Crop className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    <h2 className="text-lg font-black italic uppercase theme-text-main">Editar imagen</h2>
                </div>
                <p className="text-xs theme-text-muted">
                    Arrastra el recuadro o usa un preset. Luego aplica antes de subir.
                    {natural.w ? ` · Original ${natural.w}×${natural.h}` : ''}
                </p>

                <div className="flex flex-wrap gap-2">
                    <button type="button" onClick={applySquare1280} className="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase border theme-border theme-text-main">
                        Cuadrado centrado
                    </button>
                    <button type="button" onClick={applyFit1280} className="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase border theme-border theme-text-main">
                        Imagen completa
                    </button>
                    <button type="button" onClick={applyFull} className="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase border theme-border theme-text-main">
                        Reset
                    </button>
                </div>

                <div className="relative inline-block max-w-full mx-auto select-none">
                    {objectUrl && (
                        <img
                            ref={imgRef}
                            src={objectUrl}
                            alt=""
                            onLoad={onImgLoad}
                            className="max-h-[50vh] max-w-full rounded-xl block"
                            draggable={false}
                        />
                    )}
                    {natural.w > 0 && (
                        <div
                            className="absolute border-2 border-amber-400 bg-amber-400/10 cursor-move"
                            style={boxStyle}
                            onPointerDown={(e) => onPointerDown(e, 'move')}
                        >
                            <div
                                className="absolute right-0 bottom-0 w-4 h-4 bg-amber-400 cursor-se-resize"
                                onPointerDown={(e) => onPointerDown(e, 'se')}
                            />
                        </div>
                    )}
                </div>

                <p className="text-[10px] font-mono theme-text-muted">
                    Recorte: {Math.round(crop.w)}×{Math.round(crop.h)} @ ({Math.round(crop.x)},{Math.round(crop.y)})
                </p>

                {error && <p className="text-xs font-bold text-red-500">{error}</p>}

                <div className="flex flex-wrap justify-end gap-2 pt-2">
                    <button
                        type="button"
                        disabled={saving || !natural.w}
                        onClick={() => guardar('fit1280')}
                        className="px-4 py-2 rounded-xl text-[10px] font-black uppercase border theme-border theme-text-main disabled:opacity-50"
                    >
                        Aplicar fit 1280
                    </button>
                    <button
                        type="button"
                        disabled={saving || !natural.w}
                        onClick={() => guardar('square1280')}
                        className="px-4 py-2 rounded-xl text-[10px] font-black uppercase border theme-border theme-text-main disabled:opacity-50"
                    >
                        Aplicar 1280×1280
                    </button>
                    <button
                        type="button"
                        disabled={saving || !natural.w}
                        onClick={() => guardar('crop')}
                        className="px-4 py-2 rounded-xl text-[10px] font-black uppercase text-white disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        {saving ? 'Guardando…' : 'Aplicar recorte'}
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
