import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { X, AlertTriangle } from 'lucide-react';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from './pedidosBmaStyles';
import DireccionPedidoResumen from './DireccionPedidoResumen';

export default function ModalCambiarDireccion({ abierto, onClose, pedido }) {
    const { auth } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p) || auth?.user?.roles?.includes('Super Admin');

    const [direcciones, setDirecciones] = useState([]);
    const [cargando, setCargando] = useState(false);
    const { data, setData, post, processing, reset, errors } = useForm({
        cliente_direccion_id: '',
        motivo: '',
    });

    const tieneGuia = Boolean(pedido?.numero_rastreo)
        || (pedido?.documentos || []).some((d) => d.tipo === 'guia');

    useEffect(() => {
        if (!abierto || !pedido?.cliente_id) return;
        setCargando(true);
        axios.get(`/api/clientes/id/${pedido.cliente_id}/direccion-envio`)
            .then((res) => setDirecciones(res.data?.direcciones || []))
            .catch(() => setDirecciones([]))
            .finally(() => setCargando(false));
    }, [abierto, pedido?.cliente_id]);

    if (!abierto || !pedido) return null;

    if (!can('control_pedidos.direccion.cambiar')) {
        return null;
    }

    const seleccionada = direcciones.find((d) => String(d.id) === String(data.cliente_direccion_id));

    const enviar = (e) => {
        e.preventDefault();
        if (tieneGuia && !window.confirm('Este pedido tiene guía. El cambio invalidará la guía vigente y regresará a pendiente de guía. ¿Continuar?')) {
            return;
        }
        post(route('control_pedidos.cambiar_direccion', pedido.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center`} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} max-w-lg w-full`} onClick={(e) => e.stopPropagation()}>
                <div className="p-5 border-b theme-border flex justify-between items-start">
                    <div>
                        <p className={`${THEME_LABEL} m-0`}>Cambiar dirección de envío</p>
                        <p className="text-xs theme-text-muted mt-1 m-0">Pedido {pedido.folio_remision || pedido.folio}</p>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <form onSubmit={enviar} className="p-5 space-y-4">
                    {tieneGuia && (
                        <div className="flex gap-2 p-3 rounded-xl border border-amber-500/40 bg-amber-500/10 text-xs font-bold">
                            <AlertTriangle className="w-4 h-4 text-amber-500 shrink-0" />
                            Hay guía registrada: se invalidará al confirmar el cambio.
                        </div>
                    )}
                    <label className="block text-sm">
                        <span className={THEME_LABEL}>Nueva dirección verificada</span>
                        <select
                            className={`${THEME_SELECT} w-full mt-1 py-3`}
                            value={data.cliente_direccion_id}
                            onChange={(e) => setData('cliente_direccion_id', e.target.value)}
                            required
                            disabled={cargando}
                        >
                            <option value="">{cargando ? 'Cargando…' : 'Seleccionar…'}</option>
                            {direcciones.map((d) => (
                                <option key={d.id} value={d.id}>
                                    #{d.numero_direccion} {d.etiqueta || ''} — {d.nombre_destinatario}
                                </option>
                            ))}
                        </select>
                        {errors.cliente_direccion_id && <span className="text-xs text-red-500">{errors.cliente_direccion_id}</span>}
                    </label>
                    {seleccionada && <DireccionPedidoResumen direccion={seleccionada} />}
                    <label className="block text-sm">
                        <span className={THEME_LABEL}>Motivo del cambio (obligatorio)</span>
                        <textarea
                            className={`${THEME_TEXTAREA} w-full mt-1`}
                            value={data.motivo}
                            onChange={(e) => setData('motivo', e.target.value)}
                            required
                            rows={3}
                        />
                        {errors.motivo && <span className="text-xs text-red-500">{errors.motivo}</span>}
                    </label>
                    <div className="flex justify-end gap-2">
                        <button type="button" className={BTN_SECONDARY} onClick={onClose}>Cancelar</button>
                        <button type="submit" className={BTN_PRIMARY} disabled={processing}>
                            {processing ? 'Guardando…' : 'Confirmar cambio'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
