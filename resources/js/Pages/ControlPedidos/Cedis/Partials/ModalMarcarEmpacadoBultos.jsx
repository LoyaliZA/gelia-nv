import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, PackageCheck, ImagePlus, Camera, Trash2, Plus } from 'lucide-react';
import { THEME_LABEL } from '../../../../utils/geliaTheme';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from '../../Partials/pedidosBmaStyles';
import {
    complementosDe,
    etiquetaSucursal,
    pedidosRequierenBultosEmpaque,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import { esDispositivoCampo } from '../../../Activos/Partials/useDispositivoCampo';

const filaVacia = () => ({
    foto_bulto: null,
    foto_ticket: null,
    preview_bulto: null,
    preview_ticket: null,
});

function CapturaFoto({ etiqueta, preview, onSeleccionar, inputRef, camaraRef }) {
    return (
        <div className="space-y-1.5">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{etiqueta}</p>
            <div className="flex flex-col gap-2">
                <button
                    type="button"
                    onClick={() => camaraRef.current?.click()}
                    className={`${BTN_SECONDARY} min-h-[44px] w-full inline-flex items-center justify-center gap-2 text-[10px] outline-none`}
                >
                    <Camera className="w-3.5 h-3.5 shrink-0" /> Tomar foto
                </button>
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    className={`${BTN_SECONDARY} min-h-[44px] w-full inline-flex items-center justify-center gap-2 text-[10px] outline-none`}
                >
                    <ImagePlus className="w-3.5 h-3.5 shrink-0" /> Subir archivo
                </button>
                <input
                    ref={camaraRef}
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="hidden"
                    onChange={(e) => {
                        onSeleccionar(e.target.files?.[0] || null);
                        e.target.value = '';
                    }}
                />
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/jpg"
                    className="hidden"
                    onChange={(e) => {
                        onSeleccionar(e.target.files?.[0] || null);
                        e.target.value = '';
                    }}
                />
            </div>
            {preview && (
                <div className="relative w-full h-24 rounded-xl overflow-hidden border theme-border mt-1">
                    <img src={preview} alt={etiqueta} className="w-full h-full object-cover" />
                </div>
            )}
        </div>
    );
}

function SeccionPedidoBultos({ pedido, bultos, onChange }) {
    const actualizarFila = (idx, patch) => {
        onChange(bultos.map((fila, i) => (i === idx ? { ...fila, ...patch } : fila)));
    };

    const quitarFila = (idx) => {
        const fila = bultos[idx];
        if (fila?.preview_bulto) URL.revokeObjectURL(fila.preview_bulto);
        if (fila?.preview_ticket) URL.revokeObjectURL(fila.preview_ticket);
        onChange(bultos.filter((_, i) => i !== idx));
    };

    const agregarFila = () => {
        if (bultos.length >= 50) return;
        onChange([...bultos, filaVacia()]);
    };

    const sucursal = pedido.sucursal_destino || pedido.sucursalDestino;

    return (
        <section className="rounded-2xl border theme-border p-4 space-y-3 theme-element">
            <div>
                <EncabezadoFolioPedido pedido={pedido} size="sm" />
                {sucursal && (
                    <p className="text-[10px] font-bold theme-text-muted m-0 mt-1">
                        Destino: {etiquetaSucursal(sucursal)}
                    </p>
                )}
            </div>

            {bultos.map((fila, idx) => (
                <BultoFila
                    key={`${pedido.id}-bulto-${idx}`}
                    numero={idx + 1}
                    fila={fila}
                    puedeQuitar={bultos.length > 1}
                    onQuitar={() => quitarFila(idx)}
                    onFotoBulto={(file) => {
                        if (fila.preview_bulto) URL.revokeObjectURL(fila.preview_bulto);
                        actualizarFila(idx, {
                            foto_bulto: file,
                            preview_bulto: file ? URL.createObjectURL(file) : null,
                        });
                    }}
                    onFotoTicket={(file) => {
                        if (fila.preview_ticket) URL.revokeObjectURL(fila.preview_ticket);
                        actualizarFila(idx, {
                            foto_ticket: file,
                            preview_ticket: file ? URL.createObjectURL(file) : null,
                        });
                    }}
                />
            ))}

            <button
                type="button"
                onClick={agregarFila}
                className={`${BTN_SECONDARY} w-full min-h-[44px] inline-flex items-center justify-center gap-2 text-xs outline-none`}
            >
                <Plus className="w-4 h-4" /> Agregar bulto
            </button>
        </section>
    );
}

function BultoFila({ numero, fila, puedeQuitar, onQuitar, onFotoBulto, onFotoTicket }) {
    const inputBultoRef = useRef(null);
    const camaraBultoRef = useRef(null);
    const inputTicketRef = useRef(null);
    const camaraTicketRef = useRef(null);

    return (
        <div className="rounded-xl border theme-border p-3 space-y-3 bg-black/[0.02] dark:bg-white/[0.02]">
            <div className="flex items-center justify-between gap-2">
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-main m-0">
                    Bulto {numero}
                </p>
                {puedeQuitar && (
                    <button
                        type="button"
                        onClick={onQuitar}
                        className="p-2 min-h-[44px] min-w-[44px] rounded-lg text-red-500 hover:bg-red-500/10 outline-none inline-flex items-center justify-center"
                        aria-label={`Quitar bulto ${numero}`}
                    >
                        <Trash2 className="w-4 h-4" />
                    </button>
                )}
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <CapturaFoto
                    etiqueta="Foto del bulto *"
                    preview={fila.preview_bulto}
                    onSeleccionar={onFotoBulto}
                    inputRef={inputBultoRef}
                    camaraRef={camaraBultoRef}
                />
                <CapturaFoto
                    etiqueta="Foto del ticket *"
                    preview={fila.preview_ticket}
                    onSeleccionar={onFotoTicket}
                    inputRef={inputTicketRef}
                    camaraRef={camaraTicketRef}
                />
            </div>
        </div>
    );
}

export default function ModalMarcarEmpacadoBultos({ abierto, onClose, pedido, onExito }) {
    const [bultosPorPedido, setBultosPorPedido] = useState({});
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const [esMovil, setEsMovil] = useState(false);
    const [confirmarCierre, setConfirmarCierre] = useState(false);

    const pedidosObjetivo = pedido ? pedidosRequierenBultosEmpaque(pedido) : [];

    useEffect(() => {
        if (!abierto || !pedido) return;

        const inicial = {};
        pedidosRequierenBultosEmpaque(pedido).forEach((p) => {
            inicial[p.id] = [filaVacia()];
        });
        setBultosPorPedido(inicial);
        setError('');
        setProcesando(false);
        setEsMovil(esDispositivoCampo());
    }, [abierto, pedido?.id]);

    useEffect(() => () => {
        Object.values(bultosPorPedido).forEach((filas) => {
            filas?.forEach((fila) => {
                if (fila.preview_bulto) URL.revokeObjectURL(fila.preview_bulto);
                if (fila.preview_ticket) URL.revokeObjectURL(fila.preview_ticket);
            });
        });
    }, [bultosPorPedido]);

    if (!abierto || !pedido) return null;

    const validar = () => {
        for (const p of pedidosObjetivo) {
            const filas = bultosPorPedido[p.id] || [];
            if (filas.length < 1) {
                return `Registre al menos un bulto para ${p.folio}.`;
            }
            for (let i = 0; i < filas.length; i += 1) {
                if (!filas[i].foto_bulto || !filas[i].foto_ticket) {
                    return `El bulto ${i + 1} de ${p.folio} requiere foto del bulto y del ticket.`;
                }
            }
        }
        return '';
    };

    const enviar = (e) => {
        e.preventDefault();
        const msg = validar();
        if (msg) {
            setError(msg);
            return;
        }

        const formData = new FormData();
        pedidosObjetivo.forEach((p) => {
            (bultosPorPedido[p.id] || []).forEach((fila, idx) => {
                formData.append(`bultos_por_pedido[${p.id}][${idx}][foto_bulto]`, fila.foto_bulto);
                formData.append(`bultos_por_pedido[${p.id}][${idx}][foto_ticket]`, fila.foto_ticket);
            });
        });

        setProcesando(true);
        setError('');
        router.post(route('control_pedidos.cedis.marcar_empacado', pedido.id), formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                onExito?.();
                onClose();
            },
            onError: (errors) => {
                const first = errors.bultos_por_pedido
                    || errors['bultos_por_pedido.0']
                    || Object.values(errors)[0];
                setError(typeof first === 'string' ? first : 'No se pudo registrar el empaque.');
            },
            onFinish: () => setProcesando(false),
        });
    };

    const comps = complementosDe(pedido);
    const titulo = comps.length ? 'Empacar grupo — bultos' : 'Marcar empacado — bultos';

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-end sm:items-center py-0 sm:py-4`} onClick={esMovil ? undefined : () => setConfirmarCierre(true)}>
                <div
                    className={`${THEME_MODAL_SHELL} max-w-2xl w-full max-h-[95dvh] sm:max-h-[calc(100dvh-2rem)] flex flex-col`}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="p-5 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div>
                            <h2 className="text-lg font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                                <PackageCheck className="w-5 h-5 text-sky-600" />
                                {titulo}
                            </h2>
                            <EncabezadoFolioPedido pedido={pedido} size="sm" className="mt-1" />
                            <p className="text-xs theme-text-muted font-bold mt-2 m-0">
                                Registra los bultos exactos que saldrán a tienda. Cada bulto necesita foto del bulto y del ticket por separado.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setConfirmarCierre(true)}
                            className="p-2 min-h-[44px] min-w-[44px] rounded-full theme-text-muted outline-none inline-flex items-center justify-center"
                            aria-label="Cerrar"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <form onSubmit={enviar} className="flex flex-col min-h-0 flex-1">
                        <div className="p-5 space-y-4 overflow-y-auto flex-1 pb-[max(1rem,env(safe-area-inset-bottom))]">
                            {pedidosObjetivo.map((p) => (
                                <SeccionPedidoBultos
                                    key={p.id}
                                    pedido={p}
                                    bultos={bultosPorPedido[p.id] || [filaVacia()]}
                                    onChange={(filas) => setBultosPorPedido((prev) => ({ ...prev, [p.id]: filas }))}
                                />
                            ))}
                            {error && <p className="text-xs text-red-500 font-bold m-0">{error}</p>}
                        </div>

                        <div className="p-5 border-t theme-border flex flex-col-reverse sm:flex-row gap-3 sm:justify-end shrink-0 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                            <button type="button" onClick={() => setConfirmarCierre(true)} className={`${BTN_SECONDARY} outline-none min-h-[44px] w-full sm:w-auto`}>
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={procesando}
                                className={`${BTN_PRIMARY} outline-none disabled:opacity-50 min-h-[44px] w-full sm:w-auto`}
                            >
                                {procesando ? 'Guardando…' : comps.length ? 'Empacar grupo' : 'Marcar empacado'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <ModalConfirmarAccion
                abierto={confirmarCierre}
                titulo="Descartar registro"
                mensaje="¿Cerrar sin guardar los bultos registrados?"
                etiquetaConfirmar="Cerrar"
                variante="danger"
                onClose={() => setConfirmarCierre(false)}
                onConfirm={() => {
                    setConfirmarCierre(false);
                    onClose();
                }}
            />
        </>,
        document.body
    );
}
