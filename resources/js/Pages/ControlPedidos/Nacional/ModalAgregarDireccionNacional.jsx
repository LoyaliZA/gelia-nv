import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { X } from 'lucide-react';
import CamposDireccionPedido, { CAMPOS_DIRECCION_VACIOS } from '../Partials/CamposDireccionPedido';
import { faltantesManualDireccion } from '../Partials/elegirDireccionParaPedido';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_LABEL, BTN_PRIMARY, BTN_SECONDARY } from '../Partials/pedidosBmaStyles';

const opcionesJson = { headers: { Accept: 'application/json' } };

/**
 * Alta de una dirección verificada del cliente sin salir del pedido nacional (N-07 / N-09).
 * La primera dirección del cliente queda como principal; las demás, como adicionales.
 */
export default function ModalAgregarDireccionNacional({ abierto, onClose, pedido, esPrimera, onGuardada }) {
    const [campos, setCampos] = useState({ ...CAMPOS_DIRECCION_VACIOS });
    const [guardando, setGuardando] = useState(false);
    const [error, setError] = useState('');
    const cierre = useRef(null);

    // Devuelve el foco a quien abrió el modal y permite cerrarlo con Escape.
    useEffect(() => {
        if (!abierto) return undefined;
        const abridor = document.activeElement;
        const alTeclear = (evento) => { if (evento.key === 'Escape') onClose?.(); };
        document.addEventListener('keydown', alTeclear);
        return () => {
            document.removeEventListener('keydown', alTeclear);
            if (abridor && typeof abridor.focus === 'function') abridor.focus();
        };
    }, [abierto, onClose]);

    useEffect(() => {
        if (abierto) {
            setCampos({ ...CAMPOS_DIRECCION_VACIOS });
            setError('');
            cierre.current?.focus();
        }
    }, [abierto]);

    if (!abierto) return null;

    const guardar = async (evento) => {
        evento.preventDefault();
        if (guardando) return;
        const faltan = faltantesManualDireccion(campos);
        if (faltan.length) {
            setError(`Complete antes de guardar: ${faltan.join(', ')}.`);
            return;
        }
        setGuardando(true);
        setError('');
        try {
            const { data } = await axios.post('/control-pedidos/registrar-direccion-catalogo', {
                cliente_id: pedido.cliente_id,
                pedido_id: pedido.id,
                es_principal: Boolean(esPrimera),
                ...campos,
            }, opcionesJson);
            onGuardada(data.direccion);
            onClose();
        } catch (fallo) {
            const datos = fallo.response?.status < 500 ? fallo.response.data : null;
            setError(Object.values(datos?.errors || {}).flat()[0] || datos?.message || 'No se pudo guardar la dirección. Intente de nuevo.');
        } finally {
            setGuardando(false);
        }
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center`} onClick={onClose}>
            <form
                role="dialog" aria-modal="true" aria-labelledby="agregar-direccion-titulo"
                className={`${THEME_MODAL_SHELL} w-full max-w-2xl max-h-[90vh] flex flex-col`}
                onClick={(evento) => evento.stopPropagation()}
                onSubmit={guardar}
            >
                <div className="p-5 border-b theme-border flex items-start justify-between gap-3 shrink-0">
                    <div>
                        <p id="agregar-direccion-titulo" className={`${THEME_LABEL} m-0`}>Agregar dirección de entrega</p>
                        <p className="m-0 mt-1 text-xs theme-text-muted">Pedido {pedido.folio_remision || pedido.folio}. Se guarda como dirección verificada del cliente.</p>
                    </div>
                    <button type="button" ref={cierre} onClick={onClose} className="p-2 min-h-[44px] min-w-[44px] rounded-full theme-text-muted inline-flex items-center justify-center" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="p-5 overflow-y-auto">
                    <CamposDireccionPedido valores={campos} onChange={setCampos} disabled={guardando} />
                </div>
                <div className="p-5 border-t theme-border shrink-0 space-y-3">
                    {error && <p className="m-0 text-sm theme-text-aviso" role="alert">{error}</p>}
                    <div className="flex justify-end gap-2">
                        <button type="button" className={`${BTN_SECONDARY} min-h-[44px]`} onClick={onClose} disabled={guardando}>Cancelar</button>
                        <button type="submit" className={`${BTN_PRIMARY} min-h-[44px]`} disabled={guardando}>
                            {guardando ? 'Guardando…' : 'Guardar dirección'}
                        </button>
                    </div>
                </div>
            </form>
        </div>,
        document.body,
    );
}
