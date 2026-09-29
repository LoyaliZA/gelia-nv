import React, { useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';
import { textoEliminacion } from '@/utils/estadoPublicidadPdv';
import useModalPublicidad from '../useModalPublicidad';
import FormularioPublicidad from './FormularioPublicidad';

function formularioDesde(item) {
    return {
        alcance: item.alcance,
        ajuste: item.ajuste || 'cover',
        duracion_seg: item.duracion_seg ?? 10,
        vigente_desde: item.vigente_desde || '',
        vigente_hasta: item.vigente_hasta || '',
        eliminar_automaticamente: Boolean(item.eliminar_automaticamente),
        conservar_dias: item.conservar_dias || 30,
    };
}

export default function ModalEditarPublicidad({ item, onClose, onGuardar }) {
    const ref = useRef(null);
    const [form, setForm] = useState(() => formularioDesde(item));
    const [guardando, setGuardando] = useState(false);
    useModalPublicidad(true, onClose, ref);

    const guardar = async () => {
        setGuardando(true);
        try {
            await onGuardar(item, form);
            onClose();
        } catch {
            // El listado ya muestra el error.
        } finally {
            setGuardando(false);
        }
    };

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div
                ref={ref}
                role="dialog"
                aria-modal="true"
                aria-labelledby="editar-publicidad-titulo"
                className={`${THEME_MODAL_SHELL} max-w-2xl w-full max-h-[90vh] overflow-y-auto p-4 md:p-6 space-y-4`}
                onClick={(event) => event.stopPropagation()}
            >
                <h2 id="editar-publicidad-titulo" className="text-lg font-black italic uppercase tracking-tighter theme-text-main m-0">
                    Editar {item.nombre_original || 'pieza'}
                </h2>
                <FormularioPublicidad
                    valor={form}
                    onChange={setForm}
                    tipo={item.tipo}
                    modo="edicion"
                    duracionVideo={item.duracion_seg}
                />
                {form.eliminar_automaticamente && textoEliminacion(item) ? (
                    <p className="text-sm theme-text-muted m-0">{textoEliminacion(item)}</p>
                ) : null}
                <div className="flex justify-end gap-2">
                    <button type="button" className={THEME_BTN_SECONDARY} onClick={onClose}>Cancelar</button>
                    <button type="button" className={THEME_BTN_PRIMARY} disabled={guardando} onClick={guardar}>
                        {guardando ? 'Guardando…' : 'Guardar cambios'}
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
