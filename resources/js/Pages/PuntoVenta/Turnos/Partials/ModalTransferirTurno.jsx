import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { deferModalAction, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import { reportarMensajeOperacion } from '../../../../utils/geliaToast';
import AvisoDisponibilidadPersona from '../../Operacion/Partials/AvisoDisponibilidadPersona';
import { mensajeAvisoDisponibilidad } from '../../Operacion/Partials/operacionUtils';

export default function ModalTransferirTurno({
    abierto,
    personas = [],
    servidorAt,
    procesando = false,
    onClose,
    onConfirmar,
}) {
    const [destinoId, setDestinoId] = useState('');

    useEffect(() => {
        if (!abierto) {
            setDestinoId('');
        }
    }, [abierto]);

    const destino = personas.find((persona) => String(persona.id) === String(destinoId)) ?? null;
    const aviso = mensajeAvisoDisponibilidad(destino?.estado_vendedor, 'transferencia');

    const confirmar = () => {
        if (!destinoId) {
            reportarMensajeOperacion('Selecciona la persona destino.');
            return;
        }
        onConfirmar?.(Number(destinoId));
    };

    if (!abierto) return null;

    const cerrar = (event) => {
        event?.stopPropagation?.();
        deferModalAction(onClose);
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-end sm:items-center py-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 15)' }}
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-md w-full p-6 space-y-5 modal-pop`}
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-transferir-titulo"
            >
                <h3 id="modal-transferir-titulo" className="text-base font-semibold theme-text-main m-0">
                    Transferir turno
                </h3>
                <p className="text-sm theme-text-muted m-0">
                    Elige a quién pasará la atención en la sucursal activa.
                </p>

                <fieldset className="min-w-0 border-0 p-0 m-0">
                    <legend className="text-sm font-medium theme-text-main mb-2">Persona destino</legend>
                    {personas.length === 0 ? (
                        <p className="text-sm theme-text-muted m-0">No hay personas disponibles para transferir.</p>
                    ) : (
                        <div className="max-h-64 overflow-y-auto overscroll-contain space-y-2">
                            {personas.map((persona) => {
                                const id = `destino-transferencia-${persona.id}`;
                                const seleccionada = String(destinoId) === String(persona.id);

                                return (
                                    <label
                                        key={persona.id}
                                        htmlFor={id}
                                        className={`flex items-center gap-3 min-h-[44px] rounded-xl border px-3 py-2 cursor-pointer focus-within:ring-2 focus-within:ring-[var(--color-primario)] ${
                                            seleccionada
                                                ? 'border-[color:var(--color-primario)] theme-element'
                                                : 'theme-border theme-surface'
                                        }`}
                                    >
                                        <input
                                            id={id}
                                            type="radio"
                                            name="destino-transferencia"
                                            className="shrink-0"
                                            value={persona.id}
                                            checked={seleccionada}
                                            disabled={procesando}
                                            onChange={() => setDestinoId(String(persona.id))}
                                        />
                                        <span className="min-w-0 flex-1 text-sm font-semibold theme-text-main truncate">
                                            {persona.nombre || persona.primer_nombre}
                                        </span>
                                        <AvisoDisponibilidadPersona
                                            estado={persona.estado_vendedor}
                                            cronometro={persona.cronometro}
                                            servidorAt={servidorAt}
                                        />
                                    </label>
                                );
                            })}
                        </div>
                    )}
                </fieldset>

                <p className="text-sm theme-text-main m-0 min-h-[1.25rem]" aria-live="polite">
                    {aviso}
                </p>

                <div className="flex flex-col gap-3">
                    <button
                        type="button"
                        className="theme-btn-primary theme-btn-primary--compact w-full min-h-[44px] hover:!scale-100"
                        disabled={procesando || personas.length === 0}
                        onClick={confirmar}
                    >
                        Confirmar transferencia
                    </button>
                    <button
                        type="button"
                        className="min-h-[44px] rounded-xl border text-sm font-semibold theme-border theme-element theme-text-main"
                        disabled={procesando}
                        onClick={cerrar}
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
