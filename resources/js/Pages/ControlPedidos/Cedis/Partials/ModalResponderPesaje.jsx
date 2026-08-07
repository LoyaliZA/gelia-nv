import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, Scale, Plus, Trash2, FileText, ChevronDown, ExternalLink } from 'lucide-react';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    formatearFechaNegocio,
    LABELS_MOTIVO_REPESAJE,
    calcularPesoCobradoGuia,
} from '../../Partials/pedidosBmaStyles';
import { THEME_INPUT, THEME_SELECT } from '../../../../utils/geliaTheme';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';
import ModalAlertaPedido from '../../Partials/ModalAlertaPedido';

const SECCION = `${THEME_LABEL} mb-2 block`;

const envioVacio = () => ({
    catalogo_tipo_caja_id: '',
    largo: '',
    ancho: '',
    alto: '',
    peso_real_kg: '',
    peso_volumetrico_kg: '',
});

export default function ModalResponderPesaje({
    abierto, onClose, pedido, tiposCaja = [],
}) {
    const [envios, setEnvios] = useState([envioVacio()]);
    const [procesando, setProcesando] = useState(false);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });
    const [soporteAbierto, setSoporteAbierto] = useState(false);

    useEffect(() => {
        if (abierto && pedido) {
            setEnvios([envioVacio()]);
            setProcesando(false);
            setAlerta({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });
            // Móvil: PDF colapsado; desktop: visible de entrada
            setSoporteAbierto(typeof window !== 'undefined' && window.matchMedia('(min-width: 768px)').matches);
        }
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const esImagenDoc = (doc) => {
        const mime = String(doc?.mime_type || '');
        const nombre = String(doc?.nombre_original || '').toLowerCase();
        return mime.startsWith('image/') || /\.(jpe?g|png|webp)$/.test(nombre);
    };

    const pdfPedido = (pedido.documentos || []).find((d) => d.tipo === 'pdf_pedido');
    const anexoPiezas = (pedido.documentos || []).find((d) => d.tipo === 'anexo_piezas');

    const renderSoporte = (doc, titulo) => {
        if (!doc?.url) {
            return <p className="text-sm theme-text-muted m-0">Sin archivo adjunto</p>;
        }
        if (esImagenDoc(doc)) {
            return (
                <img
                    src={doc.url}
                    alt={doc.nombre_original || titulo}
                    className="w-full max-h-[min(40vh,420px)] object-contain border theme-border rounded-xl bg-black/5"
                />
            );
        }
        return (
            <iframe
                src={doc.url}
                title={doc.nombre_original || titulo}
                className="w-full border theme-border rounded-xl bg-black/5"
                style={{ height: 'min(40vh, 420px)' }}
            />
        );
    };

    const AccionesDoc = ({ doc }) => {
        if (!doc?.url) return null;
        return (
            <div className="flex flex-wrap gap-2">
                <a
                    href={doc.url}
                    download={doc.nombre_original || undefined}
                    className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-1.5 text-xs min-h-[40px]`}
                >
                    <FileText className="w-3.5 h-3.5" /> Descargar
                </a>
                <a
                    href={doc.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-1.5 text-xs min-h-[40px]`}
                >
                    <ExternalLink className="w-3.5 h-3.5" /> Abrir
                </a>
            </div>
        );
    };

    const actualizarEnvio = (idx, campo, valor) => {
        setEnvios((prev) => prev.map((e, i) => {
            if (i !== idx) return e;
            if (campo !== 'catalogo_tipo_caja_id') {
                return { ...e, [campo]: valor };
            }
            const tipo = tiposCaja.find((c) => String(c.id) === String(valor));
            return {
                ...e,
                catalogo_tipo_caja_id: valor,
                largo: tipo?.largo != null ? String(tipo.largo) : '',
                ancho: tipo?.ancho != null ? String(tipo.ancho) : '',
                alto: tipo?.alto != null ? String(tipo.alto) : '',
                peso_volumetrico_kg: tipo?.peso_volumetrico != null ? String(tipo.peso_volumetrico) : '',
            };
        }));
    };

    const agregarEnvio = () => setEnvios((prev) => [...prev, envioVacio()]);

    const quitarEnvio = (idx) => {
        setEnvios((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== idx)));
    };

    const confirmar = () => {
        const cajas = [];
        for (let i = 0; i < envios.length; i++) {
            const e = envios[i];
            const tipoId = Number(e.catalogo_tipo_caja_id);
            const pesoReal = Number(e.peso_real_kg);
            const pesoVol = Number(e.peso_volumetrico_kg);
            const largo = Number(e.largo);
            const ancho = Number(e.ancho);
            const alto = Number(e.alto);
            const n = i + 1;

            if (!tipoId) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${n}`, mensaje: 'Seleccione el tipo de caja.' });
                return;
            }
            if (e.largo === '' || Number.isNaN(largo) || largo < 0
                || e.ancho === '' || Number.isNaN(ancho) || ancho < 0
                || e.alto === '' || Number.isNaN(alto) || alto < 0) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${n}`, mensaje: 'Indique largo, ancho y alto válidos.' });
                return;
            }
            if (e.peso_real_kg === '' || Number.isNaN(pesoReal) || pesoReal < 0) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${n}`, mensaje: 'Indique el peso real en kg.' });
                return;
            }
            if (e.peso_volumetrico_kg === '' || Number.isNaN(pesoVol) || pesoVol < 0) {
                setAlerta({ abierto: true, tipo: 'error', titulo: `Envío ${n}`, mensaje: 'Indique el peso volumétrico.' });
                return;
            }

            cajas.push({
                catalogo_tipo_caja_id: tipoId,
                largo,
                ancho,
                alto,
                peso_real_kg: pesoReal,
                peso_volumetrico_kg: pesoVol,
            });
        }

        setProcesando(true);
        router.post(route('control_pedidos.cedis.responder_pesaje', pedido.id), {
            cajas,
        }, {
            preserveScroll: true,
            onFinish: () => setProcesando(false),
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: page.props.flash.error });
                    return;
                }
                onClose();
            },
            onError: (errs) => {
                const msg = Object.values(errs || {})[0];
                setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: typeof msg === 'string' ? msg : 'No se pudo guardar el pesaje.' });
            },
        });
    };

    const totalCobrado = envios.reduce((acc, e) => {
        const cobrado = calcularPesoCobradoGuia(e.peso_real_kg, e.peso_volumetrico_kg);
        return acc + (cobrado === '' ? 0 : Number(cobrado));
    }, 0);

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
                <div
                    className={`${THEME_MODAL_SHELL} max-w-3xl w-full flex flex-col`}
                    style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="p-4 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1">Responder pesaje</p>
                            <EncabezadoFolioPedido pedido={pedido} />
                            <p className="text-xs theme-text-muted m-0 mt-1">
                                {pedido.cliente?.nombre || '—'} · {formatearFechaNegocio(pedido.fecha)}
                            </p>
                        </div>
                        <button type="button" onClick={onClose} className="p-2 min-h-[44px] min-w-[44px] rounded-xl theme-element border theme-border outline-none shrink-0 inline-flex items-center justify-center" aria-label="Cerrar">
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="gelia-modal-body p-4 md:p-6 space-y-5">
                        {pedido.motivo_repesaje && (
                            <AvisoOperativoPedido label="Re-pesaje" tono="warning" icon={Scale}>
                                Motivo: {LABELS_MOTIVO_REPESAJE[pedido.motivo_repesaje] || pedido.motivo_repesaje}
                            </AvisoOperativoPedido>
                        )}

                        {/* Envíos primero: prioridad táctil en almacén */}
                        <div>
                            <div className="flex items-center justify-between gap-2 mb-3">
                                <label className={`${SECCION} m-0`}>Envíos</label>
                                <button
                                    type="button"
                                    onClick={agregarEnvio}
                                    className={`${BTN_SECONDARY} text-xs flex items-center gap-1.5 outline-none min-h-[40px]`}
                                >
                                    <Plus className="w-3.5 h-3.5" /> Otro envío
                                </button>
                            </div>
                            <div className="space-y-4">
                                {envios.map((envio, idx) => {
                                    const cobrado = calcularPesoCobradoGuia(envio.peso_real_kg, envio.peso_volumetrico_kg);
                                    return (
                                        <div key={idx} className="p-4 rounded-xl border theme-border theme-element space-y-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-sm font-black theme-text-main m-0">Envío {idx + 1}</p>
                                                <button
                                                    type="button"
                                                    onClick={() => quitarEnvio(idx)}
                                                    disabled={envios.length <= 1}
                                                    className="p-2 min-h-[40px] min-w-[40px] rounded-xl border theme-border outline-none disabled:opacity-40 inline-flex items-center justify-center"
                                                    aria-label={`Quitar envío ${idx + 1}`}
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div className="sm:col-span-2">
                                                    <label className={SECCION}>Tipo de caja</label>
                                                    <select
                                                        value={envio.catalogo_tipo_caja_id}
                                                        onChange={(e) => actualizarEnvio(idx, 'catalogo_tipo_caja_id', e.target.value)}
                                                        className={`${THEME_SELECT} w-full py-3 min-h-[44px]`}
                                                    >
                                                        <option value="">Seleccionar…</option>
                                                        {tiposCaja.map((c) => (
                                                            <option key={c.id} value={c.id}>{c.nombre}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className={SECCION}>Largo (cm)</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        inputMode="decimal"
                                                        value={envio.largo}
                                                        onChange={(e) => actualizarEnvio(idx, 'largo', e.target.value)}
                                                        className={`${THEME_INPUT} w-full py-3 min-h-[44px]`}
                                                    />
                                                </div>
                                                <div>
                                                    <label className={SECCION}>Ancho (cm)</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        inputMode="decimal"
                                                        value={envio.ancho}
                                                        onChange={(e) => actualizarEnvio(idx, 'ancho', e.target.value)}
                                                        className={`${THEME_INPUT} w-full py-3 min-h-[44px]`}
                                                    />
                                                </div>
                                                <div>
                                                    <label className={SECCION}>Alto (cm)</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        inputMode="decimal"
                                                        value={envio.alto}
                                                        onChange={(e) => actualizarEnvio(idx, 'alto', e.target.value)}
                                                        className={`${THEME_INPUT} w-full py-3 min-h-[44px]`}
                                                    />
                                                </div>
                                                <div>
                                                    <label className={SECCION}>Peso real (kg)</label>
                                                    <input
                                                        type="number"
                                                        step="0.0001"
                                                        min="0"
                                                        inputMode="decimal"
                                                        value={envio.peso_real_kg}
                                                        onChange={(e) => actualizarEnvio(idx, 'peso_real_kg', e.target.value)}
                                                        className={`${THEME_INPUT} w-full py-3 min-h-[44px]`}
                                                        placeholder="0.0000"
                                                    />
                                                </div>
                                                <div>
                                                    <label className={SECCION}>Peso volumétrico (kg)</label>
                                                    <input
                                                        type="number"
                                                        step="0.0001"
                                                        min="0"
                                                        inputMode="decimal"
                                                        value={envio.peso_volumetrico_kg}
                                                        onChange={(e) => actualizarEnvio(idx, 'peso_volumetrico_kg', e.target.value)}
                                                        className={`${THEME_INPUT} w-full py-3 min-h-[44px]`}
                                                    />
                                                </div>
                                                <div>
                                                    <label className={SECCION}>Peso cobrado (kg)</label>
                                                    <input
                                                        type="text"
                                                        readOnly
                                                        value={cobrado !== '' ? cobrado : '—'}
                                                        className={`${THEME_INPUT} w-full py-3 min-h-[44px] opacity-60`}
                                                        title="Mayor entre peso real y volumétrico"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                            {envios.length > 1 && (
                                <p className="text-xs theme-text-muted font-bold m-0 mt-3">
                                    Total peso cobrado: {Math.round(totalCobrado * 10000) / 10000} kg · {envios.length} envíos
                                </p>
                            )}
                        </div>

                        {/* PDF/foto colapsable (cerrado por defecto en móvil) */}
                        <div className="rounded-xl border theme-border overflow-hidden">
                            <button
                                type="button"
                                onClick={() => setSoporteAbierto((v) => !v)}
                                aria-expanded={soporteAbierto}
                                className="w-full flex items-center justify-between gap-2 px-4 py-3 min-h-[44px] theme-element outline-none"
                            >
                                <span className="text-xs font-black uppercase tracking-wide theme-text-main">
                                    Ver PDF / foto del pedido
                                </span>
                                <ChevronDown
                                    className={`w-4 h-4 theme-text-muted shrink-0 transition-transform ${soporteAbierto ? 'rotate-180' : ''}`}
                                    aria-hidden
                                />
                            </button>
                            {soporteAbierto && (
                                <div className="p-4 space-y-4 border-t theme-border">
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between gap-2 flex-wrap">
                                            <label className={`${SECCION} m-0`}>PDF o foto del pedido</label>
                                            <AccionesDoc doc={pdfPedido} />
                                        </div>
                                        {renderSoporte(pdfPedido, 'Soporte del pedido')}
                                    </div>
                                    {anexoPiezas?.url && (
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between gap-2 flex-wrap">
                                                <label className={`${SECCION} m-0`}>Piezas adicionales</label>
                                                <AccionesDoc doc={anexoPiezas} />
                                            </div>
                                            {renderSoporte(anexoPiezas, 'Anexo de piezas')}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="gelia-modal-footer flex flex-col-reverse sm:flex-row flex-wrap gap-3 sm:justify-end p-4 md:p-6 border-t theme-border shrink-0">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} outline-none min-h-[44px] w-full sm:w-auto`} disabled={procesando}>Cancelar</button>
                        <button type="button" onClick={confirmar} disabled={procesando} className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none min-h-[44px] w-full sm:w-auto`}>
                            <Scale className="w-4 h-4" /> {procesando ? 'Guardando…' : 'Registrar pesaje'}
                        </button>
                    </div>
                </div>
            </div>
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta((a) => ({ ...a, abierto: false }))}
            />
        </>,
        document.body
    );
}
