import React, { useRef, useEffect, useImperativeHandle, forwardRef } from 'react';
import { Eraser } from 'lucide-react';

const FirmaCanvas = forwardRef(function FirmaCanvas({ label, className = '' }, ref) {
    const canvasRef = useRef(null);
    const drawing = useRef(false);

    useImperativeHandle(ref, () => ({
        getDataUrl: () => {
            const canvas = canvasRef.current;
            if (!canvas) return null;
            const ctx = canvas.getContext('2d');
            const blank = document.createElement('canvas');
            blank.width = canvas.width;
            blank.height = canvas.height;
            if (canvas.toDataURL() === blank.toDataURL()) return null;
            return canvas.toDataURL('image/png');
        },
        clear: () => limpiar(),
    }));

    const getPos = (e, canvas) => {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: ((clientX - rect.left) / rect.width) * canvas.width,
            y: ((clientY - rect.top) / rect.height) * canvas.height,
        };
    };

    const limpiar = () => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    };

    useEffect(() => {
        limpiar();
    }, []);

    const start = (e) => {
        e.preventDefault();
        drawing.current = true;
        const canvas = canvasRef.current;
        const ctx = canvas.getContext('2d');
        const { x, y } = getPos(e, canvas);
        ctx.beginPath();
        ctx.moveTo(x, y);
    };

    const draw = (e) => {
        if (!drawing.current) return;
        e.preventDefault();
        const canvas = canvasRef.current;
        const ctx = canvas.getContext('2d');
        ctx.strokeStyle = '#111827';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        const { x, y } = getPos(e, canvas);
        ctx.lineTo(x, y);
        ctx.stroke();
    };

    const stop = () => {
        drawing.current = false;
    };

    return (
        <div className={className}>
            <div className="flex items-center justify-between mb-2">
                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
                <button type="button" onClick={limpiar} className="text-[9px] font-black uppercase theme-text-muted flex items-center gap-1">
                    <Eraser className="w-3 h-3" /> Limpiar
                </button>
            </div>
            <canvas
                ref={canvasRef}
                width={400}
                height={120}
                className="w-full rounded-2xl border theme-border bg-white touch-none cursor-crosshair"
                onMouseDown={start}
                onMouseMove={draw}
                onMouseUp={stop}
                onMouseLeave={stop}
                onTouchStart={start}
                onTouchMove={draw}
                onTouchEnd={stop}
            />
        </div>
    );
});

export default FirmaCanvas;
