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

const SECCION = `${THEME_LABEL} mb-2 block`;

export default function ModalAtenderSinExistencia({
    abierto, pedido, revision, onClose, puedeCancelar = false,
}) {
    const { auth } = usePage().props;
    const cancelarOk = puedeCancelar || (auth?.user?.permissions || []).includes('control_pedidos.cancelar')
        || (auth?.user?.roles || []).includes('Super Admin');
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
        setAccion('esperar');
        setNota('');
        setTotalMercancia(String(pedido.total_mercancia ?? ''));
        setCantidadPiezas(String(pedido.cantidad_piezas ?? ''));
        setCostoEnvio(pedido.costo_envio == null ? '' : String(pedido.costo_envio));
        setAplicaSeguro(Boolean(pedido.aplica_seguro));
        setSolicitarRepesaje(false);
        setProcesando(false);
    }, [abierto, pedido?.id, revision?.id]);

    if (!abierto || !pedido || !revision) return null;

    const requiereTotales = accion === 'retirar' || accion === 'sustituir';

    const enviar = () => {
        if ((accion === 'contactar' || accion === 'esperar') && !String(nota).trim()) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Nota', mensaje: 'Indique qué se acordó con el cliente.' });
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
            <div className={`${THEME_MODAL_OVERLAY} items-center py-4`} style={{ zIndex: 'calc(var(--gelia-z-modal) + 10)' }}>
                <div className={`${THEME_MODAL_SHELL} max-w-lg w-full p-5 md:p-6 space-y-4`} onClick={(e) => e.stopPropagation()}>
                    <div className="flex justify-between items-start gap-3">
                        <div>
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Sin existencias</p>
                            <p className="text-sm font-black theme-text-main m-0 mt-1">{revision.descripcion_producto}</p>
                        </div>
                        <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted outline-none" aria-label="Cerrar">
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div>
                        <label className={SECCION}>Acción</label>
                        <select value={accion} onChange={(e) => setAccion(e.target.value)} className="w-full py-2.5 min-h-[44px] rounded-xl border theme-border theme-element text-sm font-bold">
                            {Object.entries(LABELS_RESOLUCION_SIN_EXISTENCIA)
                                .filter(([k]) => k !== 'stock_ok')
                                .map(([k, label]) => (
                                    <option key={k} value={k}>{label}</option>
                                ))}
                            {cancelarOk && <option value="cancelar">Cancelar pedido</option>}
                        </select>
                    </div>

                    <div>
                        <label className={SECCION}>Nota{accion === 'contactar' || accion === 'esperar' ? ' *' : ''}</label>
                        <textarea
                            value={nota}
                            onChange={(e) => setNota(e.target.value)}
                            className={`${THEME_TEXTAREA} w-full min-h-[72px]`}
                            placeholder={accion === 'cancelar' ? 'Comentario de cancelación…' : 'Qué decidió el cliente…'}
                        />
                    </div>

                    {requiereTotales && (
                        <div className="space-y-3 p-3 rounded-xl border theme-border">
                            <p className="text-[9px] font-black uppercase theme-text-muted m-0">Recálculo</p>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className={SECCION}>Mercancía</label>
                                    <input type="number" min="0" step="0.01" value={totalMercancia} onChange={(e) => setTotalMercancia(e.target.value)} className={`${THEME_INPUT} w-full py-2`} />
                                </div>
                                <div>
                                    <label className={SECCION}>Piezas</label>
                                    <input type="number" min="0" step="1" value={cantidadPiezas} onChange={(e) => setCantidadPiezas(e.target.value)} className={`${THEME_INPUT} w-full py-2`} />
                                </div>
                                <div>
                                    <label className={SECCION}>Envío</label>
                                    <input type="number" min="0" step="0.01" value={costoEnvio} onChange={(e) => setCostoEnvio(e.target.value)} className={`${THEME_INPUT} w-full py-2`} />
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
                                <p className="text-[10px] font-bold text-sky-600 m-0">Se solicitará re-pesaje. Adjunte PDF o anexo del surtido nuevo antes de confirmar.</p>
                            )}
                        </div>
                    )}

                    <div className="flex flex-col-reverse sm:flex-row gap-3 pt-2">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} min-h-[44px] w-full sm:w-auto`}>Cancelar</button>
                        <button type="button" onClick={enviar} disabled={procesando} className={`${BTN_PRIMARY} min-h-[44px] w-full sm:w-auto sm:ml-auto disabled:opacity-50`}>
                            {procesando ? 'Guardando…' : 'Guardar decisión'}
                        </button>
                    </div>
                </div>
            </div>
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
