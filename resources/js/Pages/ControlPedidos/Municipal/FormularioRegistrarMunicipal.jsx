import React, { useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { MapPin, UserRound, Coins, FileText, Save, Send, RefreshCw } from 'lucide-react';
import { GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';
import ModalVistaPreviaDocumento from '../Partials/ModalVistaPreviaDocumento';
import { terminalesMunicipales } from './terminalMunicipal';
import { THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';
import ExpedienteConsignatario from './ExpedienteConsignatario';
import SeguimientoSolicitudMunicipal from './SeguimientoSolicitudMunicipal';

export default function FormularioRegistrarMunicipal({ abierto, pedido, catalogos = {}, onPedidoCreado, antesDeGuardar, asegurarDocumento, onLectura, motivoComun = '', ocupado = false, onOcupado, children }) {
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
    const [guardando, setGuardando] = useState(false);
    const [etapa, setEtapa] = useState('');
    const [ajustePendiente, setAjustePendiente] = useState(false);
    const operacionRef = useRef(false);
    const [actual, setActual] = useState(pedido);
    const [docPreview, setDocPreview] = useState(null);
    const [cambios, setCambios] = useState(Boolean(Object.keys(borrador).length));
    const enviada = Boolean(actual.solicitud_preparacion?.consulta_municipal?.enviada_at);
    const bloqueado = enviada || Boolean(actual.consulta_cerrada) || Boolean(actual.cancelado_at);
    useEffect(() => { setActual(pedido); }, [pedido]);
    useEffect(() => { onOcupado?.(guardando || ajustePendiente); }, [guardando, ajustePendiente, onOcupado]);
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

    const persistirSolicitud = async () => {
        const comun = antesDeGuardar ? await antesDeGuardar() : actual;
        if (!comun?.id) throw new Error('No se pudieron guardar los datos del pedido. Revise el aviso del formulario antes de continuar.');
        const { data } = await window.axios.post(route('control_pedidos.municipal.solicitud', comun.id), {
            catalogo_paqueteria_id: Number(paqueteriaId),
            consignatario_id: Number(consignatarioId),
            almacen_id: almacenId ? Number(almacenId) : null,
            leyenda_terminal: puedePorCobrar ? leyenda : 'PAGADO',
            costo_transporte: puedeDiferido && costoModo === 'diferido' ? 'diferido' : 'conocido',
            costo_envio: costo === '' ? null : costo,
        }, { headers: { Accept: 'application/json' } });
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

    const cerrarConsulta = async () => {
        setGuardando(true);
        try {
            await window.axios.post(route('control_pedidos.cerrar_consulta', actual.id), {}, { headers: { Accept: 'application/json' } });
            await refrescar();
        } catch (error) {
            setAviso(error.response?.data?.message || 'No se pudo cerrar la consulta.');
        } finally {
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
                    <SeguimientoSolicitudMunicipal pedido={actual} onCerrar={cerrarConsulta} onReabrir={reabrir} cerrando={guardando || ocupado} onPedidoActualizado={actualizar} onOcupado={setAjustePendiente} />
                    <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview} onClose={() => setDocPreview(null)} />
                </>;
    const pie = <>

                    <p id="municipal-motivo-envio" className="registrar-tienda-pie-estado" aria-live="polite">{motivoEnvio || etapa || aviso || 'Al enviar se guardarán los datos automáticamente.'}</p>
                    <div className="registrar-tienda-pie-acciones">
                        {!bloqueado && <button type="button" className={GELIA_BTN_OUTLINE} disabled={guardando || ocupado} onClick={guardar}><Save size={16} />Guardar solicitud</button>}
                        {!enviada && <button type="button" className={THEME_BTN_PRIMARY} disabled={guardando || ocupado || Boolean(motivoEnvio)} aria-describedby="municipal-motivo-envio" onClick={enviar}><Send size={16} />{guardando ? (etapa === 'Guardando documento.' ? 'Guardando documento' : etapa === 'Enviando consulta.' ? 'Enviando consulta' : 'Preparando solicitud') : 'Enviar consulta'}</button>}
                    </div>
                </>;
    return children({ contenido, pie, bloqueado, guardando });
}
