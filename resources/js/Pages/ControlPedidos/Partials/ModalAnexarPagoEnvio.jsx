import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, Upload } from 'lucide-react';
import { THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import InputMoneda from './InputMoneda';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from './pedidosBmaStyles';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import { archivosImagenDesdeClipboard, documentoDesdeArchivoLocal } from './archivosDesdeClipboard';

const SECCION = `${THEME_LABEL} mb-2 block`;

export default function ModalAnexarPagoEnvio({
    abierto,
    onClose,
    pedido,
    bancos = [],
    routeName = 'control_pedidos.anexar_pago_envio',
}) {
    const { data, setData, post, processing, reset, errors } = useForm({
        monto: '',
        catalogo_banco_id: '',
        comentarios: '',
        comprobante: null,
    });
    const [previewUrl, setPreviewUrl] = useState(null);
    const [docPreview, setDocPreview] = useState(null);

    const asignarComprobante = (file) => {
        setPreviewUrl((prev) => {
            if (prev) URL.revokeObjectURL(prev);
            return file ? URL.createObjectURL(file) : null;
        });
        setData('comprobante', file || null);
    };

    useEffect(() => {
        if (!abierto) return;
        reset();
        setPreviewUrl((prev) => {
            if (prev) URL.revokeObjectURL(prev);
            return null;
        });
        setDocPreview(null);
        setData({
            monto: '',
            catalogo_banco_id: '',
            comentarios: '',
            comprobante: null,
        });
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const folio = pedido.folio_remision || pedido.folio;
    const docLocal = data.comprobante && previewUrl
        ? documentoDesdeArchivoLocal(data.comprobante, previewUrl)
        : null;

    const enviar = (e) => {
        e.preventDefault();
        post(route(routeName, pedido.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
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
                onPaste={(e) => {
                    const pasted = archivosImagenDesdeClipboard(e.clipboardData);
                    if (!pasted.length) return;
                    e.preventDefault();
                    const img = pasted[0];
                    asignarComprobante(new File([img], `comprobante-paste-${Date.now()}.png`, { type: img.type || 'image/png' }));
                }}
            >
                <div className="flex items-center justify-between p-5 border-b theme-border shrink-0">
                    <div>
                        <h2 className="text-lg font-black uppercase italic theme-text-main m-0">Anexar pago de envío</h2>
                        <p className="text-xs theme-text-muted font-bold mt-1 m-0">Pedido {folio} · {pedido.cliente?.nombre || '—'}</p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-xl theme-element border theme-border outline-none">
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <div className="p-5 space-y-4 overflow-y-auto flex-1">
                    <div>
                        <label className={SECCION}>Monto del envío *</label>
                        <InputMoneda value={data.monto} onChange={(v) => setData('monto', v)} className="w-full py-3" />
                        {errors.monto && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.monto}</p>}
                    </div>
                    <div>
                        <label className={SECCION}>Banco / cuenta de origen *</label>
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
                        <label className={SECCION}>Comprobante *</label>
                        <div className="flex flex-wrap items-center gap-3">
                            <label className="flex items-center gap-2 p-4 rounded-xl border theme-border theme-element cursor-pointer">
                                <Upload className="w-4 h-4 theme-text-muted" />
                                <span className="text-xs font-bold theme-text-main">
                                    {data.comprobante?.name || 'Adjuntar imagen o PDF'}
                                </span>
                                <input
                                    type="file"
                                    accept="image/*,.pdf"
                                    className="hidden"
                                    onChange={(e) => {
                                        asignarComprobante(e.target.files?.[0] || null);
                                        e.target.value = '';
                                    }}
                                />
                            </label>
                            {docLocal && (
                                <MiniaturaDocumento documento={docLocal} onVer={() => setDocPreview(docLocal)} />
                            )}
                        </div>
                        <p className="text-[10px] theme-text-muted font-bold m-0 mt-2">
                            Puede pegar una captura (Ctrl+V).
                        </p>
                        {errors.comprobante && <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.comprobante}</p>}
                    </div>
                    <div>
                        <label className={SECCION}>Comentarios</label>
                        <textarea
                            value={data.comentarios}
                            onChange={(e) => setData('comentarios', e.target.value)}
                            rows={3}
                            placeholder="Ej. Pago de envío perteneciente a la remisión X."
                            className={`${THEME_TEXTAREA} w-full`}
                        />
                    </div>
                </div>

                <div className="flex justify-end gap-3 p-5 border-t theme-border shrink-0">
                    <button type="button" onClick={onClose} className={BTN_SECONDARY} disabled={processing}>Cancelar</button>
                    <button type="submit" className={BTN_PRIMARY} disabled={processing}>
                        {processing ? 'Guardando...' : 'Anexar pago'}
                    </button>
                </div>
                <ModalVistaPreviaDocumento
                    abierto={Boolean(docPreview)}
                    documento={docPreview}
                    onClose={() => setDocPreview(null)}
                />
            </form>
        </div>,
        document.body
    );
}
