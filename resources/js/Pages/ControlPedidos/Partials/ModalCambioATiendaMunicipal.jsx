import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { X, Upload, Store } from 'lucide-react';
import { THEME_SELECT, THEME_INPUT } from '../../../utils/geliaTheme';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from './pedidosBmaStyles';
import { mensajeDeError } from './ModalPiezasAdicionales';

const SECCION = `${THEME_LABEL} mb-2 block`;
const MAX_MOTIVO = 200;
const MAX_ARCHIVOS = 8;

/**
 * Ventas pide que el cliente recoja en sucursal un envío municipal con compra registrada. Solo registra la solicitud:
 * no mueve mercancía ni cambia pagos o remisión. La verificación la hace el área que tiene el paquete.
 */
export default function ModalCambioATiendaMunicipal({ abierto, pedido, onClose, onActualizado }) {
    const cambio = pedido?.cambio_a_tienda || null;
    const [solicitudId, setSolicitudId] = useState(() => uuidSolicitud());
    const [motivo, setMotivo] = useState('');
    const [errorCaptura, setErrorCaptura] = useState(false);
    const [archivos, setArchivos] = useState([]);
    const [destino, setDestino] = useState('');
    const [error, setError] = useState('');
    const [procesando, setProcesando] = useState(false);

    useEffect(() => {
        if (!abierto) return;
        setSolicitudId(uuidSolicitud());
        setMotivo('');
        setErrorCaptura(false);
        setArchivos([]);
        setDestino(String(cambio?.sucursal_destino_id ?? ''));
        setError('');
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido || !cambio) return null;
    const folio = pedido.folio_remision || pedido.folio;
    const sucursales = cambio.sucursales || [];
    const cambiaDestino = destino !== '' && Number(destino) !== Number(cambio.sucursal_destino_id);
    const exigeGerencia = cambiaDestino && cambio.ubicacion !== 'cedis';

    const guardar = async (e) => {
        e.preventDefault();
        setError('');
        const datos = new FormData();
        datos.append('solicitud_id', solicitudId);
        datos.append('motivo', motivo.trim());
        if (errorCaptura) datos.append('error_captura', '1');
        if (cambiaDestino) datos.append('sucursal_destino_id', destino);
        archivos.forEach((archivo) => datos.append('evidencias[]', archivo));
        setProcesando(true);
        try {
            await axios.post(route('control_pedidos.municipal.cambio_a_tienda.solicitar', pedido.id), datos, { headers: { Accept: 'application/json' } });
            onActualizado();
            onClose();
        } catch (err) {
            setError(mensajeDeError(err));
        } finally {
            setProcesando(false);
        }
    };

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="cambio-tienda-titulo"
                className={`${THEME_MODAL_SHELL} max-w-xl w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between p-5 border-b theme-border shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        <Store className="w-5 h-5 theme-text-muted shrink-0" aria-hidden="true" />
                        <div className="min-w-0">
                            <h2 id="cambio-tienda-titulo" className="text-lg font-black uppercase italic theme-text-main m-0">Recolección en sucursal</h2>
                            <p className="text-xs theme-text-muted font-bold mt-1 m-0 truncate">Envío {folio} · {pedido.cliente?.nombre || '—'}</p>
                        </div>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Cerrar" className="p-2 rounded-xl theme-element border theme-border outline-none min-w-11 min-h-11">
                        <X className="w-4 h-4" aria-hidden="true" />
                    </button>
                </div>

                {cambio.estado ? (
                    <div className="p-5 space-y-3 overflow-y-auto flex-1">
                        <p className="text-sm font-black theme-text-main m-0">{cambio.etiqueta}</p>
                        {cambio.motivo && <p className="text-xs theme-text-muted font-bold m-0">Motivo: {cambio.motivo}</p>}
                        {cambio.error_captura && <p className="text-xs theme-text-muted font-bold m-0">Se notificó un error de captura.</p>}
                        {cambio.saldo_envio && (
                            <p className="text-xs theme-text-main font-bold m-0">
                                Saldo de envío {cambio.saldo_envio.folio}: {Number(cambio.saldo_envio.monto || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}, solo para gastos de envío.
                            </p>
                        )}
                        <p className="text-xs theme-text-muted font-bold m-0">
                            El paquete no sale a terminal. La compra, la remisión y los pagos se conservan.
                        </p>
                    </div>
                ) : (
                    <form onSubmit={guardar} className="p-5 space-y-4 overflow-y-auto flex-1">
                        <p className="text-xs theme-text-muted font-bold m-0">
                            Solicitar no mueve el paquete ni cambia pagos o remisión. El área que lo tiene verifica el cambio y el paquete deja de ir a terminal.
                        </p>
                        <div>
                            <label className={SECCION} htmlFor="ct-motivo">Motivo *</label>
                            <textarea id="ct-motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} maxLength={MAX_MOTIVO} rows={3} className={`${THEME_INPUT} w-full`} />
                            <p className="text-[10px] theme-text-muted font-bold m-0 mt-1">{motivo.length}/{MAX_MOTIVO}</p>
                        </div>
                        {sucursales.length > 1 && (
                            <div>
                                <label className={SECCION} htmlFor="ct-destino">Sucursal de recolección</label>
                                <select id="ct-destino" value={destino} onChange={(e) => setDestino(e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                    {sucursales.map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                                </select>
                                {exigeGerencia && (
                                    <p className="text-[11px] text-amber-600 font-bold m-0 mt-2">
                                        El paquete ya se trasladó. Otra sucursal exige autorización de gerencia y un nuevo traslado; la solicitud queda registrada, sin moverlo.
                                    </p>
                                )}
                            </div>
                        )}
                        <fieldset className="border-0 p-0 m-0">
                            <legend className={SECCION}>Evidencia de la petición del cliente {errorCaptura ? '' : '*'}</legend>
                            <label className="flex items-center gap-2 p-3 rounded-xl border theme-border theme-element cursor-pointer min-h-11">
                                <Upload className="w-4 h-4 theme-text-muted shrink-0" aria-hidden="true" />
                                <span className="text-xs font-bold theme-text-main break-all">
                                    {archivos.length ? archivos.map((a) => a.name).join(', ') : 'Adjuntar PDF o imagen'}
                                </span>
                                <input
                                    type="file"
                                    multiple
                                    accept="image/*,.pdf"
                                    className="sr-only"
                                    onChange={(e) => { setArchivos(Array.from(e.target.files || []).slice(0, MAX_ARCHIVOS)); e.target.value = ''; }}
                                />
                            </label>
                            <label className="flex items-center gap-2 mt-3 text-xs font-bold theme-text-main min-h-11">
                                <input type="checkbox" checked={errorCaptura} onChange={(e) => setErrorCaptura(e.target.checked)} />
                                Se notificó un error de captura
                            </label>
                        </fieldset>
                        {error && <p role="alert" className="text-[11px] text-red-500 font-bold m-0">{error}</p>}
                        <div className="flex justify-end gap-3">
                            <button type="button" onClick={onClose} className={BTN_SECONDARY} disabled={procesando}>Cancelar</button>
                            <button type="submit" className={BTN_PRIMARY} disabled={procesando}>{procesando ? 'Enviando...' : 'Solicitar cambio'}</button>
                        </div>
                    </form>
                )}

                {cambio.estado && (
                    <div className="flex justify-end p-5 border-t theme-border shrink-0">
                        <button type="button" onClick={onClose} className={BTN_PRIMARY}>Cerrar</button>
                    </div>
                )}
            </div>
        </div>,
        document.body
    );
}
