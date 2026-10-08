import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { Camera, Loader2, X } from 'lucide-react';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import BotonesCapturaEvidencia from './BotonesCapturaEvidencia';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';

function claveEvidencia() {
    return `pdv:evi:${Date.now()}:${Math.random().toString(36).slice(2, 10)}`;
}

export default function AccionEvidenciaRegistroManual({ resguardo, onExito, className = '' }) {
    const [abierto, setAbierto] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);
    const [ticket, setTicket] = useState(null);
    const [paquete, setPaquete] = useState(null);
    const usos = new Set((resguardo?.registro_manual?.evidencias || []).map((item) => item.uso));
    const faltaTicket = !usos.has('ticket');
    const faltaPaquete = !usos.has('paquete');

    useToastAlCambiar(error, 'error');

    useEffect(() => () => {
        if (ticket?.preview) URL.revokeObjectURL(ticket.preview);
        if (paquete?.preview) URL.revokeObjectURL(paquete.preview);
    }, [ticket, paquete]);

    const asignar = (setter, archivo) => {
        setter(archivo
            ? {
                archivo,
                preview: archivo.type?.startsWith('image/') ? URL.createObjectURL(archivo) : null,
            }
            : null);
    };

    const cerrar = () => {
        if (enviando) return;
        setError(null);
        setAbierto(false);
    };

    const enviar = async () => {
        if (enviando) return;
        if ((faltaTicket && !ticket) || (faltaPaquete && !paquete)) {
            setError('Adjunte el ticket y la foto del paquete.');
            return;
        }

        setEnviando(true);
        setError(null);
        const formData = new FormData();
        formData.append('idempotency_key', claveEvidencia());
        if (ticket?.archivo) formData.append('archivo_ticket', ticket.archivo);
        if (paquete?.archivo) formData.append('foto_paquete', paquete.archivo);

        try {
            const { data } = await axios.post(
                route('punto_venta.resguardos.registro_manual.evidencia', resguardo.id),
                formData,
                { headers: { Accept: 'application/json' } },
            );
            setAbierto(false);
            onExito?.(data);
        } catch (err) {
            const data = err?.response?.data;
            const primero = data?.errors ? Object.values(data.errors).flat()[0] : null;
            setError(primero || data?.message || 'No se pudo guardar la evidencia.');
        } finally {
            setEnviando(false);
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setAbierto(true)}
                className={className}
            >
                <Camera className="w-4 h-4 shrink-0" aria-hidden />
                <span>Completar evidencia</span>
            </button>
            {abierto && createPortal(
                <div
                    className={`${THEME_MODAL_OVERLAY} items-end sm:items-center p-0 sm:p-4`}
                    onClick={cerrar}
                >
                    <div
                        className={`${THEME_MODAL_SHELL} w-full sm:max-w-lg max-h-[92dvh] flex flex-col rounded-t-3xl sm:rounded-3xl overflow-hidden`}
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div className="p-5 border-b theme-border flex items-start justify-between gap-3">
                            <div>
                                <h3 className="text-base font-black uppercase theme-text-main m-0">Evidencia del resguardo</h3>
                                <p className="text-xs theme-text-muted m-0 mt-1">
                                    Se usa para recepción y custodia. Al completarla, el paquete queda en recepción.
                                </p>
                            </div>
                            <button type="button" onClick={cerrar} className={`${THEME_BTN_SECONDARY} p-2 min-h-[44px] min-w-[44px]`} aria-label="Cerrar">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        <div className="p-5 space-y-4 overflow-y-auto">
                            {faltaTicket && (
                                <div className="space-y-2">
                                    <p className="text-xs font-semibold theme-text-main m-0">Ticket</p>
                                    <BotonesCapturaEvidencia
                                        onAgregar={(archivos) => asignar(setTicket, archivos?.[0] || null)}
                                        deshabilitado={enviando}
                                        camaraMultiple={false}
                                        galeriaMultiple={false}
                                        acceptGaleria="image/*,application/pdf"
                                        etiquetaGaleria="Archivo"
                                    />
                                    {ticket?.preview && <img src={ticket.preview} alt="Ticket" className="w-full h-28 object-cover rounded-2xl" />}
                                </div>
                            )}
                            {faltaPaquete && (
                                <div className="space-y-2">
                                    <p className="text-xs font-semibold theme-text-main m-0">Foto del paquete</p>
                                    <BotonesCapturaEvidencia
                                        onAgregar={(archivos) => asignar(setPaquete, archivos?.[0] || null)}
                                        deshabilitado={enviando}
                                        camaraMultiple={false}
                                        galeriaMultiple={false}
                                        acceptGaleria="image/*"
                                        etiquetaGaleria="Galería"
                                    />
                                    {paquete?.preview && <img src={paquete.preview} alt="Paquete" className="w-full h-28 object-cover rounded-2xl" />}
                                </div>
                            )}
                            <button
                                type="button"
                                onClick={enviar}
                                disabled={enviando}
                                className={`${THEME_BTN_PRIMARY} w-full min-h-[44px] inline-flex items-center justify-center gap-2`}
                            >
                                {enviando && <Loader2 className="w-4 h-4 animate-spin" aria-hidden />}
                                Guardar evidencia
                            </button>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </>
    );
}
