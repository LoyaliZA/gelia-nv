import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { X, Search, MessageSquare, Send, Hash, Calendar, FileText, Landmark, ChevronDown } from 'lucide-react';
import { tipoOperativoDeProceso } from './operativasStyles';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_INPUT,
    THEME_SELECT,
    THEME_TEXTAREA,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
} from '../../../utils/geliaTheme';

/** Select con icono izquierdo y chevron derecho (hijos directos del contenedor). */
function SelectConIcono({ icon: Icon, required, value, onChange, children, error }) {
    return (
        <>
            <div className="theme-field-with-icon theme-field-with-icon--has-trailing relative mt-0">
                <Icon className="theme-field-icon" aria-hidden />
                <select required={required} value={value} onChange={onChange} className={`${THEME_SELECT} w-full`}>
                    {children}
                </select>
                <ChevronDown className="theme-field-with-icon__trailing" aria-hidden />
            </div>
            {error && <p className="text-xs text-red-500 font-bold m-0">{error}</p>}
        </>
    );
}

function Campo({ icon: Icon, label, type = 'text', required, value, onChange, error, placeholder }) {
    return (
        <div className="space-y-1.5">
            <label className={THEME_LABEL}>{label}</label>
            <div className="theme-field-with-icon relative">
                <Icon className="theme-field-icon" aria-hidden />
                <input
                    type={type}
                    required={required}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    className={`${THEME_INPUT} w-full pr-4`}
                />
            </div>
            {error && <p className="text-xs text-red-500 font-bold m-0">{error}</p>}
        </div>
    );
}

function CampoTextarea({ icon: Icon, label, required, value, onChange, error, placeholder, rows = 3 }) {
    return (
        <div className="space-y-1.5">
            <label className={THEME_LABEL}>{label}</label>
            <div className="theme-field-with-icon theme-field-with-icon--textarea relative">
                <Icon className="theme-field-icon" aria-hidden />
                <textarea
                    required={required}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    rows={rows}
                    placeholder={placeholder}
                    className={`${THEME_TEXTAREA} w-full pr-4`}
                />
            </div>
            {error && <p className="text-xs text-red-500 font-bold m-0">{error}</p>}
        </div>
    );
}

