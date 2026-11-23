import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { X, Upload, Plus, Trash2, PackagePlus } from 'lucide-react';
import { THEME_SELECT, THEME_INPUT } from '../../../utils/geliaTheme';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import InputMoneda from './InputMoneda';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from './pedidosBmaStyles';

const SECCION = `${THEME_LABEL} mb-2 block`;
const MAX_PIEZAS = 20;
const MAX_ARCHIVOS = 8;

const FORMAS_PAGO = [
    { codigo: 'transferencia', etiqueta: 'Transferencia', banco: true },
    { codigo: 'deposito', etiqueta: 'Depósito', banco: true },
    { codigo: 'efectivo', etiqueta: 'Efectivo', banco: false },
    { codigo: 'tarjeta', etiqueta: 'Tarjeta', banco: false },
];

const dinero = (valor) => Number(valor || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

/** Convierte un error de axios o Inertia en un texto breve para la persona usuaria. */
export function mensajeDeError(error) {
    const datos = error?.response?.data;
    const primero = datos?.errors ? Object.values(datos.errors).flat()[0] : null;
    return primero || datos?.message || 'No se pudo completar la operación. Intente de nuevo.';
}

function Mensaje({ texto }) {
    if (!texto) return null;
    return <p role="alert" className="text-[11px] text-red-500 font-bold mt-2 m-0">{texto}</p>;
}

function SelectorArchivos({ archivos, onChange, etiqueta = 'Adjuntar PDF o imagen', multiple = true }) {
    return (
        <label className="flex items-center gap-2 p-3 rounded-xl border theme-border theme-element cursor-pointer min-h-11">
            <Upload className="w-4 h-4 theme-text-muted shrink-0" aria-hidden="true" />
            <span className="text-xs font-bold theme-text-main break-all">
                {archivos.length ? archivos.map((a) => a.name).join(', ') : etiqueta}
            </span>
            <input
                type="file"
                multiple={multiple}
                accept="image/*,.pdf"
                className="sr-only"
                onChange={(e) => {
                    onChange(Array.from(e.target.files || []).slice(0, multiple ? MAX_ARCHIVOS : 1));
                    e.target.value = '';
                }}
            />
        </label>
    );
}

function FormularioAgregar({ pedido, onCreado, onCancelar }) {
    const [solicitudId, setSolicitudId] = useState(() => uuidSolicitud());
    const [folioPedido, setFolioPedido] = useState('');
    const [importe, setImporte] = useState('');
    const [piezas, setPiezas] = useState([{ descripcion: '', cantidad: 1 }]);
    const [archivos, setArchivos] = useState([]);
    const [error, setError] = useState('');
    const [procesando, setProcesando] = useState(false);

    const cambiarPieza = (indice, campo, valor) => setPiezas((lista) => lista.map((p, i) => (i === indice ? { ...p, [campo]: valor } : p)));

    const guardar = async (e) => {
        e.preventDefault();
        setError('');
        const datos = new FormData();
        datos.append('solicitud_id', solicitudId);
        datos.append('folio_remision', folioPedido.trim());
        datos.append('total_mercancia', String(importe || ''));
        piezas.filter((p) => p.descripcion.trim() !== '').forEach((p, i) => {
            datos.append(`piezas[${i}][descripcion]`, p.descripcion.trim());
            datos.append(`piezas[${i}][cantidad]`, String(Math.max(1, Number(p.cantidad) || 1)));
        });
        archivos.forEach((archivo) => datos.append('evidencias[]', archivo));
        setProcesando(true);
        try {
            await axios.post(route('control_pedidos.municipal.complementos.store', pedido.id), datos, { headers: { Accept: 'application/json' } });
            setSolicitudId(uuidSolicitud());
            onCreado();
        } catch (err) {
            setError(mensajeDeError(err));
        } finally {
            setProcesando(false);
        }
    };

    return (
        <form onSubmit={guardar} className="space-y-4 rounded-xl border theme-border p-4" aria-label="Agregar piezas adicionales">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label className={SECCION} htmlFor="pa-folio">Folio del pedido de las piezas *</label>
                    <input id="pa-folio" value={folioPedido} onChange={(e) => setFolioPedido(e.target.value)} maxLength={60} className={`${THEME_INPUT} w-full py-3`} />
                </div>
                <div>
                    <label className={SECCION} htmlFor="pa-importe">Importe de las piezas *</label>
                    <InputMoneda id="pa-importe" value={importe} onChange={setImporte} className="w-full py-3" />
                </div>
            </div>

            <fieldset className="border-0 p-0 m-0">
                <legend className={SECCION}>Piezas *</legend>
                <div className="space-y-2">
                    {piezas.map((pieza, i) => (
                        <div key={i} className="flex items-start gap-2">
                            <input
                                aria-label={`Descripción de la pieza ${i + 1}`}
                                value={pieza.descripcion}
                                onChange={(e) => cambiarPieza(i, 'descripcion', e.target.value)}
                                maxLength={180}
                                placeholder="Descripción"
                                className={`${THEME_INPUT} flex-1 min-w-0 py-3`}
                            />
                            <input
                                aria-label={`Cantidad de la pieza ${i + 1}`}
                                type="number"
                                min={1}
                                value={pieza.cantidad}
                                onChange={(e) => cambiarPieza(i, 'cantidad', e.target.value)}
                                className={`${THEME_INPUT} w-20 py-3`}
                            />
                            {piezas.length > 1 && (
                                <button type="button" onClick={() => setPiezas((l) => l.filter((_, j) => j !== i))} aria-label={`Quitar la pieza ${i + 1}`} className="p-3 rounded-xl theme-element border theme-border min-w-11 min-h-11">
                                    <Trash2 className="w-4 h-4" aria-hidden="true" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
                {piezas.length < MAX_PIEZAS && (
                    <button type="button" onClick={() => setPiezas((l) => [...l, { descripcion: '', cantidad: 1 }])} className={`${BTN_SECONDARY} mt-2 inline-flex items-center gap-2`}>
                        <Plus className="w-4 h-4" aria-hidden="true" /> Añadir pieza
                    </button>
                )}
            </fieldset>

            <div>
                <span className={SECCION}>Documento del pedido *</span>
                <SelectorArchivos archivos={archivos} onChange={setArchivos} />
                <p className="text-[10px] theme-text-muted font-bold m-0 mt-2">PDF o imagen, hasta {MAX_ARCHIVOS} archivos de 10 MB.</p>
            </div>

            <Mensaje texto={error} />
            <div className="flex justify-end gap-3">
                {onCancelar && <button type="button" onClick={onCancelar} className={BTN_SECONDARY} disabled={procesando}>Cancelar</button>}
                <button type="submit" className={BTN_PRIMARY} disabled={procesando}>{procesando ? 'Guardando...' : 'Guardar piezas'}</button>
            </div>
        </form>
    );
}

function FormularioPago({ complemento, bancos, onListo, onCancelar }) {
    const [forma, setForma] = useState('transferencia');
    const [banco, setBanco] = useState('');
    const [monto, setMonto] = useState(String(complemento.total_mercancia ?? ''));
    const [comprobante, setComprobante] = useState([]);
    const [error, setError] = useState('');
    const [procesando, setProcesando] = useState(false);
    const [clave] = useState(() => uuidSolicitud());
    const requiereBanco = FORMAS_PAGO.find((f) => f.codigo === forma)?.banco;

    const guardar = (e) => {
        e.preventDefault();
        setError('');
        setProcesando(true);
        router.post(route('control_pedidos.pagos.store', complemento.id), {
            monto,
            forma_pago: forma,
            catalogo_banco_id: requiereBanco ? banco : '',
            comprobante: comprobante[0] || null,
            idempotency_key: clave,
        }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onListo(),
            onError: (errores) => setError(Object.values(errores || {}).flat()[0] || 'No se pudo registrar el pago.'),
            onFinish: () => setProcesando(false),
        });
    };

    return (
        <form onSubmit={guardar} className="space-y-3 rounded-xl border theme-border p-3" aria-label={`Registrar pago de ${complemento.folio}`}>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label className={SECCION} htmlFor={`pa-forma-${complemento.id}`}>Forma de pago *</label>
                    <select id={`pa-forma-${complemento.id}`} value={forma} onChange={(e) => setForma(e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                        {FORMAS_PAGO.map((f) => <option key={f.codigo} value={f.codigo}>{f.etiqueta}</option>)}
                    </select>
                </div>
                <div>
                    <label className={SECCION} htmlFor={`pa-monto-${complemento.id}`}>Monto *</label>
                    <InputMoneda id={`pa-monto-${complemento.id}`} value={monto} onChange={setMonto} className="w-full py-3" />
                </div>
            </div>
            {requiereBanco && (
                <div>
                    <label className={SECCION} htmlFor={`pa-banco-${complemento.id}`}>Banco receptor *</label>
                    <select id={`pa-banco-${complemento.id}`} value={banco} onChange={(e) => setBanco(e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                        <option value="">Seleccionar...</option>
                        {bancos.map((b) => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>
                </div>
            )}
            <div>
                <span className={SECCION}>Comprobante *</span>
                <SelectorArchivos archivos={comprobante} onChange={setComprobante} multiple={false} etiqueta="Adjuntar comprobante" />
            </div>
            <Mensaje texto={error} />
            <div className="flex justify-end gap-3">
                <button type="button" onClick={onCancelar} className={BTN_SECONDARY} disabled={procesando}>Cancelar</button>
                <button type="submit" className={BTN_PRIMARY} disabled={procesando}>{procesando ? 'Guardando...' : 'Registrar pago'}</button>
            </div>
        </form>
    );
}

function TarjetaComplemento({ pedido, complemento, bancos, onCambio }) {
    const [pagando, setPagando] = useState(false);
    const [error, setError] = useState('');
    const [procesando, setProcesando] = useState(false);

    const operar = async (ruta) => {
        setError('');
        setProcesando(true);
        try {
            await axios.post(route(ruta, [pedido.id, complemento.id]), {}, { headers: { Accept: 'application/json' } });
            onCambio();
        } catch (err) {
            setError(mensajeDeError(err));
        } finally {
            setProcesando(false);
        }
    };

    return (
        <li className="rounded-xl border theme-border p-4 space-y-3 list-none">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="text-sm font-black theme-text-main m-0">{complemento.folio}</p>
                    <p className="text-[11px] theme-text-muted font-bold m-0">Pedido {complemento.folio_pedido || '—'} · {dinero(complemento.total_mercancia)}</p>
                </div>
                <span className="text-[10px] font-black uppercase tracking-wide px-2 py-1 rounded-lg border theme-border theme-text-main">{complemento.etiqueta}</span>
            </div>
            {complemento.piezas?.length > 0 && (
                <ul className="m-0 p-0 space-y-1">
                    {complemento.piezas.map((p, i) => (
                        <li key={i} className="list-none text-xs theme-text-main font-bold">{p.cantidad} × {p.descripcion}</li>
                    ))}
                </ul>
            )}
            {pagando && (
                <FormularioPago
                    complemento={complemento}
                    bancos={bancos}
                    onCancelar={() => setPagando(false)}
                    onListo={() => { setPagando(false); onCambio(); }}
                />
            )}
            <Mensaje texto={error} />
            {!pagando && (complemento.puede_registrar_pago || complemento.puede_enviar || complemento.puede_integrar) && (
                <div className="flex flex-wrap justify-end gap-3">
                    {complemento.puede_registrar_pago && (
                        <button type="button" className={BTN_SECONDARY} onClick={() => setPagando(true)} disabled={procesando}>
                            {complemento.pago_registrado ? 'Añadir pago' : 'Registrar pago'}
                        </button>
                    )}
                    {complemento.puede_enviar && (
                        <button type="button" className={BTN_PRIMARY} onClick={() => operar('control_pedidos.municipal.complementos.enviar')} disabled={procesando}>
                            {procesando ? 'Enviando...' : 'Enviar a revisión'}
                        </button>
                    )}
                    {complemento.puede_integrar && (
                        <button type="button" className={BTN_PRIMARY} onClick={() => operar('control_pedidos.municipal.complementos.integrar')} disabled={procesando}>
                            {procesando ? 'Confirmando...' : 'Confirmar integración'}
                        </button>
                    )}
                </div>
            )}
        </li>
    );
}

/**
 * Piezas adicionales de un envío municipal con remisión. Ventas las agrega, las paga y las envía a revisión;
 * CEDIS o la sucursal confirman su integración física. Cada acción se valida de nuevo en servidor.
 */
export default function ModalPiezasAdicionales({ abierto, pedido, bancos = [], onClose, onActualizado }) {
    const resumen = pedido?.piezas_adicionales || null;
    const items = resumen?.items || [];
    const [agregando, setAgregando] = useState(false);

    useEffect(() => {
        if (abierto) setAgregando(false);
    }, [abierto, pedido?.id]);

    const mostrarFormulario = useMemo(
        () => Boolean(resumen?.puede_agregar) && (agregando || items.length === 0),
        [resumen?.puede_agregar, agregando, items.length]
    );

    if (!abierto || !pedido) return null;
    const folio = pedido.folio_remision || pedido.folio;

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="piezas-adicionales-titulo"
                className={`${THEME_MODAL_SHELL} max-w-2xl w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between p-5 border-b theme-border shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        <PackagePlus className="w-5 h-5 theme-text-muted shrink-0" aria-hidden="true" />
                        <div className="min-w-0">
                            <h2 id="piezas-adicionales-titulo" className="text-lg font-black uppercase italic theme-text-main m-0">Piezas adicionales</h2>
                            <p className="text-xs theme-text-muted font-bold mt-1 m-0 truncate">Envío {folio} · {pedido.cliente?.nombre || '—'}</p>
                        </div>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Cerrar" className="p-2 rounded-xl theme-element border theme-border outline-none min-w-11 min-h-11">
                        <X className="w-4 h-4" aria-hidden="true" />
                    </button>
                </div>

                <div className="p-5 space-y-5 overflow-y-auto flex-1">
                    {resumen?.motivo && <p className="text-xs theme-text-muted font-bold m-0">{resumen.motivo}</p>}

                    {items.length > 0 && (
                        <section aria-label="Piezas adicionales registradas">
                            <h3 className={SECCION}>Registradas</h3>
                            <ul className="m-0 p-0 space-y-3">
                                {items.map((c) => (
                                    <TarjetaComplemento key={c.id} pedido={pedido} complemento={c} bancos={bancos} onCambio={onActualizado} />
                                ))}
                            </ul>
                        </section>
                    )}

                    {items.length === 0 && !resumen?.puede_agregar && (
                        <p className="text-sm theme-text-muted font-bold m-0">Este envío no tiene piezas adicionales.</p>
                    )}

                    {mostrarFormulario && (
                        <section aria-label="Agregar piezas">
                            <h3 className={SECCION}>Agregar piezas</h3>
                            <p className="text-[11px] theme-text-muted font-bold m-0 mb-3">
                                Se registran con su propio pago y remisión. El envío no sale a terminal hasta integrarlas.
                            </p>
                            <FormularioAgregar
                                pedido={pedido}
                                onCreado={() => { setAgregando(false); onActualizado(); }}
                                onCancelar={items.length ? () => setAgregando(false) : null}
                            />
                        </section>
                    )}
                </div>

                <div className="flex justify-between gap-3 p-5 border-t theme-border shrink-0">
                    {resumen?.puede_agregar && items.length > 0 && !agregando ? (
                        <button type="button" onClick={() => setAgregando(true)} className={`${BTN_SECONDARY} inline-flex items-center gap-2`}>
                            <Plus className="w-4 h-4" aria-hidden="true" /> Agregar más piezas
                        </button>
                    ) : <span />}
                    <button type="button" onClick={onClose} className={BTN_PRIMARY}>Cerrar</button>
                </div>
            </div>
        </div>,
        document.body
    );
}
