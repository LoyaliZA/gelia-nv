import React, { useEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { X, Sparkles, Search, CreditCard, FileSignature, TrendingUp, Send, AlertTriangle, Users, MessageSquare, CheckCircle2, Circle } from 'lucide-react';

const obtenerPorcentajeEscalonamiento = (lista) => {
    if (!lista) return 0;

    if (lista.porcentaje_escalonamiento_pct != null && lista.porcentaje_escalonamiento_pct !== '') {
        return parseFloat(lista.porcentaje_escalonamiento_pct) || 0;
    }

    const pct = lista.porcentaje_escalonamiento;
    if (!pct) return 0;
    if (typeof pct === 'object' && pct !== null) {
        if (pct.activo === false || pct.activo === 0) return 0;
        return parseFloat(pct.porcentaje_descuento || 0);
    }
    return parseFloat(pct || 0);
};

const buscarListaPorId = (catalogoListas, id) => {
    if (!id) return null;
    return catalogoListas.find(l => String(l.id) === String(id)) || null;
};

const calcularMontoFinalTentativo = (montoCotizado, porcentaje) => {
    return Math.round(montoCotizado * (1 - porcentaje / 100) * 100) / 100;
};

const fmtMonto = (valor) =>
    `$${Number(valor).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const FilaMontoEscalonamiento = ({ etiqueta, valor, destacado = false, valorClassName = '' }) => (
    <div className={`flex justify-between items-baseline gap-4 ${destacado ? 'py-2.5 px-3 rounded-xl bg-black/5 dark:bg-white/5' : 'py-1'}`}>
        <span className={`${destacado ? 'text-sm font-bold' : 'text-sm font-medium'} theme-text-muted leading-snug`}>{etiqueta}</span>
        <span className={`text-sm font-bold tabular-nums shrink-0 ${destacado ? 'text-base font-black text-[var(--color-primario)]' : 'theme-text-main'} ${valorClassName}`}>
            {fmtMonto(valor)}
        </span>
    </div>
);

const calcularMontoBrutoNecesario = (faltanteNeto, porcentaje) => {
    if (faltanteNeto <= 0) return 0;
    const mult = 1 - porcentaje / 100;
    return mult <= 0 ? faltanteNeto : Math.round((faltanteNeto / mult) * 100) / 100;
};

const evaluarEscalonamiento = (cliente, cotizacion, catalogoListas, listaActualObj, listaSolicitadaId) => {
    const montoHistorico = parseFloat(cliente?.monto_venta_actual?.toString().replace(/[^0-9.-]+/g, '') || 0);
    const montoCotizado = parseFloat(cotizacion || 0);

    const listasValidas = [...catalogoListas]
        .filter(l => !l.nombre.toUpperCase().includes('COLABORADOR') && !l.nombre.toUpperCase().includes('PLATAFORMAS'))
        .sort((a, b) => parseFloat(b.monto_requerido) - parseFloat(a.monto_requerido));

    const totalProyectadoBruto = montoHistorico + montoCotizado;

    const listaCalificadaBruto = listasValidas.find(l => totalProyectadoBruto >= parseFloat(l.monto_requerido)) || null;

    const requisitoListaActual = listaActualObj ? parseFloat(listaActualObj.monto_requerido || 0) : 0;
    const esAscenso = listaCalificadaBruto && parseFloat(listaCalificadaBruto.monto_requerido) > requisitoListaActual;

    // Anticipación: descuento de la lista que alcanza el bruto (aunque el cliente aún no la tenga)
    const listaAnticipada = listaCalificadaBruto;

    const listaSolicitada = listaSolicitadaId
        ? buscarListaPorId(catalogoListas, listaSolicitadaId)
        : (esAscenso && listaCalificadaBruto ? listaCalificadaBruto : null);

    const porcentajeDescuento = obtenerPorcentajeEscalonamiento(listaAnticipada);
    const montoFinalTentativo = calcularMontoFinalTentativo(montoCotizado, porcentajeDescuento);
    const totalProyectadoNeto = montoHistorico + montoFinalTentativo;

    const listaCalificadaNeto = listasValidas.find(l => totalProyectadoNeto >= parseFloat(l.monto_requerido)) || null;

    const listasAscendentes = [...listasValidas].sort((a, b) => parseFloat(a.monto_requerido) - parseFloat(b.monto_requerido));
    const listaSiguienteNeto = listasAscendentes.find(l => parseFloat(l.monto_requerido) > totalProyectadoNeto) || null;
    const faltanteNetoSiguiente = listaSiguienteNeto
        ? Math.max(0, parseFloat(listaSiguienteNeto.monto_requerido) - totalProyectadoNeto)
        : 0;
    const porcentajeSiguiente = listaSiguienteNeto
        ? obtenerPorcentajeEscalonamiento(listaSiguienteNeto)
        : porcentajeDescuento;
    const montoBrutoParaSiguiente = calcularMontoBrutoNecesario(faltanteNetoSiguiente, porcentajeSiguiente);

    let mantieneListaAnticipada = true;
    let faltanteNetoMantener = 0;
    let montoBrutoParaMantener = 0;
    let brutoCalificaNetoNo = false;

    if (listaAnticipada) {
        const umbral = parseFloat(listaAnticipada.monto_requerido);
        mantieneListaAnticipada = totalProyectadoNeto >= umbral;
        faltanteNetoMantener = Math.max(0, umbral - totalProyectadoNeto);
        montoBrutoParaMantener = calcularMontoBrutoNecesario(faltanteNetoMantener, porcentajeDescuento);
        brutoCalificaNetoNo = totalProyectadoBruto >= umbral && !mantieneListaAnticipada;
    }

    const desgloseListas = listasAscendentes.map(l => ({
        id: l.id,
        nombre: l.nombre,
        monto_requerido: parseFloat(l.monto_requerido),
        cubre: totalProyectadoNeto >= parseFloat(l.monto_requerido),
    }));

    return {
        montoHistorico,
        montoCotizado,
        porcentajeDescuento,
        porcentajeSiguiente,
        montoFinalTentativo,
        totalProyectadoBruto,
        totalProyectadoNeto,
        listaCalificadaBruto,
        listaCalificadaNeto,
        listaAnticipada,
        listaSiguienteNeto,
        faltanteNetoSiguiente,
        montoBrutoParaSiguiente,
        mantieneListaAnticipada,
        mantieneListaSolicitada: mantieneListaAnticipada,
        faltanteNetoMantener,
        montoBrutoParaMantener,
        brutoCalificaNetoNo,
        esAscenso,
        desgloseListas,
        listaSolicitada,
    };
};

export default function ModalFormSolicitud({ onClose, procesos, listas, tiposCliente = [], modoEdicion, solicitudAEditar }) {

    const infoClienteInicial = solicitudAEditar?.cliente || null;
    const [infoCliente, setInfoCliente] = useState(infoClienteInicial);
    const [listaClientes, setListaClientes] = useState([]);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [alertaLista, setAlertaLista] = useState(null);
    const [alertaHeredado, setAlertaHeredado] = useState(false);
    const [analisisFinanciero, setAnalisisFinanciero] = useState(null);

    const temporizadorBusqueda = useRef(null);

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
    });

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
        if (infoCliente && data.monto_cotizado && !isNaN(data.monto_cotizado)) {
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

            if (analisis.esAscenso && !data.catalogo_lista_descuento_id && analisis.listaCalificadaBruto) {
                setData('catalogo_lista_descuento_id', analisis.listaCalificadaBruto.id);
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
        } else {
            setAnalisisFinanciero(null);
            setAlertaLista(null);
            if (!modoEdicion) {
                setData('catalogo_lista_descuento_id', '');
            }
            setData('confirmo_informacion_escalonamiento', false);
            setData('monto_final_tentativo', '');
            setData('total_proyectado_neto', '');
        }
    }, [data.monto_cotizado, data.catalogo_lista_descuento_id, infoCliente, listas]);

    const fetchClientes = async (term = '') => {
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
        setAlertaHeredado(false);
        setAlertaLista(null);
        setAnalisisFinanciero(null);
        if (temporizadorBusqueda.current) clearTimeout(temporizadorBusqueda.current);
        if (valor.trim() === '') { setMostrarDropdown(false); setListaClientes([]); return; }
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
        const procesoSeleccionado = procesos.find(p => p.id == data.catalogo_proceso_id);

        if (infoCliente?.es_heredado && (procesoSeleccionado?.nombre === 'ASIGNAR CLIENTE REACTIVADO' || procesoSeleccionado?.nombre === 'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA')) {
            setAlertaHeredado(true);
            return;
        }

        if (requiereConfirmacionEscalonamiento && !data.confirmo_informacion_escalonamiento) {
            return;
        }

        const config = {
            onSuccess: () => { reset(); onClose(); },
            forceFormData: false,
        };

        if (modoEdicion) {
            transform((d) => ({ ...d, _method: 'put' }));
            post(route('solicitudes.update', solicitudAEditar.id), config);
        } else {
            transform((d) => d);
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
                    <div className="space-y-8">

                        <div className="space-y-2 relative">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Cliente (Buscador)_</label>
                            <div className="relative">
                                <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                <input type="text" value={data.numero_cliente} onChange={e => manejarBusquedaCliente(e.target.value)} onFocus={() => { if (data.numero_cliente) setMostrarDropdown(true); }} placeholder="Ingresa nombre o folio..." className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md" disabled={modoEdicion} />
                            </div>
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

                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Cotización Autorizada_</label>
                            <div className="relative">
                                <CreditCard className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                <input type="number" step="0.01" required value={data.monto_cotizado} onChange={e => setData('monto_cotizado', e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-black outline-none focus:ring-2 transition-all shadow-sm" />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Proceso_</label>
                                <div className="relative">
                                    <FileSignature className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                    <select value={data.catalogo_proceso_id} required onChange={e => setData('catalogo_proceso_id', e.target.value)} className="w-full pl-9 pr-4 py-4 theme-surface border theme-border rounded-xl theme-text-main text-xs font-bold outline-none appearance-none focus:ring-2 shadow-sm cursor-pointer">
                                        <option value="">Selecciona...</option>
                                        {procesos.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                                    </select>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Clasificación_</label>
                                <div className="relative">
                                    <Users className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted z-10 pointer-events-none" />
                                    <select value={data.catalogo_tipo_cliente_id} onChange={e => setData('catalogo_tipo_cliente_id', e.target.value)} className="w-full pl-9 pr-4 py-4 theme-surface border theme-border rounded-xl theme-text-main text-xs font-bold outline-none appearance-none focus:ring-2 shadow-sm cursor-pointer">
                                        <option value="">Asignar Tipo</option>
                                        {opcionesTipoCliente.map(t => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-8 flex flex-col">
                        <div className="space-y-2 flex flex-col flex-1">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Comentario de la Vendedora_</label>
                            <div className="relative flex-1">
                                <MessageSquare className="absolute left-4 top-4 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                <textarea
                                    value={data.observaciones_vendedor}
                                    onChange={e => setData('observaciones_vendedor', e.target.value)}
                                    placeholder="Observaciones, contexto de la venta, acuerdos con el cliente..."
                                    rows={8}
                                    className="w-full min-h-[220px] lg:min-h-[280px] px-12 py-4 theme-surface border theme-border rounded-2xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm resize-none"
                                />
                            </div>
                            {errors.observaciones_vendedor && (
                                <p className="text-xs text-red-500">{errors.observaciones_vendedor}</p>
                            )}
                        </div>
                    </div>

                    <div className="lg:col-span-2 space-y-3">
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

                        <div className="relative">
                            <TrendingUp className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                            <select value={data.catalogo_lista_descuento_id || ''} onChange={e => setData('catalogo_lista_descuento_id', e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none appearance-none focus:ring-2 transition-all shadow-sm cursor-pointer">
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
                    </div>

                    <div className="lg:col-span-2">
                        <button type="submit" disabled={processing || (requiereConfirmacionEscalonamiento && !data.confirmo_informacion_escalonamiento)} className="w-full py-5 text-white rounded-2xl font-black uppercase tracking-widest text-[12px] shadow-xl hover:scale-105 transition-all disabled:opacity-50 disabled:hover:scale-100 outline-none flex justify-center items-center gap-3" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Send className="w-5 h-5" /> {processing ? 'Procesando...' : (modoEdicion ? 'Reenviar Corrección' : 'Transmitir Solicitud')}
                        </button>
                    </div>
                </form>
            </div>
        </div>, document.body
    );
}
