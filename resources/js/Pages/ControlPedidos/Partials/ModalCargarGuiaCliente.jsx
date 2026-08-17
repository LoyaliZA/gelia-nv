import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { X, Upload } from 'lucide-react';
import { THEME_INPUT } from '../../../utils/geliaTheme';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    LABEL_GUIA_CLIENTE,
} from './pedidosBmaStyles';

const SECCION = `${THEME_LABEL} mb-2 block`;

export default function ModalCargarGuiaCliente({ abierto, onClose, pedido }) {
    const { data, setData, post, processing, reset, errors } = useForm({
        numero_rastreo: '',
        guia_pdf: null,
    });
    const [previewNombre, setPreviewNombre] = useState('');

    useEffect(() => {
        if (!abierto) return;
        reset();
        setPreviewNombre('');
        setData({
            numero_rastreo: '',
            guia_pdf: null,
        });
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const enviar = (e) => {
        e.preventDefault();
        post(route('control_pedidos.cargar_guia_cliente', pedido.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full p-6 space-y-5`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <p className={`${THEME_LABEL} m-0`}>{LABEL_GUIA_CLIENTE}</p>
                        <p className="text-xs theme-text-muted font-bold mt-1 m-0">
                            Folio {pedido.folio_remision || pedido.folio || pedido.id}
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-xl theme-element border theme-border outline-none">
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <form onSubmit={enviar} className="space-y-4">
                    <div>
                        <label className={SECCION}>Número de guía / rastreo</label>
                        <input
                            type="text"
                            value={data.numero_rastreo}
                            onChange={(e) => setData('numero_rastreo', e.target.value)}
                            className={`${THEME_INPUT} w-full py-3`}
                            placeholder="Número de rastreo"
                            autoFocus
                        />
                        {errors.numero_rastreo && (
                            <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.numero_rastreo}</p>
                        )}
                    </div>
                    <div>
                        <label className={SECCION}>PDF de la guía</label>
                        <label className="flex items-center gap-2 px-4 py-3 border theme-border border-dashed rounded-xl cursor-pointer w-fit theme-element theme-text-main">
                            <Upload className="w-4 h-4 theme-text-muted" />
                            <span className="text-xs font-black uppercase">
                                {previewNombre || 'Adjuntar PDF'}
                            </span>
                            <input
                                type="file"
                                accept="application/pdf,.pdf"
                                className="hidden"
                                onChange={(e) => {
                                    const file = e.target.files?.[0] || null;
                                    setData('guia_pdf', file);
                                    setPreviewNombre(file?.name || '');
                                }}
                            />
                        </label>
                        {errors.guia_pdf && (
                            <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.guia_pdf}</p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-3 pt-2">
                        <button type="submit" disabled={processing} className={BTN_PRIMARY}>
                            Cargar guía
                        </button>
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} theme-element border theme-border`}>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
