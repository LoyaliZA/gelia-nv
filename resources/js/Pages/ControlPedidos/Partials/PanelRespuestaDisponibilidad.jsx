import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';
import usePedidoDialog from './usePedidoDialog';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_TEXTAREA } from '../../../utils/geliaTheme';

function formDataDe({ confirmacion, excepciones, archivos }) {
    const form = new FormData();
    form.append('confirmacion_explicita', confirmacion ? '1' : '0');
    excepciones.forEach((excepcion, indice) => {
        form.append(`excepciones[${indice}][descripcion]`, excepcion.descripcion);
        form.append(`excepciones[${indice}][cantidad]`, String(excepcion.cantidad));
        form.append(`excepciones[${indice}][condicion]`, excepcion.condicion);
        form.append(`excepciones[${indice}][detalle]`, excepcion.detalle);
    });
    archivos.forEach((archivo) => form.append('evidencias[]', archivo));
    return form;
}

export default function PanelRespuestaDisponibilidad({
    modo = 'seccion',
    abierto = true,
    onClose,
    documentos = [],
    revision = null,
    piezas = [],
    puedeResponder = false,
    tomada = false,
    onEnviar,
}) {
    const [confirmacion, setConfirmacion] = useState(false);
    const [conExcepciones, setConExcepciones] = useState(false);
    const [excepciones, setExcepciones] = useState([{ descripcion: '', cantidad: '1', condicion: 'faltante', detalle: '' }]);
    const [archivos, setArchivos] = useState([]);
    const [error, setError] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const dialogo = usePedidoDialog({ abierto: modo === 'dialogo' && abierto, onClose, bloqueado: ocupado });
    if (modo === 'dialogo' && !abierto) return null;

    const cambiar = (indice, campo, valor) => {
        setExcepciones((previas) => previas.map((fila, i) => (i === indice ? { ...fila, [campo]: valor } : fila)));
    };
    const enviar = async () => {
        if (ocupado || !puedeResponder) return;
        setError('');
        const utiles = conExcepciones
            ? excepciones.filter((fila) => fila.descripcion.trim() || fila.detalle.trim())
            : [];
        if (!conExcepciones && !confirmacion) {
            setError('La revisión no está concluida. Confirme el pedido completo o indique las piezas con excepción.');
            return;
        }
        if (conExcepciones && utiles.length === 0) {
            setError('Indique la pieza, la cantidad y el detalle o faltante.');
            return;
        }
        if (archivos.length === 0) {
            setError('Adjunte la evidencia de la revisión.');
            return;
        }
        setOcupado(true);
        try {
            await onEnviar?.(formDataDe({ confirmacion: !conExcepciones && confirmacion, excepciones: utiles, archivos }));
        } catch (e) {
            const errores = e?.response?.data?.errors;
            setError(errores ? Object.values(errores).flat().join(' ') : (e?.response?.data?.message || 'No se pudo registrar la revisión.'));
        } finally {
            setOcupado(false);
        }
    };

    const cuerpo = (
        <section className="grid gap-3 p-4 border theme-border rounded-xl theme-element" aria-label="Respuesta de disponibilidad">
            <div>
                <h3 className="text-base font-bold theme-text-main m-0">Respuesta de disponibilidad</h3>
                <p className="text-sm theme-text-muted m-0 mt-1">
                    Sin excepciones exige confirmación explícita y evidencia del pedido completo. Con excepciones, indique solo las piezas afectadas.
                </p>
            </div>
            {documentos.length > 0 && (
                <ul className="m-0 pl-0 list-none grid gap-1">
                    {documentos.map((documento) => (
                        <li key={documento.id || documento.url}>
                            {documento.url ? (
                                <a className="text-sm font-semibold theme-text-primario underline" href={documento.url}>
                                    Consultar {documento.nombre_original || 'pedido'}
                                </a>
                            ) : (
                                <span className="text-sm theme-text-main">{documento.nombre_original || 'Pedido adjunto'}</span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {revision?.estado ? (
                <div role="status" className="grid gap-2">
                    <p className="text-sm font-semibold theme-text-main m-0">
                        {revision.estado === 'excepciones_pendientes'
                            ? 'Falta la decisión del cliente sobre las piezas con excepción. Esas piezas no continúan como aceptadas.'
                            : 'La revisión quedó registrada. No confirma pago ni entrega.'}
                    </p>
                    {piezas.length > 0 && (
                        <ul className="m-0 pl-4">
                            {piezas.map((pieza) => (
                                <li key={pieza.id} className="text-sm theme-text-main">
                                    {pieza.descripcion} · {pieza.cantidad}
                                    {pieza.detalle ? ` · ${pieza.detalle}` : ''}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            ) : (
                <>
                    {!tomada && <p className="text-sm theme-text-main m-0">Tome la revisión antes de responder.</p>}
                    {tomada && puedeResponder && (
                        <>
                            <label className="flex items-start gap-2 text-sm theme-text-main">
                                <input
                                    type="checkbox"
                                    className="mt-1"
                                    checked={confirmacion}
                                    disabled={conExcepciones || ocupado}
                                    onChange={(evento) => setConfirmacion(evento.target.checked)}
                                />
                                Confirmo el pedido completo, sin excepciones.
                            </label>
                            <label className="flex items-start gap-2 text-sm theme-text-main">
                                <input
                                    type="checkbox"
                                    className="mt-1"
                                    checked={conExcepciones}
                                    disabled={ocupado}
                                    onChange={(evento) => {
                                        setConExcepciones(evento.target.checked);
                                        if (evento.target.checked) setConfirmacion(false);
                                    }}
                                />
                                Hay piezas con detalle o faltante.
                            </label>
                            {conExcepciones && excepciones.map((fila, indice) => (
                                <div key={indice} className="grid gap-2 md:grid-cols-2">
                                    <div>
                                        <label className={THEME_LABEL} htmlFor={`excepcion-pieza-${indice}`}>Pieza</label>
                                        <input id={`excepcion-pieza-${indice}`} className={`${THEME_INPUT} w-full min-h-[44px] text-base`} value={fila.descripcion} onChange={(evento) => cambiar(indice, 'descripcion', evento.target.value)} />
                                    </div>
                                    <div>
                                        <label className={THEME_LABEL} htmlFor={`excepcion-cantidad-${indice}`}>Cantidad afectada</label>
                                        <input id={`excepcion-cantidad-${indice}`} className={`${THEME_INPUT} w-full min-h-[44px] text-base`} inputMode="numeric" value={fila.cantidad} onChange={(evento) => cambiar(indice, 'cantidad', evento.target.value)} />
                                    </div>
                                    <div>
                                        <label className={THEME_LABEL} htmlFor={`excepcion-condicion-${indice}`}>Condición</label>
                                        <select id={`excepcion-condicion-${indice}`} className={`${THEME_INPUT} w-full min-h-[44px] text-base`} value={fila.condicion} onChange={(evento) => cambiar(indice, 'condicion', evento.target.value)}>
                                            <option value="faltante">Faltante</option>
                                            <option value="detalle">Detalle</option>
                                        </select>
                                    </div>
                                    <div className="md:col-span-2">
                                        <label className={THEME_LABEL} htmlFor={`excepcion-detalle-${indice}`}>Detalle o faltante</label>
                                        <textarea id={`excepcion-detalle-${indice}`} className={`${THEME_TEXTAREA} w-full text-base`} rows={2} value={fila.detalle} onChange={(evento) => cambiar(indice, 'detalle', evento.target.value)} />
                                    </div>
                                </div>
                            ))}
                            {conExcepciones && (
                                <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px] justify-self-start`} onClick={() => setExcepciones((previas) => [...previas, { descripcion: '', cantidad: '1', condicion: 'faltante', detalle: '' }])}>
                                    Otra pieza
                                </button>
                            )}
                            <div>
                                <label className={THEME_LABEL} htmlFor="evidencia-disponibilidad">
                                    {conExcepciones ? 'Evidencia de la excepción' : 'Evidencia del pedido completo'}
                                </label>
                                <input id="evidencia-disponibilidad" className="block w-full text-base" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" onChange={(evento) => setArchivos([...(evento.target.files || [])])} />
                            </div>
                            {error && <p className="text-sm theme-text-main m-0" role="alert">{error}</p>}
                            <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] justify-self-start`} disabled={ocupado} onClick={enviar}>
                                {ocupado ? 'Enviando…' : 'Registrar revisión'}
                            </button>
                        </>
                    )}
                </>
            )}
        </section>
    );

    if (modo !== 'dialogo') return cuerpo;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-end md:items-center p-0 md:p-4`}>
            <div {...dialogo} aria-label="Respuesta de disponibilidad" className={`${THEME_MODAL_SHELL} w-full max-w-2xl max-h-[100dvh] md:max-h-[90dvh] overflow-y-auto`}>
                <header className="flex items-center justify-between gap-3 px-4 py-3 border-b theme-border">
                    <h2 className="text-base font-bold theme-text-main m-0">Consulta de disponibilidad</h2>
                    <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} onClick={onClose} aria-label="Cerrar consulta">
                        <X className="w-5 h-5" />
                    </button>
                </header>
                {cuerpo}
            </div>
        </div>,
        document.body,
    );
}
