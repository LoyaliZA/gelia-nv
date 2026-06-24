import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Edit2, X } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { BTN_PRIMARY, BTN_PRIMARY_STYLE } from './contabilidadStyles';
import { contabilidadRoutes } from '../contabilidadRoutes';

export default function ModalEditarPedido({ pedido, plataformas, tiposTransaccion, onCerrar }) {
    const [productos, setProductos] = useState(
        () => (pedido?.lineas || []).map((l) => ({ id: l.id, piezas: l.piezas, nombre: l.nombre_producto, sku: l.sku, tipo_devolucion: l.tipo_devolucion || 'normal' }))
    );

    const { data, setData, put, processing, errors } = useForm({
        tipo_transaccion: pedido?.tipo_transaccion?.codigo || 'venta',
        plataforma_pago_id: String(pedido?.plataforma_pago_id || ''),
        cliente_nombre: pedido?.cliente_nombre || '',
        venta_total: pedido?.venta_total ?? '',
        costo_envio: pedido?.costo_envio ?? '',
        comision_plataforma: pedido?.comision_plataforma ?? '',
        envio_pagado_cliente: Boolean(pedido?.envio_pagado_cliente),
        productos: productos.map((p) => ({ id: p.id, piezas: p.piezas, tipo_devolucion: p.tipo_devolucion })),
    });

    if (!pedido) return null;

    const actualizarProductoCampo = (index, campo, valor) => {
        const next = [...productos];
        next[index] = { ...next[index], [campo]: valor };
        setProductos(next);
        setData('productos', next.map((p) => ({ id: p.id, piezas: p.piezas, tipo_devolucion: p.tipo_devolucion })));
    };

    const actualizarPiezas = (index, piezas) => {
        actualizarProductoCampo(index, 'piezas', parseInt(piezas, 10) || 1);
    };

    const actualizarTipoDevolucion = (index, tipo) => {
        actualizarProductoCampo(index, 'tipo_devolucion', tipo);
    };

    const enviar = (e) => {
        e.preventDefault();
        put(contabilidadRoutes.pedidosUpdate(pedido.id), {
            preserveScroll: true,
            onSuccess: () => onCerrar(),
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md`} onClick={onCerrar}>
            <div className={`${THEME_MODAL_SHELL} w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col rounded-[2rem] shadow-2xl`} onClick={(e) => e.stopPropagation()}>
                <div className="flex items-center justify-between gap-3 p-6 border-b theme-border shrink-0">
                    <div className="flex items-center gap-3">
                        <Edit2 className="w-6 h-6 text-amber-500" />
                        <h3 className="text-xl font-black italic theme-text-main uppercase m-0">
                            Editar · {pedido.numero_pedido}
                        </h3>
                    </div>
                    <button type="button" onClick={onCerrar} className="theme-btn-icon rounded-xl p-2">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <form onSubmit={enviar} className="overflow-y-auto custom-scrollbar p-6 space-y-4 flex-1">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className={THEME_LABEL}>Transacción</label>
                            <select className={`${THEME_INPUT} w-full mt-1`} value={data.tipo_transaccion} onChange={(e) => setData('tipo_transaccion', e.target.value)}>
                                {(tiposTransaccion || []).map((t) => (
                                    <option key={t.id} value={t.codigo}>{t.nombre}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Plataforma</label>
                            <select className={`${THEME_INPUT} w-full mt-1`} value={data.plataforma_pago_id} onChange={(e) => setData('plataforma_pago_id', e.target.value)}>
                                {(plataformas || []).map((p) => (
                                    <option key={p.id} value={p.id}>{p.nombre}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className={THEME_LABEL}>Cliente</label>
                        <input className={`${THEME_INPUT} w-full mt-1`} value={data.cliente_nombre} onChange={(e) => setData('cliente_nombre', e.target.value)} />
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className={THEME_LABEL}>Venta</label>
                            <input type="number" step="0.01" className={`${THEME_INPUT} w-full mt-1`} value={data.venta_total} onChange={(e) => setData('venta_total', e.target.value)} required />
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Envío</label>
                            <input type="number" step="0.01" className={`${THEME_INPUT} w-full mt-1`} value={data.costo_envio} onChange={(e) => setData('costo_envio', e.target.value)} required />
                        </div>
                        <div>
                            <label className={`${THEME_LABEL} text-[var(--color-primario)]`}>Comisión</label>
                            <input type="number" step="0.01" className={`${THEME_INPUT} w-full mt-1`} value={data.comision_plataforma} onChange={(e) => setData('comision_plataforma', e.target.value)} required />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 cursor-pointer theme-text-main text-sm font-bold">
                        <input type="checkbox" checked={data.envio_pagado_cliente} onChange={(e) => setData('envio_pagado_cliente', e.target.checked)} className="rounded border theme-border" />
                        Cliente pagó envío
                    </label>
                    <div className="border-t theme-border pt-3 space-y-2">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-main">Ajustar productos</p>
                        {productos.map((prod, index) => (
                            <div key={prod.id} className="theme-element border theme-border rounded-xl p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs font-bold theme-text-main truncate">{prod.nombre}</p>
                                    <p className="text-[10px] theme-text-muted">{prod.sku}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="number"
                                        min="1"
                                        className={`${THEME_INPUT} w-16 text-center text-xs`}
                                        value={prod.piezas}
                                        onChange={(e) => actualizarPiezas(index, e.target.value)}
                                    />
                                    <select
                                        className={`${THEME_INPUT} text-xs py-1 px-2 pr-8`}
                                        value={prod.tipo_devolucion}
                                        onChange={(e) => actualizarTipoDevolucion(index, e.target.value)}
                                    >
                                        <option value="normal">Normal</option>
                                        <option value="devuelto">Devuelto</option>
                                        <option value="perdido_danado">Perdido/Dañado</option>
                                    </select>
                                </div>
                            </div>
                        ))}
                    </div>
                    {errors.productos && <p className="text-xs text-red-500">{errors.productos}</p>}
                    <button type="submit" disabled={processing} className={`${BTN_PRIMARY} w-full`} style={BTN_PRIMARY_STYLE}>
                        {processing ? 'Guardando...' : 'Actualizar registro'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
