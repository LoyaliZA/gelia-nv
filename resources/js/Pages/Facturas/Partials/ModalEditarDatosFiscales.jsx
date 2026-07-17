import React from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Save, X } from 'lucide-react';
import {
    THEME_INPUT,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '../../../utils/geliaTheme';

const CAMPOS = [
    ['rfc', 'RFC'],
    ['codigo_postal', 'Código postal'],
    ['regimen_fiscal', 'Régimen fiscal'],
    ['correo_electronico', 'Correo electrónico'],
    ['uso_factura', 'Uso de factura'],
    ['nombre_razon_social', 'Nombre / razón social'],
    ['telefono', 'Número telefónico'],
];

export default function ModalEditarDatosFiscales({ cliente, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        rfc: cliente.rfc || '',
        codigo_postal: cliente.codigo_postal || '',
        regimen_fiscal: cliente.regimen_fiscal || '',
        correo_electronico: cliente.correo_electronico || '',
        uso_factura: cliente.uso_factura || '',
        nombre_razon_social: cliente.nombre_razon_social || '',
        telefono: cliente.telefono || '',
    });

    const guardar = (e) => {
        e.preventDefault();
        put(route('facturas.datos_fiscales.update', cliente.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="min-w-0">
                        <h3 className="text-lg font-black italic uppercase theme-text-main m-0 leading-tight">
                            Datos fiscales
                        </h3>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1.5 m-0">
                            {cliente.numero_cliente} — {cliente.nombre}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <form onSubmit={guardar} className="flex flex-col flex-1 min-h-0">
                    <div className="gelia-modal-body p-5 md:p-6 space-y-4">
                        {CAMPOS.map(([key, label]) => (
                            <div key={key} className="space-y-1.5">
                                <label className={THEME_LABEL}>{label}</label>
                                <input
                                    value={data[key]}
                                    onChange={(e) => setData(key, e.target.value)}
                                    className={THEME_INPUT}
                                />
                                {errors[key] && <p className="text-xs text-red-500 font-bold m-0">{errors[key]}</p>}
                            </div>
                        ))}
                    </div>
                    <div className="gelia-modal-footer p-5 md:p-6">
                        <button type="submit" disabled={processing} className={`${THEME_BTN_PRIMARY} w-full`}>
                            <Save className="w-4 h-4 shrink-0" />
                            {processing ? 'Guardando…' : 'Guardar cambios'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
