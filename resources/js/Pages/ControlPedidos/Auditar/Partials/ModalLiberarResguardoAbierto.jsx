import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, Upload } from 'lucide-react';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../../utils/geliaTheme';
import InputMoneda from '../../Partials/InputMoneda';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from '../../Partials/pedidosBmaStyles';

const SECCION = `${THEME_LABEL} mb-2 block`;

export default function ModalLiberarResguardoAbierto({
    abierto,
    onClose,
    onSuccess,
    pedido,
    bancos = [],
    routeName = 'control_pedidos.auditar.liberar_resguardo',
    titulo = 'Liberar resguardo abierto',
    etiquetaConfirmar = 'Liberar y anexar envío',
}) {
    const { data, setData, post, processing, reset, errors } = useForm({
        peso_real_kg: '',
        numero_cajas: '',
        costo_envio: '',
        catalogo_banco_id: '',
        comentarios: '',
        comprobante: null,
    });
    const [previewNombre, setPreviewNombre] = useState('');

    useEffect(() => {
        if (!abierto) return;
        reset();
        setPreviewNombre('');
        setData({
            peso_real_kg: '',
            numero_cajas: '',
            costo_envio: '',
            catalogo_banco_id: pedido?.catalogo_banco_id || '',
            comentarios: '',
            comprobante: null,
        });
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const folio = pedido.folio_remision || pedido.folio;
    const nComplementos = (pedido.complementos || []).length;
    const usaPesajeCedis = Boolean(pedido.pesaje_respondido_at);
    const cajasPesaje = pedido.cajas || [];

    const enviar = (e) => {
        e.preventDefault();
        post(route(routeName, pedido.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
                onSuccess?.();
            },
        });
    };

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <form
                className={`${THEME_MODAL_SHELL} max-w-lg w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
                onSubmit={enviar}
            >
                <div className="flex items-center justify-between p-5 border-b theme-border shrink-0">
                    <div>
                        <h2 className="text-lg font-black uppercase italic theme-text-main m-0">{titulo}</h2>
                        <p className="text-xs theme-text-muted font-bold mt-1 m-0">
                            Pedido {folio} · {usaPesajeCedis
                                ? 'Pesaje CEDIS listo — capture costo y comprobante de envío'
                                : 'Capture peso, cajas, costo y comprobante de envío'}
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-xl theme-element border theme-border outline-none">
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <div className="p-5 space-y-4 overflow-y-auto flex-1">
                    {nComplementos > 0 && (
                        <div className="rounded-xl border border-teal-500/30 bg-teal-500/5 p-3">
                            <p className="text-[10px] font-black uppercase text-teal-700 dark:text-teal-400 m-0 mb-1">
                                Paquete con complementos
                            </p>
                            <p className="text-xs font-bold theme-text-main m-0">
                                Peso, cajas y costo aplican al folio padre más {nComplementos} complemento{nComplementos === 1 ? '' : 's'}.
                            </p>
                        </div>
                    )}
                    {usaPesajeCedis ? (
                        <div className="rounded-xl border theme-border theme-element p-3 space-y-1">
                            <p className="text-[10px] font-black uppercase theme-text-muted m-0">Pesaje CEDIS (no editable)</p>
                            <p className="text-sm font-bold theme-text-main m-0">
                                Peso: {pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : '—'}
                                {' · '}
                                Cajas: {pedido.numero_cajas ?? '—'}
                            </p>
                            {cajasPesaje.length > 0 && cajasPesaje.map((c) => (
                                <p key={c.id} className="text-xs theme-text-muted font-bold m-0">
                                    {c.tipo_caja?.nombre || 'Caja'}: {c.cantidad}
                                </p>
                            ))}
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={SECCION}>Peso real (kg) *</label>
                                <input
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    value={data.peso_real_kg}
                                    onChange={(e) => setData('peso_real_kg', e.target.value)}
                                    className={`${THEME_INPUT} w-full py-3`}
                                />
                                {errors.peso_real_kg && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.peso_real_kg}</p>}
                            </div>
                            <div>
                                <label className={SECCION}>N° de cajas *</label>
                                <select
                                    value={data.numero_cajas === '' || data.numero_cajas == null ? '' : String(data.numero_cajas)}
                                    onChange={(e) => setData('numero_cajas', e.target.value)}
                                    className={`${THEME_SELECT} w-full py-3`}
                                >
                                    <option value="">Seleccionar...</option>
                                    <option value="0">N/A</option>
                                    {Array.from({ length: 20 }, (_, i) => i + 1).map((n) => (
                                        <option key={n} value={String(n)}>{n}</option>
                                    ))}
                                </select>
                                {errors.numero_cajas && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.numero_cajas}</p>}
                            </div>
                        </div>
                    )}
                    <div>
                        <label className={SECCION}>Costo de envío *</label>
                        <InputMoneda value={data.costo_envio} onChange={(v) => setData('costo_envio', v)} className="w-full py-3" />
                        {errors.costo_envio && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.costo_envio}</p>}
                    </div>
                    <div>
                        <label className={SECCION}>Banco / cuenta *</label>
                        <select
                            value={data.catalogo_banco_id}
                            onChange={(e) => setData('catalogo_banco_id', e.target.value)}
                            className={`${THEME_SELECT} w-full py-3`}
                        >
                            <option value="">Seleccionar...</option>
                            {bancos.map((b) => (
                                <option key={b.id} value={b.id}>{b.nombre}</option>
                            ))}
                        </select>
                        {errors.catalogo_banco_id && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.catalogo_banco_id}</p>}
                    </div>
                    <div>
                        <label className={SECCION}>Comprobante de envío *</label>
                        <label className="flex items-center gap-2 p-4 rounded-xl border theme-border theme-element cursor-pointer">
                            <Upload className="w-4 h-4 theme-text-muted" />
                            <span className="text-xs font-bold theme-text-main">
                                {previewNombre || 'Adjuntar imagen o PDF'}
                            </span>
                            <input
                                type="file"
                                accept="image/*,.pdf"
                                className="hidden"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] || null;
                                    setData('comprobante', file);
                                    setPreviewNombre(file?.name || '');
                                }}
                            />
                        </label>
                        {errors.comprobante && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.comprobante}</p>}
                    </div>
                    <div>
                        <label className={SECCION}>Comentarios</label>
                        <textarea
                            value={data.comentarios}
                            onChange={(e) => setData('comentarios', e.target.value)}
                            rows={2}
                            className={`${THEME_TEXTAREA} w-full`}
                        />
                    </div>
                </div>

                <div className="flex justify-end gap-3 p-5 border-t theme-border shrink-0">
                    <button type="button" onClick={onClose} className={BTN_SECONDARY} disabled={processing}>Cancelar</button>
                    <button type="submit" className={BTN_PRIMARY} disabled={processing}>
                        {processing ? 'Guardando...' : etiquetaConfirmar}
                    </button>
                </div>
            </form>
        </div>,
        document.body
    );
}
