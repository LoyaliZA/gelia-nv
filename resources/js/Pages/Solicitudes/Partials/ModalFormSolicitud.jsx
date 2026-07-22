import React, { useEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { X, Sparkles, Search, CreditCard, FileSignature, TrendingUp, Send, AlertTriangle, Users, MessageSquare, CheckCircle2, Circle, FileText, Calendar, Landmark, Hash, Store, Tag, FileSpreadsheet, Upload, Trash2, Download, ChevronDown } from 'lucide-react';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import {
    evaluarEscalonamiento,
    buscarListaPorId,
    fmtMontoEscalonamiento,
} from '../../../utils/escalonamiento';

const obtenerProceso = (procesos, procesoId) => procesos.find(p => String(p.id) === String(procesoId)) || null;

const esProcesoFinancieroSeleccionado = (procesos, procesoId) => {
    if (!procesoId) return false;
    const proceso = obtenerProceso(procesos, procesoId);
    if (!proceso) return false;
    // Los procesos del módulo de solicitudes son financieros; operativos no llegan aquí.
    return true;
};

/** Solo el proceso exacto ASIGNAR TAG (no "… Y CAMBIO DE LISTA"). */
const esProcesoAsignarTagSolo = (procesos, procesoId) => {
    const nombre = (obtenerProceso(procesos, procesoId)?.nombre || '').trim().toUpperCase();
    return nombre === 'ASIGNAR TAG';
};

const resolverListaBronce = (catalogoListas) => {
    const bronce = catalogoListas.find(l => l.nombre?.toUpperCase() === 'MAYOREO BRONCE')
        || catalogoListas.find(l => l.nombre?.toUpperCase().includes('BRONCE'));
    return bronce || null;
};

const fmtMonto = fmtMontoEscalonamiento;

const FilaMontoEscalonamiento = ({ etiqueta, valor, destacado = false, valorClassName = '' }) => (
    <div className={`flex justify-between items-baseline gap-4 ${destacado ? 'py-2.5 px-3 rounded-xl bg-black/5 dark:bg-white/5' : 'py-1'}`}>
        <span className={`${destacado ? 'text-sm font-bold' : 'text-sm font-medium'} theme-text-muted leading-snug`}>{etiqueta}</span>
        <span className={`text-sm font-bold tabular-nums shrink-0 ${destacado ? 'text-base font-black text-[var(--color-primario)]' : 'theme-text-main'} ${valorClassName}`}>
            {fmtMonto(valor)}
        </span>
    </div>
);

export default function ModalFormSolicitud({ onClose, procesos, listas, tiposCliente = [], bancos = [], modoEdicion, solicitudAEditar }) {

    const infoClienteInicial = solicitudAEditar?.cliente || null;
    const [infoCliente, setInfoCliente] = useState(infoClienteInicial);
    const [listaClientes, setListaClientes] = useState([]);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [alertaLista, setAlertaLista] = useState(null);
    const [alertaHeredado, setAlertaHeredado] = useState(false);
    const [analisisFinanciero, setAnalisisFinanciero] = useState(null);
    const temporizadorBusqueda = useRef(null);
    const abortBusquedaCliente = useRef(null);

    const { data, setData, post, processing, reset, transform, errors } = useForm({
        numero_cliente: solicitudAEditar?.cliente?.numero_cliente || '',
        nombre_cliente: solicitudAEditar?.cliente?.nombre || '',
        monto_cotizado: solicitudAEditar?.monto_cotizado || '',
        catalogo_proceso_id: solicitudAEditar?.catalogo_proceso_id || '',
        catalogo_lista_descuento_id: solicitudAEditar?.catalogo_lista_descuento_id || '',
        catalogo_tipo_cliente_id: solicitudAEditar?.catalogo_tipo_cliente_id || '',
        observaciones_vendedor: solicitudAEditar?.observaciones_vendedor || '',
        confirmo_informacion_escalonamiento: false,
        monto_final_tentativo: '',
        total_proyectado_neto: '',
        compra_en_tienda: !!(solicitudAEditar?.compra_en_tienda),
        compra_en_tienda_solo_tag: !!(solicitudAEditar?.compra_en_tienda_solo_tag),
    });

    const procesoFinancieroSeleccionado = esProcesoFinancieroSeleccionado(procesos, data.catalogo_proceso_id);
    const procesoAsignarTagSolo = esProcesoAsignarTagSolo(procesos, data.catalogo_proceso_id);
    const listaBronce = resolverListaBronce(listas);
    const flujoTienda = procesoFinancieroSeleccionado && (!!data.compra_en_tienda || !!data.compra_en_tienda_solo_tag);
    const cotizacionOpcional = flujoTienda;

    const opcionesTipoCliente = infoCliente?.es_heredado
        ? tiposCliente.filter(t => t.nombre.toUpperCase().includes('HEREDADO'))
        : tiposCliente.filter(t => !t.nombre.toUpperCase().includes('HEREDADO'));

    const obtenerListaActual = () => {
        if (!infoCliente) return null;
        return listas.find(l =>
            l.id == infoCliente.lista_actual_id ||
            l.nombre === infoCliente.lista_actual ||
            l.nombre === infoCliente.lista_descuento?.nombre
        );
    };

    useEffect(() => {
        if (!procesoFinancieroSeleccionado) {
            if (data.compra_en_tienda) setData('compra_en_tienda', false);
        }
        if (!procesoAsignarTagSolo && data.compra_en_tienda_solo_tag) {
            setData('compra_en_tienda_solo_tag', false);
        }
    }, [data.catalogo_proceso_id, procesoFinancieroSeleccionado, procesoAsignarTagSolo]);

    useEffect(() => {
        if (!data.compra_en_tienda || !listaBronce) return;
        setData('catalogo_lista_descuento_id', String(listaBronce.id));
        setData('monto_cotizado', '');
        setData('confirmo_informacion_escalonamiento', false);
        setData('monto_final_tentativo', '');
        setData('total_proyectado_neto', '');
        setAnalisisFinanciero(null);
        setAlertaLista({ mensaje: `Lista ${listaBronce.nombre} asignada (compra en tienda)` });
    }, [data.compra_en_tienda, listaBronce?.id]);

    useEffect(() => {
        if (!data.compra_en_tienda_solo_tag) return;
        setData('catalogo_lista_descuento_id', '');
        setData('monto_cotizado', '');
        setData('confirmo_informacion_escalonamiento', false);
        setData('monto_final_tentativo', '');
        setData('total_proyectado_neto', '');
        setAnalisisFinanciero(null);
        setAlertaLista({ mensaje: 'Compra en tienda: Solo Tag — sin lista ni cotización' });
    }, [data.compra_en_tienda_solo_tag]);

    useEffect(() => {
        if (cotizacionOpcional || !infoCliente || !data.monto_cotizado || isNaN(data.monto_cotizado)) {
            if (!cotizacionOpcional) {
                setAnalisisFinanciero(null);
                setAlertaLista(null);
                if (!modoEdicion) {
                    setData('catalogo_lista_descuento_id', '');
                }
                setData('confirmo_informacion_escalonamiento', false);
                setData('monto_final_tentativo', '');
                setData('total_proyectado_neto', '');
            }
            if (cotizacionOpcional) {
                setAnalisisFinanciero(null);
            }
            return;
        }

        const listaActualObj = obtenerListaActual();
        const analisis = evaluarEscalonamiento(
            infoCliente,
            data.monto_cotizado,
            listas,
            listaActualObj,
            data.catalogo_lista_descuento_id || null
        );

        setAnalisisFinanciero(analisis);
        setData('monto_final_tentativo', analisis.montoFinalTentativo);
        setData('total_proyectado_neto', analisis.totalProyectadoNeto);

        if (analisis.esAscenso && analisis.listaCalificadaBruto) {
            const reqObjetivo = parseFloat(analisis.listaCalificadaBruto.monto_requerido);
            const listaSeleccionada = buscarListaPorId(listas, data.catalogo_lista_descuento_id);
            const reqSeleccionada = listaSeleccionada ? parseFloat(listaSeleccionada.monto_requerido) : -1;

            if (reqObjetivo > reqSeleccionada) {
                setData('catalogo_lista_descuento_id', String(analisis.listaCalificadaBruto.id));
            }
        }

        if (analisis.esAscenso) {
            setAlertaLista({
                mensaje: `¡Total proyectado alcanza nivel ${analisis.listaCalificadaBruto.nombre}!`
            });
        } else if (analisis.listaAnticipada && analisis.mantieneListaAnticipada) {
            setAlertaLista({
                mensaje: `Pago final mantiene nivel ${analisis.listaAnticipada.nombre}`
            });
        } else {
            setAlertaLista(null);
        }

        if (analisis.mantieneListaAnticipada) {
            setData('confirmo_informacion_escalonamiento', false);
        }
    }, [data.monto_cotizado, data.catalogo_lista_descuento_id, data.catalogo_proceso_id, infoCliente, listas]);

    const fetchClientes = async (term = '') => {
        const limpio = term.trim();
        if (limpio.length < 2) {
            setListaClientes([]);
            setMostrarDropdown(false);
            return;
        }
        abortBusquedaCliente.current?.abort();
        const controller = new AbortController();
        abortBusquedaCliente.current = controller;
        setBuscandoCliente(true);
        setMostrarDropdown(true);
        try {
            const response = await axios.get('/api/clientes', {
                params: { q: limpio },
                signal: controller.signal,
            });
            setListaClientes(response.data);
        } catch (err) {
            if (!axios.isCancel(err) && err?.code !== 'ERR_CANCELED') {
                setListaClientes([]);
            }
        } finally {
            if (!controller.signal.aborted) {
                setBuscandoCliente(false);
            }
        }
    };

    const manejarBusquedaCliente = (valor) => {
        setData('numero_cliente', valor);
        setInfoCliente(null);
        setAlertaHeredado(false);
        setAlertaLista(null);
        setAnalisisFinanciero(null);
        if (temporizadorBusqueda.current) clearTimeout(temporizadorBusqueda.current);
        if (valor.trim() === '') {
            abortBusquedaCliente.current?.abort();
            setMostrarDropdown(false);
            setListaClientes([]);
            return;
        }
        temporizadorBusqueda.current = setTimeout(() => { fetchClientes(valor); }, 400);
    };

    const seleccionarCliente = (cliente) => {
        setData('numero_cliente', cliente.numero_cliente);
        setData('nombre_cliente', cliente.nombre);
        setInfoCliente(cliente);
        setMostrarDropdown(false);
        setAlertaHeredado(false);
        setData('catalogo_tipo_cliente_id', '');
        setData('catalogo_lista_descuento_id', '');
        setData('confirmo_informacion_escalonamiento', false);
    };

    const requiereConfirmacionEscalonamiento = analisisFinanciero
        && analisisFinanciero.listaAnticipada
        && analisisFinanciero.brutoCalificaNetoNo;

    const guardarSolicitud = (e) => {
        e.preventDefault();

        if (!modoEdicion && (!data.numero_cliente || !infoCliente)) {
            return;
        }

        if (!cotizacionOpcional && requiereConfirmacionEscalonamiento && !data.confirmo_informacion_escalonamiento) {
            return;
        }

        const config = {
            onSuccess: () => { reset(); onClose(); },
            preserveScroll: true,
        };

        if (modoEdicion) {
            transform((d) => ({ ...d, _method: 'put' }));
            post(route('solicitudes.update', solicitudAEditar.id), config);
        } else {
            post(route('solicitudes.store'), config);
        }
    };

    return createPortal(
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div className="w-full max-w-4xl theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-10 md:p-12 flex flex-col relative modal-pop max-h-[90vh] overflow-y-auto custom-scrollbar" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main theme-element border theme-border rounded-2xl transition-all outline-none hover:scale-110 z-50"><X className="w-5 h-5" /></button>

                <div className="flex items-center gap-3 mb-8">
                    <Sparkles className="w-8 h-8 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                    <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">{modoEdicion ? 'Reparar Solicitud_' : 'Nueva Solicitud_'}</h2>
                </div>

                {alertaHeredado && (
                    <div className="mb-8 p-5 rounded-2xl border-2 border-amber-400 bg-amber-50 dark:bg-amber-500/10 flex gap-4 items-center animate-fade-in">
                        <AlertTriangle className="w-8 h-8 text-amber-500 shrink-0" />
                        <p className="text-sm font-bold text-amber-700 dark:text-amber-400 m-0 leading-tight">Alerta: Cliente protegido (Heredado). Utiliza los procesos exclusivos para heredados.</p>
                    </div>
                )}

                <form onSubmit={guardarSolicitud} className="grid grid-cols-1 lg:grid-cols-2 gap-10 relative z-10">

                    <div className="lg:col-span-2 space-y-2">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Tipo de Solicitud_</label>
                        <div className="theme-field-with-icon theme-field-with-icon--has-trailing relative">
                            <FileSignature className="theme-field-icon w-4 h-4" aria-hidden />
                            <select
                                value={data.catalogo_proceso_id}
                                required
                                onChange={e => setData('catalogo_proceso_id', e.target.value)}
                                disabled={modoEdicion}
                                className={`${THEME_SELECT} w-full py-4 text-xs`}
                            >
                                <option value="">Selecciona el tipo de solicitud...</option>
                                {procesos.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                            </select>
                            <ChevronDown className="theme-field-with-icon__trailing" aria-hidden />
                        </div>
                        {errors.catalogo_proceso_id && <p className="text-xs text-red-500">{errors.catalogo_proceso_id}</p>}
                    </div>

                    <div className="space-y-8">

                        <div className="space-y-2 relative">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Cliente (Buscador)_</label>
                            <div className="theme-field-with-icon relative">
                                <Search className="theme-field-icon w-5 h-5" aria-hidden />
                                <input
                                    type="text"
                                    value={data.numero_cliente}
                                    required
                                    onChange={e => manejarBusquedaCliente(e.target.value)}
                                    onFocus={() => { if (data.numero_cliente) setMostrarDropdown(true); }}
                                    placeholder="Ingresa nombre o folio..."
                                    className={`${THEME_INPUT} w-full py-4 text-sm`}
                                    disabled={modoEdicion}
                                />
                            </div>
                            {errors.numero_cliente && <p className="text-xs text-red-500">{errors.numero_cliente}</p>}
                            {!modoEdicion && data.numero_cliente && !infoCliente && (
                                <p className="text-xs text-amber-600 dark:text-amber-400 font-bold">
                                    Selecciona un cliente de la lista para continuar.
                                </p>
                            )}
                            {infoCliente && (
                                <div className={`mt-4 p-4 theme-element border ${infoCliente.es_heredado ? 'border-purple-500/50 bg-purple-500/5' : 'theme-border'} rounded-2xl shadow-sm animate-fade-in`}>
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mb-1">Titular Seleccionado:</p>
                                            <p className="text-sm font-black theme-text-main italic truncate">{infoCliente.nombre}</p>
                                        </div>
                                        {infoCliente.es_heredado && (
                                            <span className="text-[9px] font-black bg-purple-500 text-white px-2 py-1 rounded-md uppercase tracking-widest shadow-sm">HEREDADO</span>
                                        )}
                                    </div>
                                    <div className="flex gap-2 mt-3">
                                        <span className="text-[10px] font-bold bg-[var(--color-primario)] text-white px-3 py-1 rounded-lg uppercase shadow-sm">
                                            Lista Actual: {obtenerListaActual()?.nombre || 'Público General'}
                                        </span>
                                    </div>
                                </div>
                            )}
                            {mostrarDropdown && !modoEdicion && (
                                <div className="absolute top-[100%] mt-2 left-0 right-0 theme-surface border theme-border rounded-2xl shadow-2xl z-50 max-h-60 overflow-y-auto custom-scrollbar p-2">
                                    {buscandoCliente ? (<div className="p-6 text-center text-xs font-bold theme-text-muted animate-pulse italic">Consultando directorio...</div>) : listaClientes.map(c => (<div key={c.id} onClick={() => seleccionarCliente(c)} className="p-4 rounded-xl hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer flex justify-between items-center group mb-1 border border-transparent"><p className="text-xs font-black uppercase theme-text-main">{c.numero_cliente} - {c.nombre}</p>{c.es_heredado ? <span className="text-[8px] font-bold bg-purple-500/20 text-purple-500 px-2 py-0.5 rounded uppercase">Heredado</span> : null}</div>))}
                                </div>
                            )}
                        </div>

                        {procesoFinancieroSeleccionado && (
                            <div className="space-y-3">
                                <label className="flex items-start gap-3 p-4 rounded-2xl border-2 border-[#cd7f32]/40 bg-[#cd7f32]/10 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={!!data.compra_en_tienda}
                                        onChange={e => {
                                            const on = e.target.checked;
                                            setData('compra_en_tienda', on);
                                            if (on) setData('compra_en_tienda_solo_tag', false);
                                        }}
                                        className="mt-1 w-4 h-4 accent-[#cd7f32]"
                                    />
                                    <div>
                                        <span className="text-sm font-black theme-text-main flex items-center gap-2">
                                            <Store className="w-4 h-4 text-[#cd7f32]" />
                                            Compra en Tienda
                                        </span>
                                        <p className="text-[10px] font-bold theme-text-muted mt-1 leading-snug">
                                            Cotización no obligatoria. La lista se asigna automáticamente (Bronce) y el ascenso se indica al confirmar el pago.
                                        </p>
                                    </div>
                                </label>

                                {procesoAsignarTagSolo && (
                                <label className="flex items-start gap-3 p-4 rounded-2xl border-2 border-sky-500/40 bg-sky-500/10 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={!!data.compra_en_tienda_solo_tag}
                                        onChange={e => {
                                            const on = e.target.checked;
                                            setData('compra_en_tienda_solo_tag', on);
                                            if (on) setData('compra_en_tienda', false);
                                        }}
                                        className="mt-1 w-4 h-4 accent-sky-500"
                                    />
                                    <div>
                                        <span className="text-sm font-black theme-text-main flex items-center gap-2">
                                            <Tag className="w-4 h-4 text-sky-500" />
                                            Compra en tienda: Solo Tag
                                        </span>
                                        <p className="text-[10px] font-bold theme-text-muted mt-1 leading-snug">
                                            Para asegurar el TAG de la vendedora cuando la compra ya ocurrió (p. ej. al día siguiente). Sin lista ni cotización.
                                        </p>
                                    </div>
                                </label>
                                )}
                            </div>
                        )}

                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                Cotización Autorizada_{cotizacionOpcional ? ' (opcional)' : ''}
                            </label>
                            <div className="theme-field-with-icon relative">
                                <CreditCard className="theme-field-icon w-5 h-5" aria-hidden />
                                <input
                                    type="number"
                                    step="0.01"
                                    required={!cotizacionOpcional}
                                    disabled={cotizacionOpcional}
                                    value={data.monto_cotizado}
                                    onChange={e => setData('monto_cotizado', e.target.value)}
                                    placeholder={cotizacionOpcional ? (data.compra_en_tienda_solo_tag ? 'No requerida — Solo Tag' : 'No requerida — compra en tienda') : ''}
                                    className={`${THEME_INPUT} w-full py-4 text-sm disabled:opacity-60`}
                                />
                            </div>
                            {errors.monto_cotizado && <p className="text-xs text-red-500">{errors.monto_cotizado}</p>}
                        </div>

                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Clasificación_</label>
                            <div className="theme-field-with-icon theme-field-with-icon--has-trailing relative">
                                <Users className="theme-field-icon w-4 h-4" aria-hidden />
                                <select value={data.catalogo_tipo_cliente_id} onChange={e => setData('catalogo_tipo_cliente_id', e.target.value)} className={`${THEME_SELECT} w-full py-4 text-xs`}>
                                    <option value="">Asignar Tipo</option>
                                    {opcionesTipoCliente.map(t => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                </select>
                                <ChevronDown className="theme-field-with-icon__trailing" aria-hidden />
                            </div>
                        </div>
                    </div>

                    <div className="space-y-8 flex flex-col">
                        <div className="space-y-2 flex flex-col flex-1">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Comentario de la Vendedora_</label>
                            <div className="theme-field-with-icon theme-field-with-icon--textarea relative flex-1">
                                <MessageSquare className="theme-field-icon w-5 h-5" aria-hidden />
                                <textarea
                                    value={data.observaciones_vendedor}
                                    onChange={e => setData('observaciones_vendedor', e.target.value)}
                                    placeholder="Observaciones, contexto de la venta, acuerdos con el cliente..."
                                    rows={8}
                                    className={`${THEME_TEXTAREA} w-full min-h-[220px] lg:min-h-[280px] py-4 text-sm resize-none`}
                                />
                            </div>
                            {errors.observaciones_vendedor && (
                                <p className="text-xs text-red-500">{errors.observaciones_vendedor}</p>
                            )}
                        </div>
                    </div>

                    <div className="lg:col-span-2 space-y-3">
                        <>
                        <div className="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-2 mb-1 px-1">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Lista Solicitada_</label>
                            {alertaLista && (
                                <span className="text-xs font-black uppercase px-3 py-1.5 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                    {alertaLista.mensaje}
                                </span>
                            )}
                        </div>

                        {analisisFinanciero && (
                            <div className="mb-2 rounded-2xl border-2 theme-border overflow-hidden animate-fade-in shadow-sm">
                                {analisisFinanciero.listaAnticipada && analisisFinanciero.montoCotizado > 0 && (
                                    <div className="px-4 py-3 bg-emerald-500/10 border-b border-emerald-500/20 flex items-center gap-2.5">
                                        <TrendingUp className="w-5 h-5 text-emerald-500 shrink-0" />
                                        <p className="text-sm font-black text-emerald-700 dark:text-emerald-400 m-0 leading-snug">
                                            Nivel anticipado: {analisisFinanciero.listaAnticipada.nombre}
                                            <span className="font-bold opacity-80"> · {analisisFinanciero.porcentajeDescuento.toFixed(2)}% descuento</span>
                                        </p>
                                    </div>
                                )}

                                <div className="p-4 md:p-5 bg-black/[0.02] dark:bg-white/[0.03]">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5 lg:gap-8">
                                        {/* Columna izquierda: montos */}
                                        <div className="space-y-4">
                                            <div>
                                                <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-3 m-0">Resumen de montos</p>
                                                <div className="space-y-1">
                                                    <FilaMontoEscalonamiento
                                                        etiqueta="Historial de compra"
                                                        valor={analisisFinanciero.montoHistorico}
                                                    />
                                                    <FilaMontoEscalonamiento
                                                        etiqueta="Monto bruto (cotización)"
                                                        valor={analisisFinanciero.montoCotizado}
                                                        destacado
                                                    />
                                                    <FilaMontoEscalonamiento
                                                        etiqueta="Total proyectado (bruto)"
                                                        valor={analisisFinanciero.totalProyectadoBruto}
                                                        valorClassName={
                                                            analisisFinanciero.listaAnticipada
                                                            && analisisFinanciero.totalProyectadoBruto >= parseFloat(analisisFinanciero.listaAnticipada.monto_requerido)
                                                                ? 'text-emerald-600 dark:text-emerald-400 font-black text-base'
                                                                : ''
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            {analisisFinanciero.listaAnticipada && analisisFinanciero.montoCotizado > 0 && (
                                                <div className="pt-4 border-t-2 border-dashed theme-border space-y-3">
                                                    <FilaMontoEscalonamiento
                                                        etiqueta={`Monto tentativo final (${analisisFinanciero.porcentajeDescuento.toFixed(2)}% desc.)`}
                                                        valor={analisisFinanciero.montoFinalTentativo}
                                                        valorClassName="text-base font-black theme-text-main"
                                                    />
                                                    <div className={`flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 py-3.5 px-4 rounded-xl border-2 ${
                                                        analisisFinanciero.mantieneListaAnticipada
                                                            ? 'border-emerald-500/40 bg-emerald-500/10'
                                                            : 'border-amber-500/40 bg-amber-500/10'
                                                    }`}>
                                                        <span className="text-sm font-black theme-text-main uppercase tracking-wide leading-snug">
                                                            Total pago final esperado
                                                        </span>
                                                        <span className={`text-2xl font-black tabular-nums ${
                                                            analisisFinanciero.mantieneListaAnticipada
                                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                                : 'text-amber-600 dark:text-amber-400'
                                                        }`}>
                                                            {fmtMonto(analisisFinanciero.totalProyectadoNeto)}
                                                        </span>
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                        {/* Columna derecha: alertas + niveles */}
                                        <div className="space-y-4 md:border-l md:theme-border md:pl-5 lg:pl-8">
                                            {(analisisFinanciero.brutoCalificaNetoNo && analisisFinanciero.listaAnticipada)
                                                || (analisisFinanciero.mantieneListaAnticipada
                                                    && analisisFinanciero.listaAnticipada
                                                    && !analisisFinanciero.listaAnticipada.nombre.toUpperCase().includes('PUBLICO'))
                                                || (analisisFinanciero.listaSiguienteNeto
                                                    && analisisFinanciero.faltanteNetoSiguiente > 0
                                                    && !analisisFinanciero.brutoCalificaNetoNo
                                                    && analisisFinanciero.listaSiguienteNeto.id !== analisisFinanciero.listaAnticipada?.id) ? (
                                                <div className="space-y-3">
                                                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted m-0">Estado del escalonamiento</p>

                                                    {analisisFinanciero.brutoCalificaNetoNo && analisisFinanciero.listaAnticipada && (
                                                        <div className="p-4 rounded-xl bg-amber-500/10 border-2 border-amber-500/30 flex gap-3 items-start">
                                                            <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                                                            <p className="text-sm font-bold text-amber-800 dark:text-amber-300 leading-relaxed m-0">
                                                                Faltan <span className="font-black tabular-nums">{fmtMonto(analisisFinanciero.faltanteNetoMantener)}</span> netos para mantener{' '}
                                                                <span className="font-black">{analisisFinanciero.listaAnticipada.nombre}</span>
                                                                {analisisFinanciero.montoBrutoParaMantener > 0 && (
                                                                    <> (~<span className="font-black tabular-nums">{fmtMonto(analisisFinanciero.montoBrutoParaMantener)}</span> bruto adicional)</>
                                                                )}
                                                                . Informa al cliente antes de continuar.
                                                            </p>
                                                        </div>
                                                    )}

                                                    {analisisFinanciero.mantieneListaAnticipada
                                                        && analisisFinanciero.listaAnticipada
                                                        && !analisisFinanciero.listaAnticipada.nombre.toUpperCase().includes('PUBLICO') && (
                                                        <div className="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex gap-2.5 items-center">
                                                            <CheckCircle2 className="w-5 h-5 text-emerald-500 shrink-0" />
                                                            <p className="text-sm font-bold text-emerald-700 dark:text-emerald-400 m-0">
                                                                El pago final mantiene la lista {analisisFinanciero.listaAnticipada.nombre}.
                                                            </p>
                                                        </div>
                                                    )}

                                                    {analisisFinanciero.listaSiguienteNeto
                                                        && analisisFinanciero.faltanteNetoSiguiente > 0
                                                        && !analisisFinanciero.brutoCalificaNetoNo
                                                        && analisisFinanciero.listaSiguienteNeto.id !== analisisFinanciero.listaAnticipada?.id && (
                                                        <p className="text-sm font-semibold text-amber-700 dark:text-amber-400 leading-relaxed m-0 p-3 rounded-xl bg-amber-500/5 border border-amber-500/20">
                                                            Faltan <span className="font-black tabular-nums">{fmtMonto(analisisFinanciero.faltanteNetoSiguiente)}</span> netos (~
                                                            <span className="font-black tabular-nums">{fmtMonto(analisisFinanciero.montoBrutoParaSiguiente)}</span> bruto al{' '}
                                                            {analisisFinanciero.porcentajeSiguiente.toFixed(2)}% de{' '}
                                                            <span className="font-black">{analisisFinanciero.listaSiguienteNeto.nombre}</span>) para alcanzar el siguiente nivel.
                                                        </p>
                                                    )}
                                                </div>
                                            ) : null}

                                            {analisisFinanciero.desgloseListas?.length > 0 && (
                                                <div className={`${(analisisFinanciero.brutoCalificaNetoNo || analisisFinanciero.mantieneListaAnticipada) ? 'pt-4 border-t-2 md:border-t-0 md:pt-0 theme-border' : ''}`}>
                                                    <p className="text-[11px] font-black uppercase tracking-widest theme-text-muted mb-3 m-0">Niveles de lista</p>
                                                    <div className="space-y-2">
                                                        {analisisFinanciero.desgloseListas.map(item => (
                                                            <div
                                                                key={item.id}
                                                                className={`flex justify-between items-center gap-3 py-2.5 px-3 rounded-xl border ${
                                                                    item.cubre
                                                                        ? 'bg-emerald-500/10 border-emerald-500/25'
                                                                        : 'bg-black/5 dark:bg-white/5 border-transparent'
                                                                }`}
                                                            >
                                                                <span className={`flex items-center gap-2 text-sm font-bold leading-snug ${item.cubre ? 'text-emerald-700 dark:text-emerald-400' : 'theme-text-muted'}`}>
                                                                    {item.cubre
                                                                        ? <CheckCircle2 className="w-4 h-4 shrink-0" />
                                                                        : <Circle className="w-4 h-4 shrink-0 opacity-60" />}
                                                                    {item.nombre}
                                                                </span>
                                                                <span className={`text-sm font-black tabular-nums shrink-0 ${item.cubre ? 'text-emerald-700 dark:text-emerald-400' : 'theme-text-muted'}`}>
                                                                    {fmtMonto(item.monto_requerido)}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="theme-field-with-icon theme-field-with-icon--has-trailing relative">
                            <TrendingUp className="theme-field-icon w-5 h-5" aria-hidden />
                            <select
                                value={data.catalogo_lista_descuento_id || ''}
                                onChange={e => setData('catalogo_lista_descuento_id', e.target.value)}
                                disabled={cotizacionOpcional}
                                className={`${THEME_SELECT} w-full py-4 text-sm disabled:opacity-60`}
                            >
                                <option value="">-- Mantener nivel actual --</option>
                                {listas.filter(l => !l.nombre.toUpperCase().includes('COLABORADOR') && !l.nombre.toUpperCase().includes('PLATAFORMAS')).map(lista => {
                                    const baseClienteObj = obtenerListaActual();
                                    let estaDeshabilitada = false;
                                    let textoEstado = '';

                                    if (lista.id == baseClienteObj?.id) {
                                        estaDeshabilitada = true;
                                        textoEstado = '(Nivel actual)';
                                    } else if (analisisFinanciero) {
                                        const reqLista = parseFloat(lista.monto_requerido);
                                        if (reqLista > analisisFinanciero.totalProyectadoBruto) {
                                            estaDeshabilitada = true;
                                            textoEstado = '(Monto insuficiente)';
                                        }
                                    }

                                    return <option key={lista.id} value={lista.id} disabled={estaDeshabilitada}>{lista.nombre} {estaDeshabilitada ? textoEstado : ''}</option>;
                                })}
                            </select>
                            <ChevronDown className="theme-field-with-icon__trailing" aria-hidden />
                        </div>

                        {requiereConfirmacionEscalonamiento && (
                            <label className="flex items-start gap-3 p-4 rounded-xl border-2 border-amber-500/40 bg-amber-500/10 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={!!data.confirmo_informacion_escalonamiento}
                                    onChange={e => setData('confirmo_informacion_escalonamiento', e.target.checked)}
                                    className="mt-1 shrink-0 w-4 h-4"
                                />
                                <span className="text-sm font-bold text-amber-800 dark:text-amber-300 leading-relaxed">
                                    Confirmo que informé al cliente el monto bruto necesario para mantener/subir de lista considerando el descuento aplicado.
                                </span>
                            </label>
                        )}
                        {errors.confirmo_informacion_escalonamiento && (
                            <p className="text-xs text-red-500 mt-1">{errors.confirmo_informacion_escalonamiento}</p>
                        )}
                        </>
                    </div>

                    <div className="lg:col-span-2">
                        <button type="submit" disabled={processing || (requiereConfirmacionEscalonamiento && !data.confirmo_informacion_escalonamiento)} className={`${THEME_BTN_PRIMARY} w-full py-5 text-[12px]`}>
                            <Send className="w-5 h-5" /> {processing ? 'Procesando...' : (modoEdicion ? 'Reenviar Corrección' : 'Transmitir Solicitud')}
                        </button>
                    </div>
                </form>
            </div>
        </div>, document.body
    );
}
