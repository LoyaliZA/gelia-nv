import React, { useRef, useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { PenTool, RotateCcw, Check } from 'lucide-react';
import ActivosModalShell from './ActivosModalShell';
import GeliaLoader from '../../../Components/GeliaLoader';
import { LABEL_CLASS, BTN_PRIMARY_CLASS, BTN_SECONDARY_CLASS } from './activosFormStyles';

export default function ModalFirmarActivo({ abierto, onCerrar, asignacion, terminosCondiciones }) {
    const canvasRef = useRef(null);
    const lastPosRef = useRef({ x: 0, y: 0, time: 0, width: 3.5 });
    const [dibujando, setDibujando] = useState(false);
    const [tieneTrazo, setTieneTrazo] = useState(false);
    const [procesando, setProcesando] = useState(false);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.strokeStyle = '#1e3a8a'; // Azul marino elegante
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        // Limpiar lienzo al abrir modal
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        setTieneTrazo(false);
    }, [abierto]);

    if (!abierto || !asignacion) return null;

    const obtenerCoordenadas = (e) => {
        const canvas = canvasRef.current;
        if (!canvas) return { x: 0, y: 0 };
        const rect = canvas.getBoundingClientRect();
        
        // Soporte para touch y mouse
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        
        // Ajustar coordenadas para la resolución real del canvas
        const x = ((clientX - rect.left) / rect.width) * canvas.width;
        const y = ((clientY - rect.top) / rect.height) * canvas.height;
        
        return { x, y };
    };

    const iniciarDibujo = (e) => {
        if (e.cancelable) e.preventDefault();
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const pos = obtenerCoordenadas(e);

        // Inicializar ref de trazo
        lastPosRef.current = {
            x: pos.x,
            y: pos.y,
            time: Date.now(),
            width: 3.5
        };

        // Dibujar punto inicial
        ctx.beginPath();
        ctx.arc(pos.x, pos.y, 1.75, 0, 2 * Math.PI);
        ctx.fillStyle = '#1e3a8a';
        ctx.fill();

        setDibujando(true);
    };

    const dibujar = (e) => {
        if (!dibujando) return;
        if (e.cancelable) e.preventDefault();
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const pos = obtenerCoordenadas(e);

        const lastPos = lastPosRef.current;
        const dx = pos.x - lastPos.x;
        const dy = pos.y - lastPos.y;
        const dist = Math.sqrt(dx * dx + dy * dy);

        if (dist === 0) return;

        const now = Date.now();
        const dt = Math.max(1, now - lastPos.time);
        const velocity = dist / dt;

        // Parámetros de flujo de tinta tipo pluma
        const MIN_WIDTH = 1.0;
        const MAX_WIDTH = 4.5;
        const MAX_VELOCITY = 2.0;

        const targetWidth = Math.max(
            MIN_WIDTH,
            Math.min(MAX_WIDTH, MAX_WIDTH - (velocity / MAX_VELOCITY) * (MAX_WIDTH - MIN_WIDTH))
        );

        // Suavizado Lerp
        const currentWidth = lastPos.width * 0.7 + targetWidth * 0.3;

        ctx.beginPath();
        ctx.moveTo(lastPos.x, lastPos.y);
        ctx.lineTo(pos.x, pos.y);
        ctx.strokeStyle = '#1e3a8a';
        ctx.lineWidth = currentWidth;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();

        lastPosRef.current = {
            x: pos.x,
            y: pos.y,
            time: now,
            width: currentWidth
        };

        setTieneTrazo(true);
    };

    const detenerDibujo = () => {
        setDibujando(false);
    };

    const limpiar = () => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        setTieneTrazo(false);
    };

    const submit = (e) => {
        e.preventDefault();
        if (!tieneTrazo) return;

        const canvas = canvasRef.current;
        const dataUrl = canvas.toDataURL('image/png');

        setProcesando(true);
        router.post(
            route('activos.asignaciones.firmar', asignacion.id),
            { firma: dataUrl },
            {
                onSuccess: () => {
                    setProcesando(false);
                    onCerrar();
                },
                onError: () => {
                    setProcesando(false);
                },
                onFinish: () => {
                    setProcesando(false);
                }
            }
        );
    };

    return (
        <ActivosModalShell
            title="Firmar Recibido de Activo"
            onClose={onCerrar}
            loader={<GeliaLoader isVisible={procesando} message="Guardando firma_" />}
        >
            <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                <div className="gelia-modal-body p-5 md:p-8 space-y-4 overflow-y-auto">
                    <p className="text-sm theme-text-muted m-0">
                        Estás firmando de recibido para el activo:{' '}
                        <strong className="theme-text-main">
                            {asignacion.activo?.folio} — {asignacion.activo?.nombre}
                        </strong>
                    </p>

                    {asignacion.condiciones_entrega && (
                        <div className="p-3 rounded-xl border border-blue-100 bg-blue-50/30 dark:border-blue-900/30 dark:bg-blue-950/10">
                            <span className="text-[10px] font-black uppercase text-blue-600 dark:text-blue-400 block mb-1">
                                Condiciones de Entrega Registradas
                            </span>
                            <p className="text-xs theme-text-main m-0 italic">
                                "{asignacion.condiciones_entrega}"
                            </p>
                        </div>
                    )}

                    <div className="p-4 rounded-xl border theme-border bg-black/[0.01] dark:bg-white/[0.01] text-justify">
                        <span className="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 block mb-2">
                            Compromiso y Cláusula Legal
                        </span>
                        <p className="text-[10px] theme-text-muted m-0 leading-relaxed whitespace-pre-line">
                            {terminosCondiciones || "Al firmar este documento, el colaborador se compromete a regresar los activos asignados en las mismas condiciones en las que fueron entregados, salvo por el desgaste natural de su uso correcto. En caso de presentar daños parciales o totales causados por negligencia, descuido o mal uso, el colaborador acepta la responsabilidad de cubrir la totalidad del costo de mantenimiento, reparación o resarcición completa del activo."}
                        </p>
                    </div>

                    <div className="flex flex-col gap-2">
                        <label className={LABEL_CLASS}>Firma en el recuadro *</label>
                        <div className="border theme-border rounded-xl bg-white dark:bg-slate-900 p-1 relative overflow-hidden">
                            <canvas
                                ref={canvasRef}
                                width={500}
                                height={220}
                                className="w-full h-[180px] sm:h-[220px] touch-none cursor-crosshair bg-white dark:bg-slate-950 rounded-lg"
                                onMouseDown={iniciarDibujo}
                                onMouseMove={dibujar}
                                onMouseUp={detenerDibujo}
                                onMouseLeave={detenerDibujo}
                                onTouchStart={iniciarDibujo}
                                onTouchMove={dibujar}
                                onTouchEnd={detenerDibujo}
                            />
                            {!tieneTrazo && (
                                <div className="absolute inset-0 flex items-center justify-center pointer-events-none opacity-30 select-none">
                                    <p className="text-xs text-slate-400 font-bold uppercase tracking-wider flex items-center gap-1">
                                        <PenTool className="w-4 h-4" /> Dibuja tu firma aquí
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
                <div className="gelia-modal-footer p-5 md:p-6 flex gap-3 shrink-0">
                    <button
                        type="button"
                        onClick={limpiar}
                        className={`${BTN_SECONDARY_CLASS} flex-1 justify-center`}
                        disabled={!tieneTrazo || procesando}
                    >
                        <RotateCcw className="w-4 h-4 shrink-0" /> Limpiar
                    </button>
                    <button
                        type="submit"
                        className={`${BTN_PRIMARY_CLASS} flex-1 justify-center`}
                        disabled={!tieneTrazo || procesando}
                    >
                        <Check className="w-4 h-4 shrink-0" /> Confirmar firma
                    </button>
                </div>
            </form>
        </ActivosModalShell>
    );
}
