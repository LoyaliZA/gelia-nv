import React, { useEffect, useRef, useState } from 'react';
import { Eraser, Image as ImageIcon, X } from 'lucide-react';
import BotonIconoMensajeria from './BotonIconoMensajeria';

const LADO = 512;

function coloresTema() {
    if (typeof window === 'undefined') return ['currentColor'];
    const estilo = getComputedStyle(document.documentElement);
    const leer = (nombre) => estilo.getPropertyValue(nombre).trim();
    const colores = [
        leer('--color-primario'),
        leer('--theme-text-main'),
        leer('--color-exito'),
        leer('--color-peligro'),
        leer('--color-info'),
    ].filter(Boolean);
    return colores.length ? colores : ['currentColor'];
}

function archivoDesdeCanvas(canvas) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (!blob) {
                reject(new Error('No se pudo exportar el sticker.'));
                return;
            }
            resolve(new File([blob], 'sticker.png', { type: 'image/png' }));
        }, 'image/png');
    });
}

export function recortarImagenCuadrada(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const imagen = new Image();
        imagen.onload = () => {
            const lado = Math.min(imagen.width, imagen.height);
            const canvas = document.createElement('canvas');
            const size = Math.min(LADO, lado);
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(
                imagen,
                (imagen.width - lado) / 2,
                (imagen.height - lado) / 2,
                lado,
                lado,
                0,
                0,
                size,
                size,
            );
            URL.revokeObjectURL(url);
            archivoDesdeCanvas(canvas).then(resolve).catch(reject);
        };
        imagen.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('No se pudo leer la imagen.'));
        };
        imagen.src = url;
    });
}

export default function CreadorSticker({ onGuardar, onCancelar, enviando = false }) {
    const canvasRef = useRef(null);
    const dibujando = useRef(false);
    const ultimo = useRef(null);
    const [modo, setModo] = useState('dibujar');
    const [color, setColor] = useState('');
    const [colores, setColores] = useState([]);
    const [goma, setGoma] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        const lista = coloresTema();
        setColores(lista);
        setColor(lista[0]);
    }, []);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, LADO, LADO);
    }, [modo]);

    const punto = (event) => {
        const canvas = canvasRef.current;
        const rect = canvas.getBoundingClientRect();
        const fuente = event.touches ? event.touches[0] : event;
        return {
            x: ((fuente.clientX - rect.left) / rect.width) * LADO,
            y: ((fuente.clientY - rect.top) / rect.height) * LADO,
        };
    };

    const trazar = (event) => {
        if (!dibujando.current || !ultimo.current) return;
        event.preventDefault();
        const ctx = canvasRef.current.getContext('2d');
        const actual = punto(event);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = goma ? 36 : 14;
        ctx.globalCompositeOperation = goma ? 'destination-out' : 'source-over';
        ctx.strokeStyle = color || 'currentColor';
        ctx.beginPath();
        ctx.moveTo(ultimo.current.x, ultimo.current.y);
        ctx.lineTo(actual.x, actual.y);
        ctx.stroke();
        ultimo.current = actual;
    };

    const iniciarTrazo = (event) => {
        dibujando.current = true;
        ultimo.current = punto(event);
    };

    const soltar = () => {
        dibujando.current = false;
        ultimo.current = null;
    };

    const cargarArchivo = async (file) => {
        if (!file) return;
        setError('');
        try {
            const recortado = await recortarImagenCuadrada(file);
            const url = URL.createObjectURL(recortado);
            const imagen = new Image();
            imagen.onload = () => {
                const ctx = canvasRef.current.getContext('2d');
                ctx.globalCompositeOperation = 'source-over';
                ctx.clearRect(0, 0, LADO, LADO);
                ctx.drawImage(imagen, 0, 0, LADO, LADO);
                URL.revokeObjectURL(url);
                setModo('dibujar');
            };
            imagen.src = url;
        } catch (e) {
            setError(e.message || 'No se pudo preparar la imagen.');
        }
    };

    const guardar = async (enviar) => {
        setError('');
        try {
            const archivo = await archivoDesdeCanvas(canvasRef.current);
            await onGuardar?.(archivo, enviar);
        } catch (e) {
            setError(e.message || 'No se pudo guardar el sticker.');
        }
    };

    return (
        <div className="gelia-sticker-creador">
            <div className="flex items-center justify-between gap-2 mb-3">
                <p className="m-0 text-sm font-semibold theme-text-main">Crear sticker</p>
                <BotonIconoMensajeria icon={X} label="Cerrar creador" onClick={onCancelar} className="rounded-full theme-text-muted" size={16} />
            </div>

            <canvas
                ref={canvasRef}
                width={LADO}
                height={LADO}
                className="gelia-sticker-lienzo"
                onPointerDown={iniciarTrazo}
                onPointerMove={trazar}
                onPointerUp={soltar}
                onPointerLeave={soltar}
            />

            <div className="flex flex-wrap items-center gap-2 mt-3">
                {colores.map((item) => (
                    <button
                        key={item}
                        type="button"
                        className={`gelia-sticker-color ${color === item && !goma ? 'gelia-sticker-color--activo' : ''}`}
                        style={{ background: item }}
                        aria-label="Elegir color"
                        onClick={() => {
                            setColor(item);
                            setGoma(false);
                        }}
                    />
                ))}
                <button
                    type="button"
                    className={`gelia-toque inline-flex items-center gap-1 rounded-full px-3 text-xs theme-element border theme-border ${goma ? 'border-[var(--color-primario)]' : ''}`}
                    onClick={() => setGoma((v) => !v)}
                >
                    <Eraser className="w-3.5 h-3.5" aria-hidden />
                    Goma
                </button>
                <label className="gelia-toque inline-flex items-center gap-1 rounded-full px-3 text-xs theme-element border theme-border cursor-pointer">
                    <ImageIcon className="w-3.5 h-3.5" aria-hidden />
                    Subir
                    <input
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        className="hidden"
                        onChange={(e) => cargarArchivo(e.target.files?.[0])}
                    />
                </label>
            </div>

            {error && <p className="m-0 mt-2 text-xs theme-text-peligro">{error}</p>}

            <div className="flex flex-wrap gap-2 mt-3">
                <button type="button" className="gelia-sticker-accion" disabled={enviando} onClick={() => guardar(false)}>
                    Guardar
                </button>
                <button type="button" className="gelia-sticker-accion gelia-sticker-accion--principal" disabled={enviando} onClick={() => guardar(true)}>
                    Guardar y enviar
                </button>
            </div>
        </div>
    );
}
