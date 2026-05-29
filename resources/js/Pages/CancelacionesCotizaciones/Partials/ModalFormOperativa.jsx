import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { X, Search, MessageSquare, Send, Hash, Calendar, FileText, Landmark } from 'lucide-react';
import { ACCENT, tipoOperativoDeProceso } from './operativasStyles';

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

    const procesoSeleccionado = procesos.find(p => String(p.id) === String(data.catalogo_proceso_id));
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
        if (valor.trim() === '') { setMostrarDropdown(false); setListaClientes([]); return; }
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
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div className="w-full max-w-2xl theme-surface border theme-border shadow-2xl rounded-[2rem] p-8 md:p-10 flex flex-col relative modal-pop max-h-[90vh] overflow-y-auto custom-scrollbar" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl outline-none"><X className="w-5 h-5" /></button>

                <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter mb-2 m-0">{tituloProceso}_</h2>
                <p className="text-xs font-bold theme-text-muted mb-6">Módulo Cancelaciones y Cotizaciones</p>

                {!procesoInicialId && (
                    <div className="mb-6 space-y-2">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Tipo de solicitud_</label>
                        <select
                            required
                            value={data.catalogo_proceso_id}
                            onChange={e => setData('catalogo_proceso_id', e.target.value)}
                            className="w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-xs font-bold outline-none appearance-none"
                        >
                            <option value="">Selecciona...</option>
                            {procesos.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                        </select>
                        {errors.catalogo_proceso_id && <p className="text-xs text-red-500">{errors.catalogo_proceso_id}</p>}
                    </div>
                )}

                <form onSubmit={guardar} className="space-y-6">
                    <div className="space-y-2 relative">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Cliente_</label>
                        <div className="relative">
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted pointer-events-none" />
                            <input
                                type="text"
                                required
                                value={data.numero_cliente}
                                onChange={e => manejarBusquedaCliente(e.target.value)}
                                onFocus={() => { if (data.numero_cliente) setMostrarDropdown(true); }}
                                placeholder="Nombre o número de cliente..."
                                className="w-full px-12 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none"
                            />
                        </div>
                        {infoCliente && (
                            <p className="text-xs font-bold theme-text-main mt-2">{infoCliente.nombre}</p>
                        )}
                        {mostrarDropdown && (
                            <div className="absolute top-full mt-2 left-0 right-0 theme-surface border theme-border rounded-2xl shadow-2xl z-50 max-h-48 overflow-y-auto p-2">
                                {buscandoCliente ? (
                                    <p className="p-4 text-center text-xs theme-text-muted italic">Buscando...</p>
                                ) : listaClientes.map(c => (
                                    <div key={c.id} onClick={() => seleccionarCliente(c)} className="p-3 rounded-xl hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer text-xs font-bold">
                                        {c.numero_cliente} — {c.nombre}
                                    </div>
                                ))}
                            </div>
                        )}
                        {errors.numero_cliente && <p className="text-xs text-red-500">{errors.numero_cliente}</p>}
                    </div>

                    {subtipo === 'remision' && (
                        <div className="space-y-4">
                            <Campo icon={Hash} label="N° Remisión" required value={data.numero_remision} onChange={v => setData('numero_remision', v)} error={errors.numero_remision} />
                            <Campo icon={Calendar} label="Fecha" type="date" required value={data.fecha_operacion} onChange={v => setData('fecha_operacion', v)} error={errors.fecha_operacion} />
                            <CampoTextarea icon={FileText} label="Motivo" required value={data.motivo_operacion} onChange={v => setData('motivo_operacion', v)} error={errors.motivo_operacion} />
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Banco_</label>
                                <div className="relative">
                                    <Landmark className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted pointer-events-none" />
                                    <select required value={data.catalogo_banco_id} onChange={e => setData('catalogo_banco_id', e.target.value)} className="w-full pl-12 pr-4 py-3 theme-surface border theme-border rounded-xl text-sm font-bold outline-none appearance-none">
                                        <option value="">Selecciona banco...</option>
                                        {bancos.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                                    </select>
                                </div>
                                {errors.catalogo_banco_id && <p className="text-xs text-red-500">{errors.catalogo_banco_id}</p>}
                            </div>
                        </div>
                    )}

                    {(subtipo === 'pedido' || subtipo === 'cotizacion_pedido') && (
                        <div className="space-y-4">
                            <Campo icon={Hash} label="N° Pedido" required value={data.numero_pedido} onChange={v => setData('numero_pedido', v)} error={errors.numero_pedido} />
                            {subtipo === 'pedido' && (
                                <>
                                    <CampoTextarea icon={FileText} label="Motivo" required value={data.motivo_operacion} onChange={v => setData('motivo_operacion', v)} error={errors.motivo_operacion} />
                                    <label className="flex items-start gap-3 p-4 rounded-xl border theme-border cursor-pointer">
                                        <input type="checkbox" checked={!!data.solicitar_cotizacion} onChange={e => setData('solicitar_cotizacion', e.target.checked)} className="mt-1 w-4 h-4" />
                                        <span className="text-sm font-bold theme-text-main">Solicitar cotización sobre este pedido</span>
                                    </label>
                                </>
                            )}
                        </div>
                    )}

                    <div className="space-y-2">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Comentario_</label>
                        <div className="relative">
                            <MessageSquare className="absolute left-4 top-4 w-5 h-5 theme-text-muted pointer-events-none" />
                            <textarea
                                value={data.observaciones_vendedor}
                                onChange={e => setData('observaciones_vendedor', e.target.value)}
                                rows={4}
                                placeholder="Observaciones adicionales..."
                                className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none resize-none"
                            />
                        </div>
                    </div>

                    <button type="submit" disabled={processing || !data.catalogo_proceso_id} className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] tracking-widest shadow-lg outline-none disabled:opacity-50 flex items-center justify-center gap-2" style={{ backgroundColor: ACCENT }}>
                        <Send className="w-4 h-4" /> {processing ? 'Enviando...' : 'Enviar solicitud'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}

function Campo({ icon: Icon, label, type = 'text', required, value, onChange, error }) {
    return (
        <div className="space-y-2">
            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">{label}_</label>
            <div className="relative">
                <Icon className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted pointer-events-none" />
                <input type={type} required={required} value={value} onChange={e => onChange(e.target.value)} className="w-full px-12 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none" />
            </div>
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}

function CampoTextarea({ icon: Icon, label, required, value, onChange, error }) {
    return (
        <div className="space-y-2">
            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">{label}_</label>
            <div className="relative">
                <Icon className="absolute left-4 top-4 w-5 h-5 theme-text-muted pointer-events-none" />
                <textarea required={required} value={value} onChange={e => onChange(e.target.value)} rows={3} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none resize-none" />
            </div>
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}
