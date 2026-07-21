import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, PackageCheck, ImagePlus, Camera, Trash2 } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../../utils/geliaTheme';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import { esDispositivoCampo } from '../../../Activos/Partials/useDispositivoCampo';

export default function ModalMarcarApartadoResguardo({ abierto, onClose, pedido }) {
    const inputRef = useRef(null);
    const [archivos, setArchivos] = useState([]);
    const [previews, setPreviews] = useState([]);
    const [detalle, setDetalle] = useState('');
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const [esMovil, setEsMovil] = useState(false);

    useEffect(() => {
        if (abierto) {
            setArchivos([]);
            setPreviews((prev) => {
                prev.forEach((p) => URL.revokeObjectURL(p.url));
                return [];
            });
            setDetalle('');
            setError('');
            setProcesando(false);
            setEsMovil(esDispositivoCampo());
        }
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const agregarArchivos = (lista) => {
        const nuevos = Array.from(lista || []).filter((f) => f.type?.startsWith('image/'));
        if (nuevos.length === 0) return;
        setArchivos((prev) => [...prev, ...nuevos].slice(0, 8));
        setPreviews((prev) => [
            ...prev,
            ...nuevos.map((f) => ({ name: f.name, url: URL.createObjectURL(f) })),
        ].slice(0, 8));
    };

    const quitar = (idx) => {
        setArchivos((prev) => prev.filter((_, i) => i !== idx));
        setPreviews((prev) => {
            const copy = [...prev];
            const [removed] = copy.splice(idx, 1);
            if (removed?.url) URL.revokeObjectURL(removed.url);
            return copy;
        });
    };

    const enviar = (e) => {
        e.preventDefault();
        if (archivos.length === 0) {
            setError('Adjunta al menos una foto del apartado.');
            return;
        }
        setProcesando(true);
        setError('');
        router.post(
            route('control_pedidos.cedis.marcar_resguardo_apartado', pedido.id),
            { evidencias: archivos, detalle: detalle.trim() || null },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errors) => {
                    setError(errors.evidencias || errors['evidencias.0'] || errors.detalle || 'No se pudo marcar como apartado.');
                },
                onFinish: () => setProcesando(false),
            }
        );
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center py-4`} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} max-w-lg w-full`} onClick={(e) => e.stopPropagation()}>
                <div className="p-5 border-b theme-border flex justify-between items-start gap-3">
                    <div>
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                            <PackageCheck className="w-5 h-5 text-sky-600" />
                            Marcar apartado
                        </h2>
                        <EncabezadoFolioPedido pedido={pedido} size="sm" className="mt-1" />
                        <p className="text-xs theme-text-muted font-bold mt-2 m-0">
                            Confirma que las piezas de este resguardo ya están apartadas. Se notificará a quien realizó el pedido.
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted outline-none" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={enviar} className="p-5 space-y-4">
                    <div>
                        <p className={`${THEME_LABEL} mb-2`}>Evidencia fotográfica <span className="text-red-500">*</span></p>
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            className={`${BTN_SECONDARY} inline-flex items-center gap-2 text-xs outline-none`}
                        >
                            {esMovil ? <Camera className="w-4 h-4" /> : <ImagePlus className="w-4 h-4" />}
                            {esMovil ? 'Tomar foto' : 'Adjuntar imagen o captura'}
                        </button>
                        <p className="text-[10px] theme-text-muted font-bold mt-1.5 m-0">
                            {esMovil
                                ? 'Se abrirá la cámara trasera. Puedes tomar varias fotos.'
                                : 'Selecciona una imagen del equipo o una captura de pantalla.'}
                        </p>
                        <input
                            ref={inputRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,image/jpg"
                            multiple={!esMovil}
                            capture={esMovil ? 'environment' : undefined}
                            className="hidden"
                            onChange={(e) => {
                                agregarArchivos(e.target.files);
                                e.target.value = '';
                            }}
                        />
                        {previews.length > 0 && (
                            <div className="flex flex-wrap gap-2 mt-3">
                                {previews.map((p, idx) => (
                                    <div key={`${p.name}-${idx}`} className="relative w-20 h-20 rounded-xl overflow-hidden border theme-border">
                                        <img src={p.url} alt={p.name} className="w-full h-full object-cover" />
                                        <button
                                            type="button"
                                            onClick={() => quitar(idx)}
                                            className="absolute top-1 right-1 p-1 rounded-full bg-black/50 text-white outline-none"
                                            aria-label="Quitar"
                                        >
                                            <Trash2 className="w-3 h-3" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div>
                        <label htmlFor="detalle-apartado" className={`${THEME_LABEL} ml-1`}>Nota (opcional)</label>
                        <textarea
                            id="detalle-apartado"
                            value={detalle}
                            onChange={(e) => setDetalle(e.target.value)}
                            rows={3}
                            placeholder="Ej. ubicado en rack B-3, 2 cajas..."
                            className={`${THEME_INPUT} w-full mt-1.5 py-3 text-sm font-bold resize-y min-h-[80px]`}
                        />
                    </div>

                    {error && <p className="text-xs text-red-500 font-bold m-0">{error}</p>}

                    <div className="flex flex-wrap gap-3 justify-end">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} outline-none`}>Cancelar</button>
                        <button
                            type="submit"
                            disabled={procesando || archivos.length === 0}
                            className={`${BTN_PRIMARY} outline-none disabled:opacity-50`}
                        >
                            {procesando ? 'Guardando…' : 'Confirmar apartado'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
