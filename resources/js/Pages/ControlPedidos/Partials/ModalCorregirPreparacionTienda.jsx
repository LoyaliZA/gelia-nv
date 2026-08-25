import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, X } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import { BTN_PRIMARY, BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, etiquetaAlmacen } from './pedidosBmaStyles';

export default function ModalCorregirPreparacionTienda({
    abierto,
    onClose,
    tarea,
    almacenes = [],
    onAviso = null,
}) {
    const [almacenId, setAlmacenId] = useState('');
    const [observaciones, setObservaciones] = useState('');
    const [productos, setProductos] = useState([]);
    const [procesando, setProcesando] = useState(false);

    useEffect(() => {
        if (!abierto || !tarea) return;
        setAlmacenId(String(tarea.almacen?.id || ''));
        setObservaciones(tarea.observaciones_solicitud || '');
        setProductos((tarea.productos || []).map((p) => ({
            descripcion_snapshot: p.descripcion_snapshot || '',
            sku: p.sku || '',
            producto_id: p.producto_id || '',
            cantidad_solicitada: p.cantidad_solicitada || 1,
        })));
    }, [abierto, tarea?.id]);

    if (!abierto || !tarea) return null;

    const inc = tarea.incidencia || {};
    const motivo = inc.motivo || tarea.incidencia?.motivo || '';

    const actualizarProducto = (idx, campo, valor) => {
        setProductos((prev) => prev.map((p, i) => (i === idx ? { ...p, [campo]: valor } : p)));
    };

    const enviar = () => {
        if (!almacenId) {
            onAviso?.({ tipo: 'error', mensaje: 'Seleccione el almacén de preparación.' });
            return;
        }
        if (!productos.length || productos.some((p) => !String(p.descripcion_snapshot || '').trim())) {
            onAviso?.({ tipo: 'error', mensaje: 'Indique al menos un producto con descripción.' });
            return;
        }
        setProcesando(true);
        router.post(route('control_pedidos.preparacion.corregir', tarea.id), {
            almacen_id: almacenId,
            observaciones,
            productos: productos.map((p) => ({
                descripcion_snapshot: p.descripcion_snapshot,
                sku: p.sku || null,
                producto_id: p.producto_id || null,
                cantidad_solicitada: Number(p.cantidad_solicitada) || 1,
            })),
        }, {
            preserveScroll: true,
            onFinish: () => setProcesando(false),
            onSuccess: (page) => {
                const err = page?.props?.flash?.error;
                if (err) {
                    onAviso?.({ tipo: 'error', mensaje: err });
                    return;
                }
                onAviso?.({ tipo: 'success', mensaje: page?.props?.flash?.success || 'Solicitud corregida y reenviada a Tienda.' });
                onClose?.();
            },
            onError: () => onAviso?.({ tipo: 'error', mensaje: 'No se pudo corregir la solicitud.' }),
        });
    };

    return (
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} max-w-2xl w-full max-h-[90vh] overflow-y-auto`} onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between gap-4 mb-4">
                    <div>
                        <p className="text-xs font-black uppercase tracking-widest text-orange-600 m-0">Corregir preparación</p>
                        <h3 className="text-lg font-black theme-text-main m-0 mt-1">Reenviar solicitud a Tienda</h3>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-lg theme-element outline-none">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {(motivo || inc.tipo_incidencia) && (
                    <div className="p-4 rounded-xl border border-orange-500/40 bg-orange-500/10 mb-4 flex gap-3">
                        <AlertTriangle className="w-5 h-5 text-orange-600 shrink-0 mt-0.5" />
                        <div>
                            <p className="text-sm font-black text-orange-700 m-0">Incidencia reportada por Tienda</p>
                            {inc.tipo_incidencia && (
                                <p className="text-xs font-bold theme-text-muted m-0 mt-1">Tipo: {inc.tipo_incidencia.replace(/_/g, ' ')}</p>
                            )}
                            {motivo && <p className="text-sm theme-text-main m-0 mt-1">{motivo}</p>}
                            {inc.observacion && <p className="text-xs theme-text-muted m-0 mt-2">{inc.observacion}</p>}
                        </div>
                    </div>
                )}

                <div className="space-y-4">
                    <div>
                        <label className={THEME_LABEL}>Almacén de preparación *</label>
                        <select value={almacenId} onChange={(e) => setAlmacenId(e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                            <option value="">Seleccionar...</option>
                            {almacenes.map((a) => (
                                <option key={a.id} value={String(a.id)}>{etiquetaAlmacen(a)}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className={THEME_LABEL}>Observaciones para Tienda</label>
                        <textarea
                            value={observaciones}
                            onChange={(e) => setObservaciones(e.target.value)}
                            rows={3}
                            className={`${THEME_TEXTAREA} w-full`}
                            placeholder="Indique los cambios realizados..."
                        />
                    </div>

                    <div className="space-y-3">
                        <p className={THEME_LABEL}>Productos solicitados</p>
                        {productos.map((p, idx) => (
                            <div key={idx} className="grid grid-cols-1 md:grid-cols-12 gap-2 p-3 rounded-xl border theme-border">
                                <div className="md:col-span-6">
                                    <input
                                        type="text"
                                        value={p.descripcion_snapshot}
                                        onChange={(e) => actualizarProducto(idx, 'descripcion_snapshot', e.target.value)}
                                        className={`${THEME_INPUT} w-full py-2`}
                                        placeholder="Descripción"
                                    />
                                </div>
                                <div className="md:col-span-3">
                                    <input
                                        type="text"
                                        value={p.sku}
                                        onChange={(e) => actualizarProducto(idx, 'sku', e.target.value)}
                                        className={`${THEME_INPUT} w-full py-2`}
                                        placeholder="SKU"
                                    />
                                </div>
                                <div className="md:col-span-3">
                                    <input
                                        type="number"
                                        min={1}
                                        value={p.cantidad_solicitada}
                                        onChange={(e) => actualizarProducto(idx, 'cantidad_solicitada', e.target.value)}
                                        className={`${THEME_INPUT} w-full py-2`}
                                        placeholder="Cant."
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap gap-3 justify-end mt-6">
                    <button type="button" onClick={onClose} disabled={procesando} className={BTN_SECONDARY}>Cancelar</button>
                    <button type="button" onClick={enviar} disabled={procesando} className={BTN_PRIMARY}>
                        Corregir y reenviar
                    </button>
                </div>
            </div>
        </div>
    );
}
