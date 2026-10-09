import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    LABELS_RESOLUCION_SIN_EXISTENCIA,
} from './pedidosBmaStyles';
import { THEME_INPUT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import ModalAlertaPedido from './ModalAlertaPedido';
import ModalConfirmarAccion from './ModalConfirmarAccion';
import usePedidoDialog from './usePedidoDialog';

const SECCION = `${THEME_LABEL} mb-2 block`;

export default function ModalAtenderSinExistencia({
    abierto, pedido, revision, onClose, puedeCancelar = false,
}) {
    const { auth } = usePage().props;
    const cancelarOk = puedeCancelar || (auth?.user?.permissions || []).includes('control_pedidos.cancelar')
        || (auth?.user?.roles || []).includes('Super Admin');
    const [confirmarCancelacion, setConfirmarCancelacion] = useState(false);
    const [errorNota, setErrorNota] = useState('');
    const [accion, setAccion] = useState('esperar');
    const [nota, setNota] = useState('');
    const [totalMercancia, setTotalMercancia] = useState('');
    const [cantidadPiezas, setCantidadPiezas] = useState('');
    const [costoEnvio, setCostoEnvio] = useState('');
    const [aplicaSeguro, setAplicaSeguro] = useState(false);
    const [solicitarRepesaje, setSolicitarRepesaje] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });

    useEffect(() => {
        if (!abierto || !pedido || !revision) return;
        setConfirmarCancelacion(false);
        setErrorNota('');
        setAccion('esperar');
        setNota('');
        setTotalMercancia(String(pedido.total_mercancia ?? ''));
        setCantidadPiezas(String(pedido.cantidad_piezas ?? ''));
        setCostoEnvio(pedido.costo_envio == null ? '' : String(pedido.costo_envio));
        setAplicaSeguro(Boolean(pedido.aplica_seguro));
        setSolicitarRepesaje(false);
        setProcesando(false);
    }, [abierto, pedido?.id, revision?.id]);

    const dialog = usePedidoDialog({ abierto: abierto && Boolean(pedido && revision), onClose, bloqueado: procesando });
    if (!abierto || !pedido || !revision) return null;

    const requiereTotales = accion === 'retirar' || accion === 'sustituir';

    const enviar = () => {
        if (procesando) return;
        if ((accion === 'contactar' || accion === 'esperar') && !String(nota).trim()) {
            setErrorNota('Indica qué se acordó con el cliente.');
            document.getElementById('decision-nota')?.focus();
            return;
        }
        const payload = {
            revision_id: revision.id,
            accion,
            nota: nota || null,
            comentario_cancelacion: accion === 'cancelar' ? (nota || null) : null,
        };
        if (requiereTotales) {
            payload.total_mercancia = totalMercancia === '' ? null : Number(totalMercancia);
            payload.cantidad_piezas = cantidadPiezas === '' ? null : Number(cantidadPiezas);
            payload.costo_envio = costoEnvio === '' ? null : Number(costoEnvio);
            payload.aplica_seguro = aplicaSeguro;
            payload.solicitar_repesaje = accion === 'sustituir' ? true : solicitarRepesaje;
        }

        setProcesando(true);
        router.post(route('control_pedidos.atender_sin_existencia', pedido.id), payload, {
            preserveScroll: true,
            onFinish: () => setProcesando(false),
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: page.props.flash.error });
                    return;
                }
                onClose?.();
            },
            onError: (errs) => {
                const msg = Object.values(errs || {})[0];
                setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: typeof msg === 'string' ? msg : 'No se pudo guardar.' });
            },
        });
    };

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-center py-4`} style={{ zIndex: 'calc(var(--gelia-z-toast) + 2)' }}>
                <div {...dialog} aria-labelledby="decision-titulo" className={`${THEME_MODAL_SHELL} gelia-pedidos-dialog-workspace max-w-lg w-full`} onClick={(e) => e.stopPropagation()}>
                    <div className="p-5 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div>
                            <h2 id="decision-titulo" className="text-lg font-semibold theme-text-main m-0">Resolver pieza sin existencias</h2>
                            <p className="text-sm font-medium theme-text-muted m-0 mt-2">{revision.descripcion_producto}</p>
                        </div>
                        <button type="button" onClick={onClose} disabled={procesando} className="p-2 rounded-full theme-text-muted outline-none" aria-label="Cerrar">
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="gelia-modal-body p-5 space-y-4">
                    <div>
                        <label htmlFor="decision-accion" className={SECCION}>Acción</label>
                        <select id="decision-accion" name="accion" disabled={procesando} value={accion} onChange={(e) => setAccion(e.target.value)} className="w-full py-2.5 min-h-[44px] rounded-xl border theme-border theme-element text-sm font-bold">
                            {Object.entries(LABELS_RESOLUCION_SIN_EXISTENCIA)
                                .filter(([k]) => k !== 'stock_ok')
                                .map(([k, label]) => (
                                    <option key={k} value={k}>{label}</option>
                                ))}
                            {cancelarOk && <option value="cancelar">Cancelar pedido</option>}
                        </select>
                    </div>

                    <div>
                        <label htmlFor="decision-nota" className={SECCION}>Nota{accion === 'contactar' || accion === 'esperar' ? ' *' : ''}</label>
                        <textarea id="decision-nota" name="nota" autoComplete="off" aria-invalid={Boolean(errorNota)} aria-describedby={errorNota ? 'decision-nota-error' : undefined}
                            value={nota}
                            onChange={(e) => { setNota(e.target.value); setErrorNota(''); }}
                            className={`${THEME_TEXTAREA} w-full min-h-[72px]`}
                            placeholder={accion === 'cancelar' ? 'Comentario de cancelación…' : 'Qué decidió el cliente…'}
                        />
                        {errorNota && <p id="decision-nota-error" role="alert" className="text-sm theme-text-peligro m-0 mt-2">{errorNota}</p>}
                    </div>

                    {requiereTotales && (
                        <div className="space-y-3 p-3 rounded-xl border theme-border">
                            <p className="text-sm font-semibold theme-text-muted m-0">Recálculo</p>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label htmlFor="decision-mercancia" className={SECCION}>Mercancía</label>
                                    <input id="decision-mercancia" name="decision-mercancia" inputMode="decimal" type="number" min="0" step="0.01" value={totalMercancia} onChange={(e) => setTotalMercancia(e.target.value)} className={`${THEME_INPUT} w-full py-2`} />
                                </div>
                                <div>
                                    <label htmlFor="decision-piezas" className={SECCION}>Piezas</label>
                                    <input id="decision-piezas" name="decision-piezas" inputMode="numeric" type="number" min="0" step="1" value={cantidadPiezas} onChange={(e) => setCantidadPiezas(e.target.value)} className={`${THEME_INPUT} w-full py-2`} />
                                </div>
                                <div>
                                    <label htmlFor="decision-envio" className={SECCION}>Envío</label>
                                    <input id="decision-envio" name="decision-envio" inputMode="decimal" type="number" min="0" step="0.01" value={costoEnvio} onChange={(e) => setCostoEnvio(e.target.value)} className={`${THEME_INPUT} w-full py-2`} />
                                </div>
                                <label className="flex items-center gap-2 text-xs font-bold theme-text-main mt-6">
                                    <input type="checkbox" checked={aplicaSeguro} onChange={(e) => setAplicaSeguro(e.target.checked)} className="w-4 h-4" />
                                    Aplica seguro
                                </label>
                            </div>
                            {accion === 'retirar' && (
                                <label className="flex items-center gap-2 text-xs font-bold theme-text-main">
                                    <input type="checkbox" checked={solicitarRepesaje} onChange={(e) => setSolicitarRepesaje(e.target.checked)} className="w-4 h-4" />
                                    Solicitar re-pesaje (cambio de peso)
                                </label>
                            )}
                            {accion === 'sustituir' && (
                                <p className="text-xs font-medium theme-text-info m-0">Se solicitará re-pesaje. Adjunte PDF o anexo del surtido nuevo antes de confirmar.</p>
                            )}
                        </div>
                    )}

                    </div>
                    <div className="gelia-modal-footer p-4 flex flex-col-reverse sm:flex-row gap-3">
                        <button type="button" onClick={onClose} disabled={procesando} className={`${BTN_SECONDARY} min-h-[44px] w-full sm:w-auto`}>Cancelar</button>
                        <button type="button" onClick={() => accion === 'cancelar' ? setConfirmarCancelacion(true) : enviar()} disabled={procesando} aria-busy={procesando} className={`${BTN_PRIMARY} min-h-[44px] w-full sm:w-auto sm:ml-auto disabled:opacity-50`}>
                            {procesando ? 'Guardando…' : (accion === 'cancelar' ? 'Revisar cancelación' : 'Guardar decisión')}
                        </button>
                    </div>
                </div>
            </div>
            <ModalConfirmarAccion abierto={confirmarCancelacion} titulo="Cancelar pedido" mensaje="Se cancelará el pedido completo. Revisa que esta sea la decisión acordada con el cliente."
                etiquetaConfirmar="Cancelar pedido" variante="danger" onClose={() => setConfirmarCancelacion(false)} onConfirm={() => { setConfirmarCancelacion(false); enviar(); }} />
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta({ ...alerta, abierto: false })}
            />
        </>,
        document.body,
    );
}
