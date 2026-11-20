import React, { useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { MapPin, UserRound, Coins, FileText, Save, Send, RefreshCw, PauseCircle } from 'lucide-react';
import SeccionPagosExhibicion from '../Partials/SeccionPagosExhibicion';
import { GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';
import ModalVistaPreviaDocumento from '../Partials/ModalVistaPreviaDocumento';
import { terminalesMunicipales } from './terminalMunicipal';
import { THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';
import ExpedienteConsignatario from './ExpedienteConsignatario';
import SeguimientoSolicitudMunicipal from './SeguimientoSolicitudMunicipal';
import EstadosResguardoMunicipal from './EstadosResguardoMunicipal';
import RemisionesPagadasMunicipal from './RemisionesPagadasMunicipal';

function contextoCierreConsulta(pedido) {
    return {
        modalidad_esperada: pedido?.solicitud_preparacion?.modalidad_entrega || 'municipal',
        version_consulta: pedido?.version_consulta ?? null,
    };
}

export default function FormularioRegistrarMunicipal({
    abierto, pedido, catalogos = {}, onPedidoCreado, antesDeGuardar, asegurarDocumento, onLectura,
    motivoComun = '', ocupado = false, onOcupado, children, modo = 'alta', persistirSolicitud: persistirExterno,
    onConfirmarConversion,
}) {
    const solicitud = pedido?.solicitud_preparacion || {};
    const finanzas = solicitud.finanzas_municipales || {};
    const usuarioId = usePage().props.auth?.user?.id;
    const borradorClave = pedido.id ? `gelia:municipal:${usuarioId}:${pedido.id}` : null;
    const [borrador] = useState(() => { if (!borradorClave) return {}; try { return JSON.parse(sessionStorage.getItem(borradorClave)) || {}; } catch { return {}; } });
    const [paqueteriaId, setPaqueteriaId] = useState(String(borrador.paqueteriaId ?? pedido?.catalogo_paqueteria_id ?? ''));
    const [consignatarioId, setConsignatarioId] = useState(borrador.consignatarioId ?? solicitud.expediente?.consignatario_id ?? '');
    const [leyenda, setLeyenda] = useState(borrador.leyenda ?? finanzas.leyenda_terminal ?? 'PAGADO');
    const [costoModo, setCostoModo] = useState(borrador.costoModo ?? finanzas.costo_transporte ?? 'conocido');
    const [costo, setCosto] = useState(borrador.costo ?? pedido?.costo_envio ?? '');
    const [almacenId, setAlmacenId] = useState(String(borrador.almacenId ?? solicitud.consulta_municipal?.almacen_id ?? solicitud.almacen_revision_id ?? ''));
    const [aviso, setAviso] = useState('');
    const resguardoInicial = solicitud.resguardo_envio || {};
    const [resguardoActivo, setResguardoActivo] = useState(Boolean(resguardoInicial.activo));
    const [resguardoMotivo, setResguardoMotivo] = useState(resguardoInicial.motivo || '');
    const [resguardoFecha, setResguardoFecha] = useState(resguardoInicial.fecha_solicitada || '');
    // Estado de la instrucción de resguardo: guardado | sin_guardar | guardando | error.
    const [resguardoEstado, setResguardoEstado] = useState('guardado');
    const resguardoEnCurso = useRef(null);
    const [confirmaCierre, setConfirmaCierre] = useState(false);
    const [guardando, setGuardando] = useState(false);
    const [etapa, setEtapa] = useState('');
    const [ajustePendiente, setAjustePendiente] = useState(false);
    const operacionRef = useRef(false);
    const [actual, setActual] = useState(pedido);
    const [docPreview, setDocPreview] = useState(null);
    const [cambios, setCambios] = useState(Boolean(Object.keys(borrador).length));
    const conversion = modo === 'conversion';
    const enviada = !conversion && Boolean(actual.solicitud_preparacion?.consulta_municipal?.enviada_at);
    const bloqueado = conversion
        ? Boolean(actual.cancelado_at)
        : enviada || Boolean(actual.consulta_cerrada) || Boolean(actual.cancelado_at);
    useEffect(() => {
        setActual(pedido);
        // Un pedido que llega del servidor no pisa una instrucción que aún no se guarda o que falló.
        if (resguardoEstado === 'sin_guardar' || resguardoEstado === 'error' || resguardoEstado === 'guardando') return;
        const siguiente = pedido?.solicitud_preparacion?.resguardo_envio || pedido?.resguardo_envio || {};
        setResguardoActivo(Boolean(siguiente.activo));
        setResguardoMotivo(siguiente.motivo || '');
        setResguardoFecha(siguiente.fecha_solicitada || '');
    }, [pedido]);
    useEffect(() => { onOcupado?.(guardando || ajustePendiente || resguardoEstado === 'guardando'); }, [guardando, ajustePendiente, resguardoEstado, onOcupado]);
    useEffect(() => {
        if (!borradorClave) return;
        try {
            if (bloqueado || !cambios) sessionStorage.removeItem(borradorClave);
            else sessionStorage.setItem(borradorClave, JSON.stringify({ paqueteriaId, consignatarioId, leyenda, costoModo, costo, almacenId }));
        } catch { /* Un navegador sin almacenamiento conserva la captura mientras el modal siga abierto. */ }
    }, [borradorClave, bloqueado, cambios, paqueteriaId, consignatarioId, leyenda, costoModo, costo, almacenId]);
    const actualizar = (siguiente) => { setActual(siguiente); onPedidoCreado?.(siguiente); };
    const refrescar = async (id = actual.id) => {
        const { data } = await window.axios.get(`/control-pedidos/${id}/disponibilidad-tienda`);
        if (onLectura) onLectura(data);
        else actualizar(data);
    };

    const paqueterias = useMemo(() => terminalesMunicipales(catalogos), [catalogos]);
    const paqueteria = paqueterias.find((item) => String(item.id) === String(paqueteriaId));
    const puedeDiferido = Boolean(paqueteria?.permite_costo_diferido);
    const puedePorCobrar = Boolean(paqueteria?.permite_por_cobrar);

    if (!abierto) return null;

    const camposMunicipales = () => ({
        catalogo_paqueteria_id: Number(paqueteriaId),
        consignatario_id: Number(consignatarioId),
        almacen_id: almacenId ? Number(almacenId) : null,
        leyenda_terminal: puedePorCobrar ? leyenda : 'PAGADO',
        costo_transporte: puedeDiferido && costoModo === 'diferido' ? 'diferido' : 'conocido',
        costo_envio: costo === '' ? null : costo,
    });

    const persistirSolicitud = async () => {
        const comun = antesDeGuardar ? await antesDeGuardar() : actual;
        if (!comun?.id) throw new Error('No se pudieron guardar los datos del pedido. Revise el aviso del formulario antes de continuar.');
        const payload = camposMunicipales();
        if (persistirExterno) {
            const data = await persistirExterno(payload, comun) || {};
            const siguiente = { ...actual, ...comun, ...data };
            setCambios(false);
            actualizar(siguiente);
            return siguiente;
        }
        const { data } = await window.axios.post(route('control_pedidos.municipal.solicitud', comun.id), payload, { headers: { Accept: 'application/json' } });
        const siguiente = { ...actual, ...comun, ...data };
        setCambios(false);
        actualizar(siguiente);
        return siguiente;
    };

    const guardar = async () => {
        if (operacionRef.current || ocupado) return;
        operacionRef.current = true;
        setGuardando(true); setAviso('');
        try {
            const siguiente = await persistirSolicitud();
            await refrescar(siguiente.id);
            setAviso('Solicitud guardada. Puede enviar la consulta.');
        } catch (error) {
            setAviso(Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || error.message || 'No se pudo guardar la solicitud.');
        } finally {
            operacionRef.current = false;
            setGuardando(false);
        }
    };

    const enviar = async () => {
        if (operacionRef.current || ocupado || motivoEnvio) return;
        operacionRef.current = true;
        setGuardando(true);
        setEtapa('Preparando solicitud.');
        setAviso('Preparando solicitud.');
        try {
            if (asegurarDocumento) {
                setEtapa('Guardando documento.');
                setAviso('Guardando documento.');
                const documento = await asegurarDocumento();
                if (!documento?.id) throw new Error('No se pudo adjuntar el PDF o la imagen.');
            }
            setEtapa('Preparando solicitud.');
            setAviso('Preparando solicitud.');
            const guardado = await persistirSolicitud();
            setEtapa('Enviando consulta.');
            setAviso('Enviando consulta.');
            const { data } = await window.axios.post(route('control_pedidos.municipal.consulta', guardado.id), {
                almacen_id: almacenId ? Number(almacenId) : null,
            }, { headers: { Accept: 'application/json' } });
            actualizar({ ...guardado, ...data });
            setEtapa('');
            setAviso('Consulta enviada.');
        } catch (error) {
            setEtapa('');
            setAviso(Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || error.message || 'No se pudo enviar la consulta.');
        } finally {
            operacionRef.current = false;
            setGuardando(false);
        }
    };

    const cerrarConsulta = async (contexto) => {
        setGuardando(true);
        try {
            await window.axios.post(route('control_pedidos.cerrar_consulta', actual.id), {
                ...contextoCierreConsulta(actual),
                ...(contexto || {}),
            }, { headers: { Accept: 'application/json' } });
            await refrescar();
        } catch (error) {
            if (error.response?.status === 409 && error.response?.data?.codigo === 'contexto_obsoleto') {
                await refrescar().catch(() => {});
            }
            setAviso(error.response?.data?.message || 'No se pudo cerrar la consulta.');
        } finally {
            setGuardando(false);
        }
    };

    const capturaResguardo = () => ({
        activo: resguardoActivo,
        motivo: resguardoActivo ? resguardoMotivo || null : null,
        fecha_solicitada: resguardoActivo && resguardoMotivo === 'fecha_posterior' ? resguardoFecha || null : null,
        revision_esperada: actual.revision_registro,
        base_captura: actual.base_captura,
    });

    const marcarResguardoSinGuardar = () => { setResguardoEstado('sin_guardar'); setCambios(true); setAviso(''); };

    // Escritura serializada: nunca hay dos peticiones de resguardo a la vez ni una respuesta vieja sobre una nueva.
    const persistirResguardo = () => {
        if (resguardoEnCurso.current) return resguardoEnCurso.current;
        const captura = capturaResguardo();
        setResguardoEstado('guardando');
        const peticion = (async () => {
            try {
                const { data } = await window.axios.post(route('control_pedidos.municipal.resguardo', actual.id), captura, { headers: { Accept: 'application/json' } });
                const siguiente = { ...actual, ...data };
                actualizar(siguiente);
                setResguardoEstado('guardado');
                return siguiente;
            } catch (error) {
                // Se conservan motivo, fecha y selección locales para reintentar.
                const mensaje = Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || error.message || 'No se pudo guardar la instrucción de resguardo.';
                setAviso(mensaje);
                setResguardoEstado('error');
                throw error;
            } finally {
                resguardoEnCurso.current = null;
            }
        })();
        resguardoEnCurso.current = peticion;
        return peticion;
    };

    const guardarInstruccionResguardo = () => { persistirResguardo().catch(() => {}); };

    const enviarRevision = async () => {
        if (operacionRef.current || ocupado) return;
        operacionRef.current = true;
        setGuardando(true); setAviso('');
        try {
            if (resguardoEstado !== 'guardado') await persistirResguardo();
            const { data } = await window.axios.put(route('control_pedidos.enviar', actual.id), {}, { headers: { Accept: 'application/json' } });
            actualizar({ ...actual, ...data });
            setAviso('Pedido enviado a revisión.');
        } catch (error) {
            setAviso(Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || error.message || 'No se pudo enviar a revisión.');
        } finally {
            operacionRef.current = false;
            setGuardando(false);
        }
    };

    const solicitarContinuacion = async () => {
        if (operacionRef.current || ocupado) return;
        operacionRef.current = true;
        setGuardando(true); setAviso('');
        try {
            const { data } = await window.axios.post(route('control_pedidos.municipal.resguardo.continuacion', actual.id), {
                revision_esperada: actual.revision_registro,
                base_captura: actual.base_captura,
            }, { headers: { Accept: 'application/json' } });
            actualizar({ ...actual, ...data });
            setAviso('Continuación solicitada. Quien audita libera el resguardo; no se despacha en automático.');
        } catch (error) {
            setAviso(Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || error.message || 'No se pudo solicitar la continuación.');
        } finally {
            operacionRef.current = false;
            setGuardando(false);
        }
    };

    const cerrarAcumulacion = async () => {
        if (operacionRef.current || ocupado) return;
        operacionRef.current = true;
        setGuardando(true); setAviso('');
        try {
            const { data } = await window.axios.post(route('control_pedidos.municipal.resguardo.acumulacion', actual.id), {
                revision_esperada: actual.revision_registro,
                base_captura: actual.base_captura,
            }, { headers: { Accept: 'application/json' } });
            actualizar({ ...actual, ...data });
            setAviso('Acumulación cerrada. Las cajas se indican cuando se conozcan.');
        } catch (error) {
            setAviso(Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || error.message || 'No se pudo cerrar la acumulación.');
        } finally {
            operacionRef.current = false;
            setGuardando(false);
        }
    };

    const reabrir = async () => {
        setGuardando(true); setAviso('');
        try {
            await window.axios.post(route('control_pedidos.reabrir_consulta', actual.id), {}, { headers: { Accept: 'application/json' } });
            await refrescar();
        } catch (error) { setAviso(error.response?.data?.message || 'No se pudo solicitar otra revisión.'); }
        finally { setGuardando(false); }
    };

    const consultaCerrada = Boolean(actual.consulta_cerrada);
    const solicitudGuardada = Boolean(actual.catalogo_paqueteria_id);
    const fase = actual.estatus?.fase_ciclo;
    const enRevision = ['PENDIENTE_AUXILIAR', 'EN_CEDIS', 'PENDIENTE_DE_GUIA', 'PENDIENTE_DE_ENVIO', 'ENVIADO'].includes(fase);
    const caps = actual.resguardo_envio?.capacidades || {};
    const puedeEnviarRevision = consultaCerrada && !enRevision && !conversion;
    const mercanciaCubierta = ['declarada', 'auditada'].includes(actual.resguardo_envio?.cobertura_mercancia);
    const motivoResguardo = resguardoActivo && !resguardoMotivo ? 'Indique el motivo de la espera.'
        : resguardoActivo && resguardoMotivo === 'fecha_posterior' && !resguardoFecha ? 'Indique la fecha solicitada por el cliente.'
            : resguardoEstado === 'sin_guardar' || resguardoEstado === 'error' ? 'Guarde la instrucción de resguardo antes de enviar a revisión.'
                : (resguardoActivo && !mercanciaCubierta) ? 'El pedido aún no está pagado; no puede proceder para resguardo.'
                    : (resguardoActivo || actual.resguardo_envio?.activo) && actual.resguardo_envio?.motivo_bloqueo_resguardo
                        ? actual.resguardo_envio.motivo_bloqueo_resguardo : '';
    const motivoEnvio = enviada ? 'La consulta ya fue enviada.'
        : bloqueado ? 'Esta solicitud ya no admite envío.'
        : motivoComun || (!paqueteria ? 'Seleccione una terminal habilitada.'
        : !consignatarioId ? 'Seleccione el consignatario.'
        : !(puedeDiferido && costoModo === 'diferido') && (!Number.isFinite(Number(costo)) || Math.round(Number(costo) * 100) <= 0)
            ? 'Indique el costo de transporte, mayor que cero.' : '');
    const cambiar = setter => evento => { setter(evento.target.value); setCambios(true); };

    const contenido = <>
                    {aviso && <p className="text-sm theme-text-main m-0" role="status">{aviso}</p>}
                    <section className="registrar-tienda-panel">
                        <div className="registrar-tienda-seccion-titulo"><MapPin size={18} /><h3>Destino y procedencia</h3></div>
                        <fieldset disabled={guardando || ocupado || bloqueado} className="grid sm:grid-cols-2 gap-4 min-w-0">
                            <label className={THEME_LABEL}>Terminal destino
                                <select className={`${THEME_SELECT} w-full mt-1`} value={paqueteriaId} onChange={evento => {
                                    setPaqueteriaId(evento.target.value); setCambios(true);
                                    const terminal = paqueterias.find(item => String(item.id) === evento.target.value);
                                    if (!terminal?.permite_costo_diferido) setCostoModo('conocido');
                                    if (!terminal?.permite_por_cobrar) setLeyenda('PAGADO');
                                }}>
                                    <option value="">Elegir terminal</option>
                                    {!paqueteria && actual.paqueteria && <option value={actual.catalogo_paqueteria_id} disabled>{actual.paqueteria.nombre} · No disponible</option>}
                                    {paqueterias.map(item => <option key={item.id} value={item.id}>{item.nombre}</option>)}
                                </select>
                                {paqueteria && <span className="block sm:hidden text-sm normal-case tracking-normal theme-text-main mt-2 break-words">{paqueteria.nombre}</span>}
                            </label>
                            <label className={THEME_LABEL}>Almacén de la mercancía
                                <select className={`${THEME_SELECT} w-full mt-1`} value={almacenId} onChange={cambiar(setAlmacenId)}>
                                    <option value="">CEDIS, si no está en punto de venta</option>
                                    {(catalogos.almacenes || []).map(almacen => <option key={almacen.id} value={almacen.id}>{almacen.nombre}</option>)}
                                </select>
                            </label>
                        </fieldset>
                        {!paqueterias.length && <p className="text-sm theme-text-aviso mt-3 mb-0" role="status">No hay terminales municipales habilitadas. Revise que estén activas y en la categoría Local / Regional en Catálogos → Paqueterías.</p>}
                    </section>
                    <div className="grid md:grid-cols-2 gap-4 items-start min-w-0">
                    <section className="registrar-tienda-panel">
                        <div className="registrar-tienda-seccion-titulo"><UserRound size={18} /><h3>Consignatario</h3></div>
                        <fieldset disabled={guardando || ocupado} className="min-w-0 border-0 p-0 m-0"><ExpedienteConsignatario clienteId={actual.cliente_id || actual.cliente?.id} pedidoId={actual.id} seleccionado={consignatarioId} onSeleccionar={bloqueado ? undefined : valor => { setConsignatarioId(valor); setCambios(true); }} compacto /></fieldset>
                    </section>
                    <section className="registrar-tienda-panel">
                        <div className="registrar-tienda-seccion-titulo"><Coins size={18} /><h3>Condiciones del transporte</h3></div>
                        <fieldset disabled={guardando || ocupado || bloqueado} className="grid gap-4 min-w-0">
                            <label className={THEME_LABEL}>Costo de transporte
                                <select className={`${THEME_SELECT} w-full mt-1`} value={costoModo} onChange={cambiar(setCostoModo)}>
                                    <option value="conocido">Ya se conoce</option>
                                    {(puedeDiferido || bloqueado && costoModo === 'diferido') && <option value="diferido">Se conocerá en terminal</option>}
                                </select>
                            </label>
                            <label className={THEME_LABEL}>Leyenda para la terminal
                                <select className={`${THEME_SELECT} w-full mt-1`} value={leyenda} onChange={cambiar(setLeyenda)}>
                                    <option value="PAGADO">PAGADO</option>
                                    {(puedePorCobrar || bloqueado && leyenda === 'POR_COBRAR') && <option value="POR_COBRAR">POR COBRAR</option>}
                                </select>
                            </label>
                            {costoModo !== 'diferido' && <label className={THEME_LABEL}>Importe de transporte
                                <input className={`${THEME_INPUT} w-full mt-1`} type="number" min="0.01" step="0.01" value={costo} onChange={cambiar(setCosto)} />
                            </label>}
                        </fieldset>
                        <p className="text-xs theme-text-muted mt-3 mb-0">La leyenda corresponde al transporte; no acredita el pago del cliente.{costoModo === 'diferido' ? ' El costo queda pendiente de determinar.' : ''}</p>
                    </section>
                    </div>
                    {(actual.documentos || []).filter(doc => doc.tipo === 'evidencia_condicion').length > 0 && <section className="registrar-tienda-panel">
                        <div className="registrar-tienda-seccion-titulo"><FileText size={18} /><h3>Evidencias de la respuesta</h3></div>
                        <div className="flex flex-wrap gap-2">{actual.documentos.filter(doc => doc.tipo === 'evidencia_condicion').map(doc => <button key={doc.id} type="button" className={GELIA_BTN_OUTLINE} onClick={() => setDocPreview(doc)}>{doc.nombre_original || 'Ver evidencia'}</button>)}</div>
                    </section>}
                    {enviada && <button type="button" className={`${GELIA_BTN_OUTLINE} justify-self-start`} disabled={guardando || ocupado} onClick={() => refrescar().catch(() => setAviso('No se pudo actualizar la consulta.'))}><RefreshCw size={16} />Actualizar respuesta</button>}
                    {!conversion && <SeguimientoSolicitudMunicipal pedido={actual} onCerrar={cerrarConsulta} onReabrir={reabrir} cerrando={guardando || ocupado} onPedidoActualizado={actualizar} onOcupado={setAjustePendiente} />}
                    {solicitudGuardada && !conversion && (
                        <section className="registrar-tienda-panel">
                            <div className="registrar-tienda-seccion-titulo"><PauseCircle size={18} /><h3>Resguardo de envío</h3></div>
                            <fieldset disabled={guardando || ocupado || enRevision} className="grid gap-4 min-w-0">
                                <label className="flex items-start gap-3 text-sm theme-text-main">
                                    <input type="checkbox" className="mt-1 w-5 h-5" checked={resguardoActivo} onChange={(evento) => {
                                        setResguardoActivo(evento.target.checked);
                                        marcarResguardoSinGuardar();
                                    }} />
                                    <span>Dejar en resguardo. La mercancía espera; no se despacha hasta que quien audita lo libere. No cierra la consulta.</span>
                                </label>
                                {resguardoActivo && (
                                    <>
                                        <label className={THEME_LABEL}>Motivo de la espera
                                            <select className={`${THEME_SELECT} w-full mt-1`} value={resguardoMotivo} onChange={(evento) => {
                                                setResguardoMotivo(evento.target.value);
                                                marcarResguardoSinGuardar();
                                            }}>
                                                <option value="">Elegir motivo</option>
                                                <option value="fecha_posterior">Entrega en fecha posterior</option>
                                                <option value="acumulacion">Acumular mercancía</option>
                                                <option value="espera_complemento">Esperar un complemento</option>
                                            </select>
                                        </label>
                                        {resguardoMotivo === 'fecha_posterior' && (
                                            <label className={THEME_LABEL}>Fecha solicitada por el cliente
                                                <input className={`${THEME_INPUT} w-full mt-1`} type="date" value={resguardoFecha} onChange={(evento) => {
                                                    setResguardoFecha(evento.target.value);
                                                    marcarResguardoSinGuardar();
                                                }} />
                                                <span className="block text-xs theme-text-muted mt-1">Llegar a esta fecha no libera el envío. Hace falta su instrucción y la autorización de quien audita.</span>
                                            </label>
                                        )}
                                        {resguardoMotivo === 'acumulacion' && (
                                            <p className="text-xs theme-text-muted m-0">Las cajas del conjunto se indican cuando se conozcan. No use cero como si ya se hubiera medido.</p>
                                        )}
                                    </>
                                )}
                            </fieldset>
                            <div className="flex flex-wrap items-center gap-3 mt-3">
                                <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={guardando || ocupado || enRevision || resguardoEstado === 'guardando' || resguardoEstado === 'guardado' || (resguardoActivo && (!resguardoMotivo || (resguardoMotivo === 'fecha_posterior' && !resguardoFecha)))} onClick={guardarInstruccionResguardo}>
                                    {resguardoEstado === 'error' ? 'Reintentar guardado' : 'Guardar instrucción'}
                                </button>
                                <span className="text-sm theme-text-main" role="status" aria-live="polite" data-estado-resguardo={resguardoEstado}>
                                    {{ guardado: 'Guardado', sin_guardar: 'Sin guardar', guardando: 'Guardando…', error: 'Error al guardar' }[resguardoEstado]}
                                </span>
                            </div>
                            <div className="mt-3"><EstadosResguardoMunicipal estados={actual.resguardo_envio?.estados} /></div>
                            <p className="text-xs theme-text-muted mt-3 mb-0">El transporte pendiente o diferido no cuenta como pagado. Un crédito de Wizerp no habilita este resguardo.</p>
                        </section>
                    )}
                    {solicitudGuardada && !conversion && (
                        <RemisionesPagadasMunicipal pedido={actual} ocupado={guardando || ocupado} onPedidoActualizado={actualizar} onAviso={setAviso} />
                    )}
                    {consultaCerrada && !conversion && (
                        <section className="registrar-tienda-panel">
                            <div className="registrar-tienda-seccion-titulo"><Coins size={18} /><h3>Pagos de la mercancía</h3></div>
                            <SeccionPagosExhibicion
                                pedidoId={actual.id}
                                bancos={catalogos.bancos || []}
                                formasPago={catalogos.formas_pago || []}
                                puedeRegistrar={!enRevision}
                                totalMercancia={actual.total_mercancia}
                                costoEnvio={actual.costo_envio}
                                aplicaSeguro={Boolean(actual.aplica_seguro)}
                                costoSeguro={actual.costo_seguro}
                                saldoAFavorAplicado={actual.saldo_a_favor}
                                revisionEsperada={actual.revision_registro}
                                onLectura={(lectura) => actualizar({ ...actual, ...lectura })}
                            />
                        </section>
                    )}
                    <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview} onClose={() => setDocPreview(null)} />
                </>;
    const pie = <>

                    <p id="municipal-motivo-envio" className="registrar-tienda-pie-estado" aria-live="polite">{motivoResguardo || motivoEnvio || etapa || aviso || (puedeEnviarRevision ? 'Registre el pago de la mercancía y envíe a revisión.' : 'Al enviar se guardarán los datos automáticamente.')}</p>
                    <div className="registrar-tienda-pie-acciones">
                        {!bloqueado && <button type="button" className={GELIA_BTN_OUTLINE} disabled={guardando || ocupado} onClick={guardar}><Save size={16} />Guardar solicitud</button>}
                        {!enviada && <button type="button" className={THEME_BTN_PRIMARY} disabled={guardando || ocupado || Boolean(motivoEnvio)} aria-describedby="municipal-motivo-envio" onClick={enviar}><Send size={16} />{guardando ? (etapa === 'Guardando documento.' ? 'Guardando documento' : etapa === 'Enviando consulta.' ? 'Enviando consulta' : 'Preparando solicitud') : 'Enviar consulta'}</button>}
                        {caps.puede_cerrar_acumulacion && !confirmaCierre && <button type="button" className={GELIA_BTN_OUTLINE} disabled={guardando || ocupado} onClick={() => setConfirmaCierre(true)}>Cerrar acumulación</button>}
                        {caps.puede_cerrar_acumulacion && confirmaCierre && (
                            <span role="group" aria-label="Confirmar cierre de acumulación" className="inline-flex flex-wrap items-center gap-2">
                                <span className="text-sm theme-text-main">¿Cerrar la acumulación? No se podrán agregar más compras; el envío sigue en resguardo.</span>
                                <button type="button" className={GELIA_BTN_OUTLINE} disabled={guardando || ocupado} onClick={async () => { setConfirmaCierre(false); await cerrarAcumulacion(); }}>Confirmar cierre</button>
                                <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => setConfirmaCierre(false)}>Cancelar</button>
                            </span>
                        )}
                        {caps.puede_solicitar_continuacion && <button type="button" className={GELIA_BTN_OUTLINE} disabled={guardando || ocupado} onClick={solicitarContinuacion}>Solicitar continuación</button>}
                        {puedeEnviarRevision && <button type="button" className={THEME_BTN_PRIMARY} disabled={guardando || ocupado || Boolean(motivoResguardo)} aria-describedby="municipal-motivo-envio" onClick={enviarRevision}><Send size={16} />{guardando ? 'Enviando…' : 'Enviar a revisión'}</button>}
                    </div>
                </>;
    const pieConversion = onConfirmarConversion ? (
        <div className="registrar-tienda-pie-acciones">
            <button type="button" className={THEME_BTN_PRIMARY} disabled={guardando || ocupado} onClick={() => onConfirmarConversion(camposMunicipales())}>
                Confirmar cambio
            </button>
        </div>
    ) : null;
    return children({
        contenido,
        pie: conversion ? pieConversion : pie,
        bloqueado,
        guardando,
        captura: camposMunicipales(),
        motivoEnvio,
        sucia: cambios,
    });
}
