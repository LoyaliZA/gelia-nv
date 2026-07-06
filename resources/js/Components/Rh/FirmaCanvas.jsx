import React, { useRef, useEffect, useImperativeHandle, forwardRef, useState, useCallback } from 'react';
import { Eraser, PenTool } from 'lucide-react';

const STROKE_COLOR = '#1e3a8a';
const MIN_WIDTH = 1.0;
const MAX_WIDTH = 4.5;
const MAX_VELOCITY = 2.0;
const DEFAULT_WIDTH = 3.5;

const FirmaCanvas = forwardRef(function FirmaCanvas({ label, className = '', height = 180 }, ref) {
    const canvasRef = useRef(null);
    const containerRef = useRef(null);
    const dibujando = useRef(false);
    const lastPosRef = useRef({ x: 0, y: 0, time: 0, width: DEFAULT_WIDTH });
    const tieneTrazoRef = useRef(false);
    const [tieneTrazo, setTieneTrazo] = useState(false);

    const obtenerCoordenadas = useCallback((e, canvas) => {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - rect.left,
            y: clientY - rect.top,
        };
    }, []);

    const limpiarLienzo = useCallback((ctx, displayWidth, displayHeight) => {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, displayWidth, displayHeight);
    }, []);

    const configurarCanvas = useCallback(() => {
        const canvas = canvasRef.current;
        const container = containerRef.current;
        if (!canvas || !container) return;

        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const displayWidth = container.clientWidth;
        const displayHeight = height;

        canvas.width = Math.floor(displayWidth * dpr);
        canvas.height = Math.floor(displayHeight * dpr);
        canvas.style.width = `${displayWidth}px`;
        canvas.style.height = `${displayHeight}px`;

        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.strokeStyle = STROKE_COLOR;
        ctx.fillStyle = STROKE_COLOR;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        limpiarLienzo(ctx, displayWidth, displayHeight);

        lastPosRef.current = { x: 0, y: 0, time: 0, width: DEFAULT_WIDTH };
        tieneTrazoRef.current = false;
        setTieneTrazo(false);
    }, [height, limpiarLienzo]);

    useImperativeHandle(ref, () => ({
        getDataUrl: () => {
            if (!tieneTrazoRef.current) return null;
            const canvas = canvasRef.current;
            return canvas ? canvas.toDataURL('image/png') : null;
        },
        clear: () => limpiar(),
        hasStroke: () => tieneTrazoRef.current,
    }));

    const limpiar = () => {
        const canvas = canvasRef.current;
        const container = containerRef.current;
        if (!canvas || !container) return;
        const ctx = canvas.getContext('2d');
        limpiarLienzo(ctx, container.clientWidth, height);
        lastPosRef.current = { x: 0, y: 0, time: 0, width: DEFAULT_WIDTH };
        tieneTrazoRef.current = false;
        setTieneTrazo(false);
    };

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

    const marcarTrazo = () => {
        if (!tieneTrazoRef.current) {
            tieneTrazoRef.current = true;
            setTieneTrazo(true);
        }
    };

    const iniciar = (e) => {
        if (e.cancelable) e.preventDefault();
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const pos = obtenerCoordenadas(e, canvas);

        lastPosRef.current = {
            x: pos.x,
            y: pos.y,
            time: Date.now(),
            width: DEFAULT_WIDTH,
        };

        ctx.beginPath();
        ctx.arc(pos.x, pos.y, DEFAULT_WIDTH / 2, 0, 2 * Math.PI);
        ctx.fill();

        dibujando.current = true;
        marcarTrazo();
    };

    const dibujar = (e) => {
        if (!dibujando.current) return;
        if (e.cancelable) e.preventDefault();

        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const pos = obtenerCoordenadas(e, canvas);
        const lastPos = lastPosRef.current;

        const dx = pos.x - lastPos.x;
        const dy = pos.y - lastPos.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist === 0) return;

        const now = Date.now();
        const dt = Math.max(1, now - lastPos.time);
        const velocity = dist / dt;

        const targetWidth = Math.max(
            MIN_WIDTH,
            Math.min(MAX_WIDTH, MAX_WIDTH - (velocity / MAX_VELOCITY) * (MAX_WIDTH - MIN_WIDTH)),
        );
        const currentWidth = lastPos.width * 0.7 + targetWidth * 0.3;

        const midX = (lastPos.x + pos.x) / 2;
        const midY = (lastPos.y + pos.y) / 2;

        ctx.beginPath();
        ctx.moveTo(lastPos.x, lastPos.y);
        ctx.quadraticCurveTo(lastPos.x, lastPos.y, midX, midY);
        ctx.strokeStyle = STROKE_COLOR;
        ctx.lineWidth = currentWidth;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(midX, midY);
        ctx.lineTo(pos.x, pos.y);
        ctx.lineWidth = currentWidth;
        ctx.stroke();

        lastPosRef.current = {
            x: pos.x,
            y: pos.y,
            time: now,
            width: currentWidth,
        };

        marcarTrazo();
    };

    const detener = () => {
        dibujando.current = false;
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