export default function ModalFormOperativa({ onClose, procesos = [], bancos = [], procesoInicialId = '' }) {
    const [infoCliente, setInfoCliente] = useState(null);
    const [listaClientes, setListaClientes] = useState([]);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const temporizadorBusqueda = useRef(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        numero_cliente: '',
        nombre_cliente: '',
        catalogo_proceso_id: procesoInicialId || '',
        observaciones_vendedor: '',
        numero_remision: '',
        numero_pedido: '',
        fecha_operacion: '',
        motivo_operacion: '',
        catalogo_banco_id: '',
        solicitar_cotizacion: false,
    });

    const procesoSeleccionado = procesos.find((p) => String(p.id) === String(data.catalogo_proceso_id));
    const subtipo = tipoOperativoDeProceso(procesoSeleccionado);

    useEffect(() => {
        if (procesoInicialId) {
            setData('catalogo_proceso_id', procesoInicialId);
        }
    }, [procesoInicialId]);

    const fetchClientes = async (term) => {
        if (!term) return;
        setBuscandoCliente(true);
        setMostrarDropdown(true);
        try {
            const response = await axios.get(`/api/clientes?q=${term}`);
            setListaClientes(response.data);
        } catch {
            setListaClientes([]);
        } finally {
            setBuscandoCliente(false);
        }
    };

    const manejarBusquedaCliente = (valor) => {
        setData('numero_cliente', valor);
        setInfoCliente(null);
        if (temporizadorBusqueda.current) clearTimeout(temporizadorBusqueda.current);
        if (valor.trim() === '') {
            setMostrarDropdown(false);
            setListaClientes([]);
            return;
        }
        temporizadorBusqueda.current = setTimeout(() => fetchClientes(valor), 400);
    };

    const seleccionarCliente = (cliente) => {
        setData('numero_cliente', cliente.numero_cliente);
        setData('nombre_cliente', cliente.nombre);
        setInfoCliente(cliente);
        setMostrarDropdown(false);
    };

    const guardar = (e) => {
        e.preventDefault();
        post(route('cancelaciones_cotizaciones.store'), {
            onSuccess: () => { reset(); onClose(); },
            preserveScroll: true,
        });
    };

    const tituloProceso = procesoSeleccionado?.nombre || 'Nueva solicitud operativa';

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-2xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="min-w-0">
                        <h2 className="text-lg md:text-xl font-black italic uppercase theme-text-main m-0 leading-tight">
                            {tituloProceso}
                        </h2>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1.5 m-0">
                            Cancelaciones y cotizaciones
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <form onSubmit={guardar} className="flex flex-col flex-1 min-h-0">
                    <div className="gelia-modal-body p-5 md:p-6 space-y-6">
                        {!procesoInicialId && (
                            <div className="space-y-1.5">
                                <label className={THEME_LABEL}>Tipo de solicitud</label>
                                <div className="relative">
                                    <select
                                        required
                                        value={data.catalogo_proceso_id}
                                        onChange={(e) => setData('catalogo_proceso_id', e.target.value)}
                                        className={`${THEME_SELECT} w-full pr-10`}
                                    >
                                        <option value="">Selecciona…</option>
                                        {procesos.map((p) => (
                                            <option key={p.id} value={p.id}>{p.nombre}</option>
                                        ))}
                                    </select>
                                    <ChevronDown className="theme-field-with-icon__trailing" aria-hidden />
                                </div>
                                {errors.catalogo_proceso_id && <p className="text-xs text-red-500 font-bold m-0">{errors.catalogo_proceso_id}</p>}
                            </div>
                        )}

                        <div className="space-y-1.5 relative">
                            <label className={THEME_LABEL}>Cliente</label>
                            <div className="theme-field-with-icon relative">
                                <Search className="theme-field-icon" aria-hidden />
                                <input
                                    type="text"
                                    required
                                    value={data.numero_cliente}
                                    onChange={(e) => manejarBusquedaCliente(e.target.value)}
                                    onFocus={() => { if (data.numero_cliente) setMostrarDropdown(true); }}
                                    placeholder="Nombre o número de cliente…"
                                    className={`${THEME_INPUT} w-full pr-4`}
                                />
                            </div>
                            {infoCliente && (
                                <p className="text-xs font-bold theme-text-main mt-2 m-0">{infoCliente.nombre}</p>
                            )}
                            {mostrarDropdown && (
                                <div className="absolute top-full mt-2 left-0 right-0 theme-element border theme-border rounded-2xl shadow-2xl z-50 max-h-48 overflow-y-auto p-2">
                                    {buscandoCliente ? (
                                        <p className="p-4 text-center text-xs theme-text-muted italic m-0">Buscando…</p>
                                    ) : (
                                        listaClientes.map((c) => (
                                            <button
                                                key={c.id}
                                                type="button"
                                                onClick={() => seleccionarCliente(c)}
                                                className="w-full text-left p-3 rounded-xl hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer text-xs font-bold theme-text-main border-0 bg-transparent"
                                            >
                                                {c.numero_cliente} — {c.nombre}
                                            </button>
                                        ))
                                    )}
                                </div>
                            )}
                            {errors.numero_cliente && <p className="text-xs text-red-500 font-bold m-0">{errors.numero_cliente}</p>}
                        </div>

                        {subtipo === 'remision' && (
                            <div className="space-y-4">
                                <Campo icon={Hash} label="N° remisión" required value={data.numero_remision} onChange={(v) => setData('numero_remision', v)} error={errors.numero_remision} />
                                <Campo icon={Calendar} label="Fecha" type="date" required value={data.fecha_operacion} onChange={(v) => setData('fecha_operacion', v)} error={errors.fecha_operacion} />
                                <CampoTextarea icon={FileText} label="Motivo" required value={data.motivo_operacion} onChange={(v) => setData('motivo_operacion', v)} error={errors.motivo_operacion} placeholder="Describe el motivo de la cancelación…" />
                                <div className="space-y-1.5">
                                    <label className={THEME_LABEL}>Banco</label>
                                    <SelectConIcono
                                        icon={Landmark}
                                        required
                                        value={data.catalogo_banco_id}
                                        onChange={(e) => setData('catalogo_banco_id', e.target.value)}
                                        error={errors.catalogo_banco_id}
                                    >
                                        <option value="">Selecciona banco…</option>
                                        {bancos.map((b) => (
                                            <option key={b.id} value={b.id}>{b.nombre}</option>
                                        ))}
                                    </SelectConIcono>
                                </div>
                            </div>
                        )}

                        {(subtipo === 'pedido' || subtipo === 'cotizacion_pedido') && (
                            <div className="space-y-4">
                                <Campo icon={Hash} label="N° pedido" required value={data.numero_pedido} onChange={(v) => setData('numero_pedido', v)} error={errors.numero_pedido} />
                                {subtipo === 'pedido' && (
                                    <>
                                        <CampoTextarea icon={FileText} label="Motivo" required value={data.motivo_operacion} onChange={(v) => setData('motivo_operacion', v)} error={errors.motivo_operacion} placeholder="Describe el motivo de la cancelación…" />
                                        <label className="flex items-start gap-3 p-4 rounded-xl border theme-border theme-element cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={!!data.solicitar_cotizacion}
                                                onChange={(e) => setData('solicitar_cotizacion', e.target.checked)}
                                                className="mt-1 w-4 h-4 accent-[var(--color-primario)]"
                                            />
                                            <span className="text-sm font-bold theme-text-main">Solicitar cotización sobre este pedido</span>
                                        </label>
                                    </>
                                )}
                            </div>
                        )}

                        <CampoTextarea
                            icon={MessageSquare}
                            label="Comentario"
                            value={data.observaciones_vendedor}
                            onChange={(v) => setData('observaciones_vendedor', v)}
                            placeholder="Observaciones adicionales…"
                            rows={4}
                        />
                    </div>

                    <div className="gelia-modal-footer p-5 md:p-6 shrink-0">
                        <button type="submit" disabled={processing || !data.catalogo_proceso_id} className={`${THEME_BTN_PRIMARY} w-full`}>
                            <Send className="w-4 h-4 shrink-0" />
                            {processing ? 'Enviando…' : 'Enviar solicitud'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
