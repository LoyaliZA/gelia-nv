import useSolicitudErrores from '@/Components/Solicitudes/useSolicitudErrores';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';
import React, { useEffect, useState, useRef } from 'react';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { Sparkles, CreditCard, FileSignature, TrendingUp, Send, AlertTriangle, Users, MessageSquare, CheckCircle2, Circle, FileText, Calendar, Landmark, Hash, Store, Tag, FileSpreadsheet, Upload, Trash2, Download, ChevronDown, Pencil } from 'lucide-react';
import CabeceraFlujoSolicitud from '@/Components/Solicitudes/CabeceraFlujoSolicitud';
import CampoBusqueda from '@/Components/CampoBusqueda';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import {
    evaluarEscalonamiento,
    buscarListaPorId,
    fmtMontoEscalonamiento,
    listaVisibleEnSolicitudes,
} from '../../../utils/escalonamiento';
import { PANEL_NOTA } from './solicitudesStyles';
import AvisoSolicitud from '@/Components/Solicitudes/AvisoSolicitud';

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

const admiteConversionMenudeo = (procesos, procesoId) => {
    const nombre = (obtenerProceso(procesos, procesoId)?.nombre || '').trim().toUpperCase();
    return ['ASIGNAR TAG', 'CAMBIO DE LISTA', 'ASIGNAR TAG Y CAMBIO DE LISTA'].includes(nombre);
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
        <span className={`text-sm font-bold tabular-nums shrink-0 ${destacado ? 'text-base font-medium text-[var(--color-primario)]' : 'theme-text-main'} ${valorClassName}`}>
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
    const [alertaInactivo, setAlertaInactivo] = useState(false);
    const [analisisFinanciero, setAnalisisFinanciero] = useState(null);
    const temporizadorBusqueda = useRef(null);
    const abortBusquedaCliente = useRef(null);

    const { data, setData, post, processing, reset, transform, errors, isDirty } = useForm({
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
        convertir_menudeo_mayoreo: !!(solicitudAEditar?.convertir_menudeo_mayoreo),
    });

    useSolicitudErrores(errors);
    const procesoFinancieroSeleccionado = esProcesoFinancieroSeleccionado(procesos, data.catalogo_proceso_id);
    const procesoAsignarTagSolo = esProcesoAsignarTagSolo(procesos, data.catalogo_proceso_id);
    const esMenudeo = infoCliente?.es_menudeo === true || infoCliente?.es_menudeo === 1;
    const puedeConvertirMenudeo = esMenudeo && admiteConversionMenudeo(procesos, data.catalogo_proceso_id);
    const listaBronce = resolverListaBronce(listas);
    const idTipoPorNombre = (nombre) => {
        const tipo = tiposCliente.find(t => (t.nombre || '').toUpperCase() === nombre);
        return tipo ? String(tipo.id) : '';
    };
    const listaSeleccionada = buscarListaPorId(listas, data.catalogo_lista_descuento_id);
    const listaMayoristaElegida = !!(puedeConvertirMenudeo
        && listaSeleccionada
        && String(listaSeleccionada.nombre).toUpperCase().startsWith('MAYOREO '));
    // La conversión es una acción explícita: casilla visible, o una lista mayorista elegida.
    const intencionConversion = !!puedeConvertirMenudeo && (!!data.convertir_menudeo_mayoreo || listaMayoristaElegida);
    const procesoCambiaLista = (obtenerProceso(procesos, data.catalogo_proceso_id)?.nombre || '').toUpperCase().includes('LISTA');
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
        if (!admiteConversionMenudeo(procesos, data.catalogo_proceso_id) && data.convertir_menudeo_mayoreo) {
            setData('convertir_menudeo_mayoreo', false);
        }
    }, [data.catalogo_proceso_id, procesoFinancieroSeleccionado, procesoAsignarTagSolo]);

    // Una lista mayorista elegida implica la conversión.
    useEffect(() => {
        if (listaMayoristaElegida && !data.convertir_menudeo_mayoreo) {
            setData('convertir_menudeo_mayoreo', true);
        }
    }, [listaMayoristaElegida]);

    // En un cambio de lista de un cliente Menudeo, convertir es lo esperado: queda marcada, y se puede quitar.
    const [conversionDecidida, setConversionDecidida] = useState(false);
    useEffect(() => {
        if (conversionDecidida || !puedeConvertirMenudeo || !procesoCambiaLista) return;
        if (!data.convertir_menudeo_mayoreo) setData('convertir_menudeo_mayoreo', true);
    }, [puedeConvertirMenudeo, procesoCambiaLista, infoCliente?.id]);

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
        if (!data.compra_en_tienda_solo_tag || data.convertir_menudeo_mayoreo) return;
        setData('catalogo_lista_descuento_id', '');
        setData('monto_cotizado', '');
        setData('confirmo_informacion_escalonamiento', false);
        setData('monto_final_tentativo', '');
        setData('total_proyectado_neto', '');
        setAnalisisFinanciero(null);
        setAlertaLista({ mensaje: 'Compra Realizada: Solicitar tag — sin lista ni cotización' });
    }, [data.compra_en_tienda_solo_tag, data.convertir_menudeo_mayoreo]);

    useEffect(() => {
        if (!data.convertir_menudeo_mayoreo || !listaBronce) return;
        const actual = buscarListaPorId(listas, data.catalogo_lista_descuento_id);
        const mayorista = actual && String(actual.nombre).toUpperCase().startsWith('MAYOREO ');
        if (!mayorista) {
            setData('catalogo_lista_descuento_id', String(listaBronce.id));
        }
    }, [data.convertir_menudeo_mayoreo, listaBronce?.id]);

    useEffect(() => {
        if (cotizacionOpcional || !infoCliente || !data.monto_cotizado || isNaN(data.monto_cotizado)) {
            if (!cotizacionOpcional) {
                setAnalisisFinanciero(null);
                setAlertaLista(null);
                if (!modoEdicion && !(esMenudeo && (data.convertir_menudeo_mayoreo || intencionConversion))) {
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

        if ((!esMenudeo || intencionConversion) && analisis.esAscenso && analisis.listaCalificadaEfectiva) {
            const reqObjetivo = parseFloat(analisis.listaCalificadaEfectiva.monto_requerido);
            const listaSeleccionada = buscarListaPorId(listas, data.catalogo_lista_descuento_id);
            const reqSeleccionada = listaSeleccionada ? parseFloat(listaSeleccionada.monto_requerido) : -1;

            if (reqObjetivo > reqSeleccionada) {
                setData('catalogo_lista_descuento_id', String(analisis.listaCalificadaEfectiva.id));
            }
        }

        if (analisis.casiAlcanzaSiguiente && analisis.listaCasiAlcanzada) {
            setAlertaLista({
                mensaje: `No asciende a ${analisis.listaCasiAlcanzada.nombre}: faltan ${fmtMonto(analisis.faltanteBrutoCasi)} brutos`
            });
        } else if (analisis.esAscenso && analisis.listaCalificadaEfectiva) {
            setAlertaLista({
                mensaje: `¡Total proyectado alcanza nivel ${analisis.listaCalificadaEfectiva.nombre}!`
            });
        } else if (analisis.listaAnticipada && analisis.mantieneListaAnticipada) {
            setAlertaLista({
                mensaje: `Nivel estable: ${analisis.listaAnticipada.nombre}`
            });
        } else {
            setAlertaLista({
                mensaje: listaActualObj?.nombre
                    ? `No alcanza otro escalón: conserva su lista ${listaActualObj.nombre}`
                    : 'No alcanza otro escalón: conserva su lista actual',
            });
        }

        if (!analisis.casiAlcanzaSiguiente) {
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
        setAlertaInactivo(false);
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
        const inactivo = cliente.es_inactivo === true || cliente.es_inactivo === 1;
        setAlertaInactivo(inactivo);
        const idReactivado = idTipoPorNombre('REACTIVADO');
        const idActivo = idTipoPorNombre('ACTIVO');
        if (inactivo && idReactivado) {
            setData('catalogo_tipo_cliente_id', idReactivado);
        } else if (String(cliente.tipo_cliente_nombre || '').toUpperCase() === 'REACTIVADO' && idActivo) {
            setData('catalogo_tipo_cliente_id', idActivo);
        } else {
            setData(
                'catalogo_tipo_cliente_id',
                cliente.catalogo_tipo_cliente_id ? String(cliente.catalogo_tipo_cliente_id) : ''
            );
        }
        setData('catalogo_lista_descuento_id', '');
        setData('confirmo_informacion_escalonamiento', false);
        setData('convertir_menudeo_mayoreo', false);
        setConversionDecidida(false);
    };

    const requiereConfirmacionEscalonamiento = analisisFinanciero
        && analisisFinanciero.casiAlcanzaSiguiente
        && analisisFinanciero.listaCasiAlcanzada;

    const guardarSolicitud = (e) => {
        e.preventDefault();
        if (processing) return;

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

    return (
        <SolicitudDialog className="gelia-tag-lista-overlay" onClose={onClose} busy={processing} title={modoEdicion ? 'Corregir solicitud' : 'Nueva solicitud'} dirty={isDirty}>
            <form onSubmit={guardarSolicitud} className="gelia-modal-shell gelia-tag-form w-full max-w-4xl">
                <CabeceraFlujoSolicitud
                    icono={modoEdicion ? Pencil : FileSignature}
                    tono={modoEdicion ? 'aviso' : 'primario'}
                    titulo={modoEdicion ? 'Corregir solicitud' : 'Nueva solicitud'}
                    subtitulo="Registra los datos del cliente y el cambio que necesitas."
                    closeLabel="Cerrar registro"
                    disabled={processing}
                />
                <div className="gelia-modal-body p-5 sm:p-6">
                {Object.keys(errors).length > 0 && <AvisoSolicitud tono="peligro" titulo="Revisa estos datos antes de enviar" anunciar className="mb-5">{Object.entries(errors).map(([key, error]) => <p key={key} className="m-0">{error}</p>)}</AvisoSolicitud>}
                {alertaHeredado && (
                    <AvisoSolicitud tono="aviso" titulo="Cliente protegido (heredado)" anunciar className="mb-8">
                        Utiliza los procesos exclusivos para heredados.
                    </AvisoSolicitud>
                )}
                {alertaInactivo && (
                    <AvisoSolicitud tono="aviso" titulo="Cliente inactivo — Reactivación" anunciar className="mb-5">
                        Este cliente se encuentra inactivo. GELIA identificó la solicitud como REACTIVADO. Puedes continuar normalmente con la cotización; la reactivación se procesará cuando corresponda según su autorización.
                    </AvisoSolicitud>
                )}

                <fieldset disabled={processing} className="grid grid-cols-1 lg:grid-cols-2 gap-5 sm:gap-6 relative z-10 border-0 p-0 m-0">

                    <div className="lg:col-span-2 space-y-2">
                        <label htmlFor="solicitud-tipo" className="text-[10px] font-medium  theme-text-muted  ml-1">Tipo de solicitud</label>
                        <div className="theme-field-with-icon theme-field-with-icon--has-trailing relative">
                            <FileSignature className="theme-field-icon w-4 h-4" aria-hidden />
                            <select id="solicitud-tipo" name="catalogo_proceso_id"
                                value={data.catalogo_proceso_id}
                                required
                                onChange={e => setData('catalogo_proceso_id', e.target.value)}
                                disabled={modoEdicion}
                                className={`${THEME_SELECT} w-full py-4 text-xs`}
                            >
                                <option value="">Selecciona el tipo de solicitud…</option>
                                {procesos.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                            </select>
                            <ChevronDown className="theme-field-with-icon__trailing" aria-hidden />
                        </div>
                        {errors.catalogo_proceso_id && <AvisoSolicitud tono="peligro" compacto anunciar>{errors.catalogo_proceso_id}</AvisoSolicitud>}
                    </div>

                    <div className="space-y-5">

                        <div className="space-y-2 relative">
                            <label htmlFor="solicitud-cliente" className="text-[10px] font-medium  theme-text-muted  ml-1">Cliente</label>
                            <CampoBusqueda
                                wrapperClassName="relative"
                                id="solicitud-cliente" name="numero_cliente" autoComplete="off"
                                type="text"
                                value={data.numero_cliente}
                                required
                                onChange={e => manejarBusquedaCliente(e.target.value)}
                                onFocus={() => { if (data.numero_cliente) setMostrarDropdown(true); }}
                                onKeyDown={event => { if (event.key === 'Escape') setMostrarDropdown(false); if (event.key === 'ArrowDown') { event.preventDefault(); event.currentTarget.closest('.relative')?.parentElement.querySelector('button')?.focus(); } }}
                                placeholder="Ingresa nombre o folio…"
                                className={`${THEME_INPUT} w-full py-4 text-sm`}
                                disabled={modoEdicion}
                            />
                            {errors.numero_cliente && <AvisoSolicitud tono="peligro" compacto anunciar>{errors.numero_cliente}</AvisoSolicitud>}
                            {!modoEdicion && data.numero_cliente && !infoCliente && (
                                <AvisoSolicitud tono="aviso" compacto>Selecciona un cliente de la lista para continuar.</AvisoSolicitud>
                            )}
                            {infoCliente && (
                                <div className={`mt-4 p-4 theme-element border ${infoCliente.es_heredado ? 'border-purple-500/50 bg-purple-500/5' : 'theme-border'} rounded-2xl shadow-sm animate-fade-in`}>
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <p className="text-[10px] theme-text-muted font-bold   mb-1">Titular Seleccionado:</p>
                                            <p className="text-sm font-medium theme-text-main  truncate">{infoCliente.nombre}</p>
                                        </div>
                                        {infoCliente.es_heredado && (
                                            <span className="text-[9px] font-medium bg-purple-500 text-white px-2 py-1 rounded-md   shadow-sm">HEREDADO</span>
                                        )}
                                    </div>
                                    <div className="flex gap-2 mt-3">
                                        <span className="text-[10px] font-bold bg-[var(--color-primario)] text-white px-3 py-1 rounded-lg  shadow-sm">
                                            Lista Actual: {obtenerListaActual()?.nombre || 'Público General'}
                                        </span>
                                    </div>
                                </div>
                            )}
                            {mostrarDropdown && !modoEdicion && (
                                <div className="absolute top-[100%] mt-2 left-0 right-0 theme-surface border theme-border rounded-2xl shadow-2xl z-50 max-h-60 overflow-y-auto custom-scrollbar p-2">
                                    {buscandoCliente ? (<div className="p-6 text-center text-xs font-bold theme-text-muted animate-pulse ">Consultando directorio…</div>) : listaClientes.length === 0 ? <p role="status" className="p-4 text-sm theme-text-muted">No se encontraron clientes. Prueba con otro nombre o número.</p> : listaClientes.map(c => (<button type="button" key={c.id} onClick={() => seleccionarCliente(c)} className="p-4 rounded-xl hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer w-full text-left flex justify-between items-center group mb-1 border border-transparent"><p className="text-xs font-medium  theme-text-main">{c.numero_cliente} - {c.nombre}</p>{c.es_heredado ? <span className="text-[8px] font-bold bg-purple-500/20 text-purple-500 px-2 py-0.5 rounded ">Heredado</span> : null}</button>))}
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
                                        <span className="text-sm font-medium theme-text-main flex items-center gap-2">
                                            <Store className="w-4 h-4 text-[#cd7f32]" />
                                            Compra en Tienda
                                        </span>
                                        <p className="text-[10px] font-bold theme-text-muted mt-1 leading-snug">
                                            Cotización no obligatoria. La lista se asigna automáticamente (Bronce). No requiere confirmar pago al aprobar.
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
                                        <span className="text-sm font-medium theme-text-main flex items-center gap-2">
                                            <Tag className="w-4 h-4 text-sky-500" />
                                            Compra Realizada: Solicitar tag
                                        </span>
                                        <p className="text-[10px] font-bold theme-text-muted mt-1 leading-snug">
                                            Para registrar el TAG de quien atendió la venta cuando la compra ya ocurrió. Sin lista ni cotización. No requiere confirmar pago.
                                        </p>
                                    </div>
                                </label>
                                )}
                                {puedeConvertirMenudeo && (
                                <label className={`${PANEL_NOTA} flex items-start gap-3 cursor-pointer`}>
                                    <input
                                        type="checkbox"
                                        className="mt-1 w-4 h-4"
                                        checked={!!data.convertir_menudeo_mayoreo}
                                        onChange={(e) => { setConversionDecidida(true); setData('convertir_menudeo_mayoreo', e.target.checked); }}
                                    />
                                    <span>
                                        <span className="block m-0 text-sm font-medium theme-text-main">Convertir cliente de Menudeo a Mayoreo</span>
                                        <span className="block m-0 mt-1 text-xs theme-text-muted">
                                            {intencionConversion
                                                ? 'Al autorizarse, el cliente pasa a Mayoreo con la lista que corresponde a su acumulado operativo (Bronce como mínimo). Mientras esté pendiente no cambia nada.'
                                                : 'Sin conversión el cliente sigue en Menudeo y su lista no cambia.'}
                                        </span>
                                    </span>
                                </label>
                                )}
                            </div>
                        )}

                        <div className="space-y-2">
                            <label htmlFor="solicitud-monto" className="theme-label">
                                Cotización autorizada{cotizacionOpcional ? ' (opcional)' : ''}
                            </label>
                            <div className="theme-field-with-icon relative">
                                <CreditCard className="theme-field-icon w-5 h-5" aria-hidden />
                                <input
                                    id="solicitud-monto" name="monto_cotizado" autoComplete="off" inputMode="decimal" min="0"
                                    type="number"
                                    step="0.01"
                                    required={!cotizacionOpcional}
                                    disabled={cotizacionOpcional}
                                    value={data.monto_cotizado}
                                    onChange={e => setData('monto_cotizado', e.target.value)}
                                    placeholder={cotizacionOpcional ? (data.compra_en_tienda_solo_tag ? 'No requerida — Compra Realizada' : 'No requerida — compra en tienda') : ''}
                                    className={`${THEME_INPUT} w-full py-4 text-sm disabled:opacity-60`}
                                />
                            </div>
                            {errors.monto_cotizado && <AvisoSolicitud tono="peligro" compacto anunciar>{errors.monto_cotizado}</AvisoSolicitud>}
                        </div>

                        <div className="space-y-2">
                            <label htmlFor="solicitud-clasificacion" className="text-[10px] font-medium  theme-text-muted  ml-1">Tipo de cliente</label>
                            <div className="theme-field-with-icon theme-field-with-icon--has-trailing relative">
                                <Users className="theme-field-icon w-4 h-4" aria-hidden />
                                <select id="solicitud-clasificacion" name="catalogo_tipo_cliente_id" value={data.catalogo_tipo_cliente_id} onChange={e => setData('catalogo_tipo_cliente_id', e.target.value)} required={procesoFinancieroSeleccionado} className={`${THEME_SELECT} w-full py-4 text-xs`}>
                                    <option value="">Seleccionar tipo</option>
                                    {opcionesTipoCliente.map(t => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                </select>
                                <ChevronDown className="theme-field-with-icon__trailing" aria-hidden />
                            </div>
                        </div>
                    </div>

                    <div className="space-y-5 flex flex-col">
                        <div className="space-y-2 flex flex-col flex-1">
                            <label htmlFor="solicitud-comentario" className="text-[10px] font-medium  theme-text-muted  ml-1">Comentario para quien responderá</label>
                            <div className="theme-field-with-icon theme-field-with-icon--textarea relative flex-1">
                                <MessageSquare className="theme-field-icon w-5 h-5" aria-hidden />
                                <textarea id="solicitud-comentario" name="observaciones_vendedor" autoComplete="off"
                                    value={data.observaciones_vendedor}
                                    onChange={e => setData('observaciones_vendedor', e.target.value)}
                                    placeholder="Observaciones, contexto de la venta, acuerdos con el cliente…"
                                    rows={4}
                                    className={`${THEME_TEXTAREA} w-full min-h-[120px] py-4 text-sm resize-none`}
                                />
                            </div>
                            {errors.observaciones_vendedor && (
                                <AvisoSolicitud tono="peligro" compacto anunciar>{errors.observaciones_vendedor}</AvisoSolicitud>
                            )}
                        </div>
                    </div>

                    <div className="lg:col-span-2 space-y-3">
                        <>
                        <div className="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-2 mb-1 px-1">
                            <label htmlFor="solicitud-lista" className="text-[10px] font-medium  theme-text-muted ">Lista solicitada</label>
                            {alertaLista && (
                                <span className="text-xs font-medium  px-3 py-1.5 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                    {alertaLista.mensaje}
                                </span>
                            )}
                        </div>

                        {infoCliente?.doble_conteo_posible && (
                            <p className="m-0 mb-2 px-1 text-xs theme-text-muted">
                                Una carga normal reciente pudo incluir cotizaciones ya autorizadas. La lista aplicada no las suma hasta confirmarlo con una carga con corte.
                            </p>
                        )}

                        {analisisFinanciero && (
                            <div className="mb-2 rounded-2xl border-2 theme-border overflow-hidden animate-fade-in shadow-sm">
                                {analisisFinanciero.listaAnticipada && analisisFinanciero.montoCotizado > 0 && (
                                    <div className="px-4 py-3 bg-emerald-500/10 border-b border-emerald-500/20 flex items-center gap-2.5">
                                        <TrendingUp className="w-5 h-5 text-emerald-500 shrink-0" />
                                        <p className="text-sm font-medium text-emerald-700 dark:text-emerald-400 m-0 leading-snug">
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
                                                <p className="text-[11px] font-medium   theme-text-muted mb-3 m-0">Resumen de montos</p>
                                                <div className="space-y-1">
                                                    <FilaMontoEscalonamiento
                                                        etiqueta="Acumulado pagado anterior"
                                                        valor={analisisFinanciero.montoHistorico}
                                                    />
                                                    <FilaMontoEscalonamiento
                                                        etiqueta="Cotización base (nueva compra)"
                                                        valor={analisisFinanciero.montoCotizado}
                                                        destacado
                                                    />
                                                    <FilaMontoEscalonamiento
                                                        etiqueta="Suma para solicitud provisional"
                                                        valor={analisisFinanciero.totalProyectadoBruto}
                                                        valorClassName={
                                                            analisisFinanciero.listaProvisional
                                                                ? 'text-emerald-600 dark:text-emerald-400 font-medium text-base'
                                                                : ''
                                                        }
                                                    />
                                                    {analisisFinanciero.listaAnticipada && parseFloat(analisisFinanciero.listaAnticipada.monto_requerido) > 0 && (
                                                        <FilaMontoEscalonamiento
                                                            etiqueta={`Mínimo de lista (${analisisFinanciero.listaAnticipada.nombre})`}
                                                            valor={analisisFinanciero.listaAnticipada.monto_requerido}
                                                        />
                                                    )}
                                                </div>
                                            </div>

                                            {analisisFinanciero.listaAnticipada && analisisFinanciero.montoCotizado > 0 && (
                                                <div className="pt-4 border-t-2 border-dashed theme-border space-y-3">
                                                    <FilaMontoEscalonamiento
                                                        etiqueta={`Pago neto estimado (${analisisFinanciero.porcentajeDescuento.toFixed(2)}% desc.)`}
                                                        valor={analisisFinanciero.montoFinalTentativo}
                                                        valorClassName="text-base font-medium theme-text-main"
                                                    />
                                                    <FilaMontoEscalonamiento
                                                        etiqueta="Acumulado neto estimado"
                                                        valor={analisisFinanciero.totalProyectadoNeto}
                                                        valorClassName={
                                                            analisisFinanciero.mantieneListaAnticipada
                                                                ? 'text-emerald-600 dark:text-emerald-400 font-medium text-base'
                                                                : 'text-amber-600 dark:text-amber-400 font-medium text-base'
                                                        }
                                                    />
                                                    {analisisFinanciero.listaConfirmacionEstimada
                                                        && analisisFinanciero.listaProvisional
                                                        && analisisFinanciero.listaConfirmacionEstimada.id !== analisisFinanciero.listaProvisional.id && (
                                                        <p className="text-xs font-semibold text-amber-700 dark:text-amber-400 m-0 px-1">
                                                            Lista provisional: {analisisFinanciero.listaProvisional.nombre}.
                                                            Con este pago estimado se confirmaría: {analisisFinanciero.listaConfirmacionEstimada.nombre}.
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {/* Columna derecha: alertas + niveles */}
                                        <div className="space-y-4 md:border-l md:theme-border md:pl-5 lg:pl-8">
                                            {(analisisFinanciero.casiAlcanzaSiguiente && analisisFinanciero.listaCasiAlcanzada)
                                                || (analisisFinanciero.mantieneListaAnticipada
                                                    && analisisFinanciero.listaAnticipada
                                                    && !analisisFinanciero.listaAnticipada.nombre.toUpperCase().includes('PUBLICO'))
                                                || (analisisFinanciero.listaSiguienteEfectiva
                                                    && analisisFinanciero.faltanteBrutoParaSiguiente > 0
                                                    && !analisisFinanciero.casiAlcanzaSiguiente
                                                    && analisisFinanciero.listaSiguienteEfectiva.id !== analisisFinanciero.listaAnticipada?.id) ? (
                                                <div className="space-y-3">
                                                    <p className="text-[11px] font-medium   theme-text-muted m-0">Estado del escalonamiento</p>

                                                    {analisisFinanciero.casiAlcanzaSiguiente && analisisFinanciero.listaCasiAlcanzada && (
                                                        <AvisoSolicitud tono="aviso" titulo="Cerca de la siguiente lista">
                                                                Puedes solicitar <span className="font-medium">{analisisFinanciero.listaCasiAlcanzada.nombre}</span> de forma provisional,
                                                                pero el pago neto estimado quedaría{' '}
                                                                <span className="font-medium tabular-nums">{fmtMonto(analisisFinanciero.faltanteNetoCasi)}</span> por debajo del mínimo neto.
                                                                {analisisFinanciero.faltanteBrutoCasi > 0 && (
                                                                    <> Ajusta la cotización en al menos{' '}
                                                                        <span className="font-medium tabular-nums">{fmtMonto(analisisFinanciero.faltanteBrutoCasi)}</span> brutos.</>
                                                                )}
                                                                Con este pago se estimaría:{' '}
                                                                <span className="font-medium">{analisisFinanciero.listaConfirmacionEstimada?.nombre || analisisFinanciero.listaAnticipada?.nombre || 'lista actual'}</span>.
                                                            
                                                        </AvisoSolicitud>
                                                    )}

                                                    {analisisFinanciero.mantieneListaAnticipada
                                                        && analisisFinanciero.listaAnticipada
                                                        && !analisisFinanciero.listaAnticipada.nombre.toUpperCase().includes('PUBLICO')
                                                        && !analisisFinanciero.casiAlcanzaSiguiente && (
                                                        <AvisoSolicitud tono="exito" compacto>
                                                            El monto alcanza de forma estable la lista {analisisFinanciero.listaAnticipada.nombre}.
                                                        </AvisoSolicitud>
                                                    )}

                                                    {analisisFinanciero.listaSiguienteEfectiva
                                                        && analisisFinanciero.faltanteBrutoParaSiguiente > 0
                                                        && !analisisFinanciero.casiAlcanzaSiguiente
                                                        && analisisFinanciero.listaSiguienteEfectiva.id !== analisisFinanciero.listaAnticipada?.id && (
                                                        <AvisoSolicitud tono="aviso" compacto>
                                                            Faltan <span className="font-medium tabular-nums">{fmtMonto(analisisFinanciero.faltanteBrutoParaSiguiente)}</span> brutos
                                                            (mínimo de lista {fmtMonto(analisisFinanciero.listaSiguienteEfectiva.monto_requerido)}) para{' '}
                                                            <span className="font-medium">{analisisFinanciero.listaSiguienteEfectiva.nombre}</span>.
                                                        </AvisoSolicitud>
                                                    )}
                                                </div>
                                            ) : null}

                                            {analisisFinanciero.desgloseListas?.length > 0 && (
                                                <div className={`${(analisisFinanciero.casiAlcanzaSiguiente || analisisFinanciero.mantieneListaAnticipada) ? 'pt-4 border-t-2 md:border-t-0 md:pt-0 theme-border' : ''}`}>
                                                    <p className="text-[11px] font-medium   theme-text-muted mb-3 m-0">Niveles de lista</p>
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
                                                                <span className={`text-sm font-medium tabular-nums shrink-0 ${item.cubre ? 'text-emerald-700 dark:text-emerald-400' : 'theme-text-muted'}`}>
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
                            <select id="solicitud-lista" name="catalogo_lista_descuento_id"
                                value={data.catalogo_lista_descuento_id || ''}
                                onChange={e => setData('catalogo_lista_descuento_id', e.target.value)}
                                disabled={cotizacionOpcional}
                                className={`${THEME_SELECT} w-full py-4 text-sm disabled:opacity-60`}
                            >
                                <option value="">-- Mantener nivel actual --</option>
                                {listas.filter(listaVisibleEnSolicitudes).map(lista => {
                                    const baseClienteObj = obtenerListaActual();
                                    let estaDeshabilitada = false;
                                    let textoEstado = '';

                                    if (lista.id == baseClienteObj?.id) {
                                        estaDeshabilitada = true;
                                        textoEstado = '(Nivel actual)';
                                    } else if (analisisFinanciero) {
                                        const desglose = analisisFinanciero.desgloseListas?.find(d => d.id === lista.id);
                                        const cubre = desglose
                                            ? desglose.cubre
                                            : analisisFinanciero.totalProyectadoBruto >= parseFloat(lista.monto_requerido);
                                        if (!cubre) {
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
                                    Confirmo que informé al cliente que no asciende a {analisisFinanciero.listaCasiAlcanzada?.nombre}
                                    {analisisFinanciero.faltanteBrutoCasi > 0 && (
                                        <> (faltan {fmtMonto(analisisFinanciero.faltanteBrutoCasi)} brutos)</>
                                    )}
                                    {' '}y que permanece en {analisisFinanciero.listaAnticipada?.nombre || 'su lista actual'}.
                                </span>
                            </label>
                        )}
                        {errors.confirmo_informacion_escalonamiento && (
                            <AvisoSolicitud tono="peligro" compacto anunciar className="mt-1">{errors.confirmo_informacion_escalonamiento}</AvisoSolicitud>
                        )}
                        </>
                    </div>

                </fieldset>
                </div>
                <footer className="gelia-modal-footer gelia-workflow-actions">
                    <button type="button" data-dialog-close disabled={processing} className="theme-btn-secondary">Cancelar</button>
                    <button type="submit" disabled={processing || (requiereConfirmacionEscalonamiento && !data.confirmo_informacion_escalonamiento)} className="theme-btn-primary">
                        <Send className="w-4 h-4" aria-hidden="true" />{processing ? 'Enviando…' : modoEdicion ? 'Reenviar corrección' : 'Enviar solicitud'}
                    </button>
                </footer>
            </form>
        </SolicitudDialog>
    );
}
