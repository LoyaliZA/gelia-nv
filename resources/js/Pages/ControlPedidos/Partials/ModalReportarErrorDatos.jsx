import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { AlertTriangle, X } from 'lucide-react';
import { THEME_LABEL, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';

export const CAMPOS_ERROR_DATOS = [
    { id: 'domicilio', label: 'Domicilio / dirección' },
    { id: 'destinatario', label: 'Destinatario' },
    { id: 'telefono', label: 'Teléfono' },
    { id: 'paqueteria', label: 'Paquetería' },
    { id: 'tipo_guia', label: 'Tipo de guía' },
    { id: 'referencia', label: 'Referencias' },
    { id: 'codigo_postal', label: 'Código postal' },
    { id: 'ciudad_estado', label: 'Ciudad / estado' },
    { id: 'numero_rastreo', label: 'Número de guía' },
    { id: 'guia_pdf', label: 'PDF de guía' },
];

/**
 * @param {'delegado'|'cedis'} origen
 */
export default function ModalReportarErrorDatos({ abierto, onClose, pedido, origen = 'delegado' }) {
    const [seleccionados, setSeleccionados] = useState([]);
    const [detalle, setDetalle] = useState('');
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (abierto) {
            setSeleccionados([]);
            setDetalle('');
            setError('');
            setProcesando(false);
        }
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const toggle = (id) => {
        setSeleccionados((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const ruta = origen === 'cedis'
        ? route('control_pedidos.cedis.reportar_error_datos', pedido.id)
        : route('control_pedidos.delegado.reportar_error_datos', pedido.id);

    const enviar = (e) => {
        e.preventDefault();
        if (seleccionados.length === 0) {
            setError('Seleccione al menos un dato incorrecto.');
            return;
        }
        setProcesando(true);
        setError('');
        router.post(ruta, {
            campos_incorrectos: seleccionados,
            detalle: detalle.trim() || null,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errors) => {
                setError(errors.campos_incorrectos || errors.detalle || 'No se pudo reportar el error.');
            },
            onFinish: () => setProcesando(false),
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 border-b theme-border flex justify-between items-start gap-3">
                    <div>
                        <p className="text-[10px] font-black uppercase theme-text-muted m-0 mb-1">Reportar error de datos</p>
                        <EncabezadoFolioPedido pedido={pedido} size="sm" />
                        <p className="text-xs theme-text-muted font-bold mt-2 m-0">
                            El pedido volverá a la vendedora y CEDIS no podrá enviarlo hasta corregir.
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted outline-none" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={enviar} className="p-5 space-y-4">
                    <div>
                        <p className={`${THEME_LABEL} mb-2`}>Datos incorrectos</p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            {CAMPOS_ERROR_DATOS.map((campo) => (
                                <label
                                    key={campo.id}
                                    className={`flex items-center gap-2 p-2.5 rounded-xl border text-xs font-bold cursor-pointer ${
                                        seleccionados.includes(campo.id)
                                            ? 'border-orange-500/50 bg-orange-500/10 text-orange-700'
                                            : 'theme-border theme-element theme-text-main'
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={seleccionados.includes(campo.id)}
                                        onChange={() => toggle(campo.id)}
                                        className="rounded border theme-border"
                                    />
                                    {campo.label}
                                </label>
                            ))}
                        </div>
                    </div>

                    <div>
                        <label htmlFor="detalle-error-datos" className={`${THEME_LABEL} ml-1`}>Detalle (opcional)</label>
                        <textarea
                            id="detalle-error-datos"
                            value={detalle}
                            onChange={(e) => setDetalle(e.target.value)}
                            rows={3}
                            className={`${THEME_TEXTAREA} w-full mt-1.5 text-sm font-bold`}
                            placeholder="Describe el error para la vendedora..."
                        />
                    </div>

                    {error && (
                        <p className="text-xs font-bold text-red-500 m-0 flex items-center gap-1">
                            <AlertTriangle className="w-3.5 h-3.5" /> {error}
                        </p>
                    )}

                    <div className="flex flex-wrap gap-3 pt-2">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} outline-none`}>
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={procesando || seleccionados.length === 0}
                            className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50 ml-auto`}
                        >
                            <AlertTriangle className="w-4 h-4" />
                            {procesando ? 'Reportando…' : 'Reportar error'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
