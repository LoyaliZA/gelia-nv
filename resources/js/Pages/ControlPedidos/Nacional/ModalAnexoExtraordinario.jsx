import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { Plus, X } from 'lucide-react';
import { THEME_INPUT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_LABEL, BTN_PRIMARY, BTN_SECONDARY } from '../Partials/pedidosBmaStyles';

const opcionesJson = { headers: { Accept: 'application/json' } };
const ARCHIVOS = 'application/pdf,image/jpeg,image/png,image/webp';
const piezaVacia = () => ({ clave: uuidSolicitud(), sku: '', descripcion: '', cantidad: '1', archivo: null });

/**
 * Anexo extraordinario: minipedido nacional relacionado con el pedido principal.
 * Hereda cliente, almacén de salida, paquetería y dirección; solo se capturan las piezas del anexo.
 */
export default function ModalAnexoExtraordinario({ abierto, onClose, pedido, onCreado }) {
    const [folioWizerp, setFolioWizerp] = useState('');
    const [importe, setImporte] = useState('');
    const [documento, setDocumento] = useState(null);
    const [piezas, setPiezas] = useState([piezaVacia()]);
    const [nota, setNota] = useState('');
    const [error, setError] = useState('');
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);
    const ocupado = useRef(false);
    const solicitud = useRef(uuidSolicitud());
    const cierre = useRef(null);

    // Devuelve el foco a quien abrió el modal y permite cerrarlo con Escape.
    useEffect(() => {
        if (!abierto) return undefined;
        const abridor = document.activeElement;
        const alTeclear = (evento) => { if (evento.key === 'Escape' && !ocupado.current) onClose?.(); };
        document.addEventListener('keydown', alTeclear);
        return () => {
            document.removeEventListener('keydown', alTeclear);
            if (abridor && typeof abridor.focus === 'function') abridor.focus();
        };
    }, [abierto, onClose]);

    useEffect(() => {
        if (!abierto) return;
        setErrores({});
        setFolioWizerp('');
        setImporte('');
        setDocumento(null);
        setPiezas([piezaVacia()]);
        setNota('');
        setError('');
        solicitud.current = uuidSolicitud();
        cierre.current?.focus();
    }, [abierto]);

    if (!abierto || !pedido) return null;

    const validarCampo = (campo, valor) => {
        const mensaje = {
            folio: !String(valor).trim() ? 'Capture el folio Wizerp del anexo.' : '',
            importe: !(Number(String(valor).replace(',', '.')) > 0) ? 'Indique un importe mayor a cero.' : '',
            documento: !valor ? 'Adjunte el documento del anexo.' : '',
        }[campo];
        setErrores((previos) => ({ ...previos, [campo]: mensaje }));
    };
    const limpiarCampo = (campo) => setErrores((previos) => (previos[campo] ? { ...previos, [campo]: '' } : previos));
    const propiedadesCampo = (campo) => ({
        'aria-invalid': errores[campo] ? 'true' : undefined,
        'aria-describedby': errores[campo] ? `anexo-error-${campo}` : undefined,
    });
    const mensajeCampo = (campo) => (errores[campo]
        ? <p id={`anexo-error-${campo}`} className="m-0 mt-1 text-xs theme-text-aviso">{errores[campo]}</p>
        : null);

    const cambiarPieza = (clave, cambios) => setPiezas((previas) => previas.map((pieza) => (pieza.clave === clave ? { ...pieza, ...cambios } : pieza)));

    const faltantes = () => {
        const falta = [];
        if (!folioWizerp.trim()) falta.push('folio Wizerp del anexo');
        if (!(Number(String(importe).replace(',', '.')) > 0)) falta.push('importe de mercancía');
        if (!documento) falta.push('documento del anexo');
        piezas.forEach((pieza, indice) => {
            if (!pieza.descripcion.trim()) falta.push(`descripción de la pieza ${indice + 1}`);
            else if (!pieza.sku.trim() && !pieza.archivo) falta.push(`pieza ${indice + 1}: indique el SKU o adjunte la captura`);
        });
        return falta;
    };

    const enviar = async (evento) => {
        evento.preventDefault();
        if (ocupado.current) return;
        const falta = faltantes();
        if (falta.length) {
            setError(`Complete antes de enviar: ${falta.join(', ')}.`);
            return;
        }
        ocupado.current = true;
        setEnviando(true);
        setError('');
        const cuerpo = new FormData();
        cuerpo.append('solicitud_id', solicitud.current);
        cuerpo.append('revision_esperada', String(pedido.revision_registro ?? 0));
        cuerpo.append('base_captura', pedido.base_captura);
        cuerpo.append('folio_wizerp', folioWizerp.trim());
        cuerpo.append('total_mercancia_nueva', String(importe).replace(',', '.'));
        if (nota.trim()) cuerpo.append('nota', nota.trim());
        cuerpo.append('documento', documento);
        piezas.forEach((pieza, indice) => {
            if (pieza.sku.trim()) cuerpo.append(`piezas[${indice}][sku]`, pieza.sku.trim());
            cuerpo.append(`piezas[${indice}][descripcion]`, pieza.descripcion.trim());
            cuerpo.append(`piezas[${indice}][cantidad]`, String(Math.max(1, parseInt(pieza.cantidad, 10) || 1)));
            if (pieza.archivo) cuerpo.append(`piezas[${indice}][evidencia]`, pieza.archivo);
        });
        try {
            const { data } = await axios.post(`/control-pedidos/${pedido.id}/anexo-extraordinario`, cuerpo, opcionesJson);
            onCreado?.(data.anexo);
            onClose();
        } catch (fallo) {
            const datos = fallo.response?.status < 500 ? fallo.response.data : null;
            setError(Object.values(datos?.errors || {}).flat()[0] || datos?.message || 'No se pudo crear el anexo. Intente de nuevo.');
        } finally {
            ocupado.current = false;
            setEnviando(false);
        }
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center`} onClick={onClose}>
            <form
                role="dialog" aria-modal="true" aria-labelledby="anexo-titulo"
                className={`${THEME_MODAL_SHELL} w-full max-w-2xl max-h-[90vh] flex flex-col`}
                onClick={(evento) => evento.stopPropagation()}
                onSubmit={enviar}
                noValidate
            >
                <div className="p-5 border-b theme-border flex items-start justify-between gap-3 shrink-0">
                    <div>
                        <p id="anexo-titulo" className={`${THEME_LABEL} m-0`}>Anexo extraordinario</p>
                        <p className="m-0 mt-1 text-xs theme-text-muted">
                            Pedido {pedido.folio_remision || pedido.folio}. Hereda cliente, almacén de salida, paquetería y dirección.
                            Se envía como un minipedido aparte, con su propia consulta, pago y guía.
                        </p>
                    </div>
                    <button type="button" ref={cierre} onClick={onClose} className="p-2 min-h-[44px] min-w-[44px] rounded-full theme-text-muted inline-flex items-center justify-center" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="p-5 overflow-y-auto space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className={THEME_LABEL} htmlFor="anexo-folio-wizerp">Folio Wizerp del anexo</label>
                            <input id="anexo-folio-wizerp" className={`${THEME_INPUT} w-full min-h-[44px] mt-1`} value={folioWizerp} disabled={enviando}
                                {...propiedadesCampo('folio')}
                                onBlur={(evento) => validarCampo('folio', evento.target.value)}
                                onChange={(evento) => { setFolioWizerp(evento.target.value); setError(''); limpiarCampo('folio'); }} />
                            {mensajeCampo('folio')}
                        </div>
                        <div>
                            <label className={THEME_LABEL} htmlFor="anexo-importe">Importe de mercancía del anexo</label>
                            <input id="anexo-importe" className={`${THEME_INPUT} w-full min-h-[44px] mt-1`} inputMode="decimal" value={importe} disabled={enviando}
                                {...propiedadesCampo('importe')}
                                onBlur={(evento) => validarCampo('importe', evento.target.value)}
                                onChange={(evento) => { setImporte(evento.target.value); setError(''); limpiarCampo('importe'); }} />
                            {mensajeCampo('importe')}
                        </div>
                    </div>
                    <div>
                        <label className={THEME_LABEL} htmlFor="anexo-documento">Documento del anexo (PDF o captura del minipedido)</label>
                        <input id="anexo-documento" type="file" accept={ARCHIVOS} className={`${THEME_INPUT} w-full min-h-[44px] mt-1`} disabled={enviando}
                            {...propiedadesCampo('documento')}
                            onBlur={() => validarCampo('documento', documento)}
                            onChange={(evento) => { setDocumento(evento.target.files?.[0] || null); setError(''); limpiarCampo('documento'); }} />
                        {mensajeCampo('documento')}
                    </div>

                    <fieldset className="space-y-3 border-0 p-0 m-0">
                        <legend className={`${THEME_LABEL} mb-1`}>Piezas del anexo</legend>
                        {piezas.map((pieza, indice) => (
                            <div key={pieza.clave} className="rounded-xl border theme-border p-3 space-y-3">
                                <div className="grid grid-cols-1 sm:grid-cols-[1fr_2fr_6rem] gap-3">
                                    <div>
                                        <label className="text-xs theme-text-muted" htmlFor={`anexo-pieza-${indice}-sku`}>SKU (si lo conoce)</label>
                                        <input id={`anexo-pieza-${indice}-sku`} className={`${THEME_INPUT} w-full min-h-[44px] mt-1`} value={pieza.sku} disabled={enviando}
                                            onChange={(evento) => { cambiarPieza(pieza.clave, { sku: evento.target.value }); setError(''); }} />
                                    </div>
                                    <div>
                                        <label className="text-xs theme-text-muted" htmlFor={`anexo-pieza-${indice}-descripcion`}>Descripción</label>
                                        <input id={`anexo-pieza-${indice}-descripcion`} className={`${THEME_INPUT} w-full min-h-[44px] mt-1`} value={pieza.descripcion} disabled={enviando}
                                            onChange={(evento) => { cambiarPieza(pieza.clave, { descripcion: evento.target.value }); setError(''); }} />
                                    </div>
                                    <div>
                                        <label className="text-xs theme-text-muted" htmlFor={`anexo-pieza-${indice}-cantidad`}>Cantidad</label>
                                        <input id={`anexo-pieza-${indice}-cantidad`} type="number" min="1" step="1" className={`${THEME_INPUT} w-full min-h-[44px] mt-1`} value={pieza.cantidad} disabled={enviando}
                                            onChange={(evento) => cambiarPieza(pieza.clave, { cantidad: evento.target.value })} />
                                    </div>
                                </div>
                                <div className="flex flex-wrap items-end gap-3">
                                    <div className="min-w-0 flex-1">
                                        <label className="text-xs theme-text-muted" htmlFor={`anexo-pieza-${indice}-archivo`}>Captura o documento de la pieza (obligatorio sin SKU)</label>
                                        <input id={`anexo-pieza-${indice}-archivo`} type="file" accept={ARCHIVOS} className={`${THEME_INPUT} w-full min-h-[44px] mt-1`} disabled={enviando}
                                            onChange={(evento) => { cambiarPieza(pieza.clave, { archivo: evento.target.files?.[0] || null }); setError(''); }} />
                                    </div>
                                    {piezas.length > 1 && (
                                        <button type="button" className={`${BTN_SECONDARY} min-h-[44px]`} disabled={enviando}
                                            onClick={() => setPiezas((previas) => previas.filter((otra) => otra.clave !== pieza.clave))}>
                                            Quitar pieza {indice + 1}
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                        {piezas.length < 20 && (
                            <button type="button" className={`${BTN_SECONDARY} min-h-[44px] inline-flex items-center gap-2`} disabled={enviando}
                                onClick={() => setPiezas((previas) => [...previas, piezaVacia()])}>
                                <Plus className="w-4 h-4" aria-hidden="true" /> Agregar pieza
                            </button>
                        )}
                    </fieldset>

                    <div>
                        <label className={THEME_LABEL} htmlFor="anexo-nota">Nota para la auxiliar (opcional)</label>
                        <textarea id="anexo-nota" rows={3} className={`${THEME_TEXTAREA} w-full mt-1`} value={nota} disabled={enviando}
                            onChange={(evento) => setNota(evento.target.value)} />
                    </div>
                </div>

                <div className="p-5 border-t theme-border shrink-0 space-y-3">
                    {error && <p className="m-0 text-sm theme-text-aviso" role="alert">{error}</p>}
                    <div className="flex justify-end gap-2">
                        <button type="button" className={`${BTN_SECONDARY} min-h-[44px]`} onClick={onClose} disabled={enviando}>Cancelar</button>
                        <button type="submit" className={`${BTN_PRIMARY} min-h-[44px]`} disabled={enviando}>
                            {enviando ? 'Enviando…' : 'Enviar a consulta'}
                        </button>
                    </div>
                </div>
            </form>
        </div>,
        document.body,
    );
}
