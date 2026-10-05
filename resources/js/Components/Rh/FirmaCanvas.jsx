import React, { useRef, useEffect, useImperativeHandle, forwardRef, useState, useCallback } from 'react';
import { Eraser, PenTool } from 'lucide-react';
import { dibujarTrazoPluma } from '../../utils/signaturePen';

const FirmaCanvas = forwardRef(function FirmaCanvas({ label, className = '', height = 180 }, ref) {
    const canvasRef = useRef(null);
    const containerRef = useRef(null);
    const dibujando = useRef(false);
    const strokesRef = useRef([]);
    const currentStrokeRef = useRef([]);
    const tieneTrazoRef = useRef(false);
    const contenidoDataUrlRef = useRef(null);
    const [tieneTrazo, setTieneTrazo] = useState(false);

    const limpiarLienzo = useCallback((ctx, displayWidth, displayHeight) => {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, displayWidth, displayHeight);
    }, []);

    const redibujar = useCallback((ctx, displayWidth, displayHeight) => {
        limpiarLienzo(ctx, displayWidth, displayHeight);
        for (const stroke of strokesRef.current) {
            dibujarTrazoPluma(ctx, stroke);
        }
        if (currentStrokeRef.current.length > 0) {
            dibujarTrazoPluma(ctx, currentStrokeRef.current);
        }
    }, [limpiarLienzo]);

    const dibujarDataUrlEnLienzo = useCallback((ctx, dataUrl, displayWidth, displayHeight) => new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
            limpiarLienzo(ctx, displayWidth, displayHeight);
            const escala = Math.min(displayWidth / img.width, displayHeight / img.height);
            const ancho = img.width * escala;
            const alto = img.height * escala;
            const offsetX = (displayWidth - ancho) / 2;
            const offsetY = (displayHeight - alto) / 2;
            ctx.drawImage(img, offsetX, offsetY, ancho, alto);
            tieneTrazoRef.current = true;
            setTieneTrazo(true);
            resolve();
        };
        img.onerror = () => resolve();
        img.src = dataUrl;
    }), [limpiarLienzo]);

    const configurarCanvas = useCallback(() => {
        const canvas = canvasRef.current;
        const container = containerRef.current;
        if (!canvas || !container) return;

        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const displayWidth = container.clientWidth;
        const displayHeight = height;

        let dataUrlPersistido = contenidoDataUrlRef.current;
        if (!dibujando.current && tieneTrazoRef.current && canvas.width > 0 && canvas.height > 0) {
            const captura = canvas.toDataURL('image/png');
            if (captura) {
                dataUrlPersistido = captura;
                contenidoDataUrlRef.current = captura;
            }
        }

        canvas.width = Math.floor(displayWidth * dpr);
        canvas.height = Math.floor(displayHeight * dpr);
        canvas.style.width = `${displayWidth}px`;
        canvas.style.height = `${displayHeight}px`;

        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        if (dataUrlPersistido && strokesRef.current.length === 0) {
            dibujarDataUrlEnLienzo(ctx, dataUrlPersistido, displayWidth, displayHeight);
            return;
        }

        redibujar(ctx, displayWidth, displayHeight);
    }, [height, dibujarDataUrlEnLienzo, redibujar]);

    const limpiar = useCallback(() => {
        const canvas = canvasRef.current;
        const container = containerRef.current;
        if (!canvas || !container) return;
        const ctx = canvas.getContext('2d');
        strokesRef.current = [];
        currentStrokeRef.current = [];
        contenidoDataUrlRef.current = null;
        dibujando.current = false;
        limpiarLienzo(ctx, container.clientWidth, height);
        tieneTrazoRef.current = false;
        setTieneTrazo(false);
    }, [height, limpiarLienzo]);

    useImperativeHandle(ref, () => ({
        getDataUrl: () => {
            if (!tieneTrazoRef.current) return null;
            const canvas = canvasRef.current;
            return canvas ? canvas.toDataURL('image/png') : null;
        },
        loadDataUrl: (dataUrl) => {
            if (!dataUrl) {
                limpiar();
                return;
            }
            strokesRef.current = [];
            currentStrokeRef.current = [];
            contenidoDataUrlRef.current = dataUrl;
            const canvas = canvasRef.current;
            const container = containerRef.current;
            if (!canvas || !container) return;
            const ctx = canvas.getContext('2d');
            dibujarDataUrlEnLienzo(ctx, dataUrl, container.clientWidth, height);
        },
        clear: () => limpiar(),
        hasStroke: () => tieneTrazoRef.current,
    }), [dibujarDataUrlEnLienzo, height, limpiar]);

    useEffect(() => {
        configurarCanvas();

        const observer = new ResizeObserver(() => {
            if (!dibujando.current) {
                configurarCanvas();
            }
        });

        if (containerRef.current) {
            observer.observe(containerRef.current);
        }

        return () => observer.disconnect();
    }, [configurarCanvas]);

    const obtenerPunto = (e, canvas) => {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        const pressure = typeof e.pressure === 'number' && e.pressure > 0 ? e.pressure : 0.5;
        return [clientX - rect.left, clientY - rect.top, pressure];
    };

    const marcarTrazo = () => {
        if (!tieneTrazoRef.current) {
            tieneTrazoRef.current = true;
            setTieneTrazo(true);
        }
    };

    const iniciar = (e) => {
        if (e.cancelable) e.preventDefault();
        const canvas = canvasRef.current;
        const container = containerRef.current;
        if (!canvas || !container) return;

        contenidoDataUrlRef.current = null;
        currentStrokeRef.current = [obtenerPunto(e, canvas)];
        dibujando.current = true;
        marcarTrazo();

        const ctx = canvas.getContext('2d');
        redibujar(ctx, container.clientWidth, height);
    };

    const dibujar = (e) => {
        if (!dibujando.current) return;
        if (e.cancelable) e.preventDefault();

        const canvas = canvasRef.current;
        const container = containerRef.current;
        if (!canvas || !container) return;

        currentStrokeRef.current.push(obtenerPunto(e, canvas));
        const ctx = canvas.getContext('2d');
        redibujar(ctx, container.clientWidth, height);
        marcarTrazo();
    };

    const detener = () => {
        if (!dibujando.current) return;
        dibujando.current = false;
        if (currentStrokeRef.current.length > 0) {
            strokesRef.current.push([...currentStrokeRef.current]);
            currentStrokeRef.current = [];
        }
    };

    return (
        <div className={className}>
            <div className="flex items-center justify-between mb-2">
                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
                <button
                    type="button"
                    onClick={limpiar}
                    disabled={!tieneTrazo}
                    className="text-[9px] font-black uppercase theme-text-muted flex items-center gap-1 disabled:opacity-40"
                >
                    <Eraser className="w-3 h-3" /> Limpiar
                </button>
            </div>
            <div
                ref={containerRef}
                className="relative border theme-border rounded-xl bg-white dark:bg-slate-950 overflow-hidden"
            >
                <canvas
                    ref={canvasRef}
                    className="w-full touch-none cursor-crosshair block bg-white dark:bg-slate-950"
                    style={{ height: `${height}px` }}
                    onMouseDown={iniciar}
                    onMouseMove={dibujar}
                    onMouseUp={detener}
                    onMouseLeave={detener}
                    onTouchStart={iniciar}
                    onTouchMove={dibujar}
                    onTouchEnd={detener}
                />
                {!tieneTrazo && (
                    <div className="absolute inset-0 flex items-center justify-center pointer-events-none opacity-30 select-none">
                        <p className="text-xs text-slate-400 font-bold uppercase tracking-wider flex items-center gap-1 m-0">
                            <PenTool className="w-4 h-4" /> Dibuja la firma aquí
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
});

export default FirmaCanvas;
