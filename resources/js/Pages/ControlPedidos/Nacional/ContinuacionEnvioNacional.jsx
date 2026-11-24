import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import SeccionPagosExhibicion from '../Partials/SeccionPagosExhibicion';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_INPUT, THEME_SELECT } from '../../../utils/geliaTheme';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import { elegirDireccionParaPedido } from '../Partials/elegirDireccionParaPedido';
import { camposDesdeDireccion, resumirCamposDireccion } from '../Partials/CamposDireccionPedido';
import ModalAgregarDireccionNacional from './ModalAgregarDireccionNacional';
import ModalGenerarLinkDireccion from '../Partials/ModalGenerarLinkDireccion';
import DesgloseCotizacionNacional from './DesgloseCotizacionNacional';
import useVistaPreviaCotizacion from './useVistaPreviaCotizacion';

const opcionesJson = { headers: { Accept: 'application/json' } };
const fasesCaptura = ['PESAJE_RESPONDIDO', 'RECHAZADO_VENDEDORA'];

export default function ContinuacionEnvioNacional({ pedido, catalogos, pendiente, importePendiente, onPedido, puedeAgregarDireccion = false, puedeGenerarEnlace = false }) {
    const [direcciones, setDirecciones] = useState([]);
    const [agregandoDireccion, setAgregandoDireccion] = useState(false);
    const [enviandoEnlace, setEnviandoEnlace] = useState(false);
    const [recarga, setRecarga] = useState(0);
    const [errorDireccion, setErrorDireccion] = useState('');
    const [cargandoDireccion, setCargandoDireccion] = useState(false);
    const [seleccion, setSeleccion] = useState({});
    const [fletes, setFletes] = useState({});
    const [seguro, setSeguro] = useState(null);
    const [ocupado, setOcupado] = useState(false);
    const [error, setError] = useState('');
    const clave = useRef(uuidSolicitud());
    const avisoError = useRef(null);
    useEffect(() => {
        if (error) avisoError.current?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' });
    }, [error]);
    const fase = pedido.estatus?.fase_ciclo;
    const puedeEnviar = fasesCaptura.includes(fase);
    const mercanciaPagada = Boolean(pedido.mercancia_pagada || pedido.solicitud_preparacion?.pago_solo_mercancia_nacional);
    const cotizada = String(pedido.solicitud_preparacion?.cotizacion_nacional?.paqueteria_id || '')
        === String(pedido.catalogo_paqueteria_id);
    const cajas = (pedido.cajas || []).filter((caja) => !['retirada', 'cancelada'].includes(caja.estado_operativo));
    const puedeCotizar = !pendiente && cajas.length > 0 && (puedeEnviar || !cotizada || !pedido.pago_validado_at);
    const listaParaPago = !importePendiente && (pendiente || cotizada) && Number(pedido.total_mercancia) > 0;
    const bancos = (catalogos.bancos || []).filter((banco) => banco.compartido !== false
        || (banco.departamento_ids || []).some((id) => String(id) === String(pedido.departamento_operacion_id)));

    useEffect(() => {
        if (!puedeCotizar || !pedido.cliente_id) return undefined;
        let vigente = true;
        setErrorDireccion('');
        setDirecciones([]);
        setCargandoDireccion(true);
        axios.get(`/api/clientes/id/${pedido.cliente_id}/direccion-envio`, opcionesJson)
            .then(({ data }) => { if (vigente) setDirecciones(data.direcciones || []); })
            .catch(() => { if (vigente) setErrorDireccion('No se pudieron cargar las direcciones. Vuelva a abrir el pedido para reintentar.'); })
            .finally(() => { if (vigente) setCargandoDireccion(false); });
        return () => { vigente = false; };
    }, [pedido.cliente_id, puedeCotizar, recarga]);

    useEffect(() => {
        if (!puedeCotizar || !direcciones.length) return;
        setSeleccion((previa) => {
            if (previa.cliente_direccion_id || pedido.cliente_direccion_id) return previa;
            const elegida = elegirDireccionParaPedido(direcciones);
            return elegida ? { ...previa, cliente_direccion_id: String(elegida.id) } : previa;
        });
    }, [puedeCotizar, direcciones, pedido.cliente_direccion_id]);

    const fletesCaja = cajas.reduce((suma, caja) => suma + Number(caja.costo_envio || 0), 0);
    const reexpedicion = Math.max(0, Number(pedido.costo_envio || 0) - fletesCaja);
    const desglose = cotizada && !pendiente && Number(pedido.costo_envio) > 0 ? [
        ['Mercancía', pedido.total_mercancia],
        ['Flete', fletesCaja],
        ['Reexpedición', reexpedicion],
        ['Seguro', pedido.aplica_seguro ? pedido.costo_seguro : 0],
    ] : [];

    const sinDirecciones = !cargandoDireccion && !errorDireccion && direcciones.length === 0;
    const ayudaSinDirecciones = puedeAgregarDireccion && puedeGenerarEnlace
        ? 'Agregue una o envíe el enlace para que el cliente la registre.'
        : puedeAgregarDireccion ? 'Agregue una para cotizar.'
            : puedeGenerarEnlace ? 'Envíe el enlace para que el cliente la registre.'
                : 'Pida a un auxiliar con permiso de direcciones que la agregue y vuelva a abrir el pedido.';
    const valorSeleccion = (campo) => seleccion[campo] ?? String(pedido[campo] || '');
    const paqueteria = (catalogos.paqueterias || []).find((item) => String(item.id) === String(pedido.catalogo_paqueteria_id));
    const coberturaSeguro = paqueteria?.cobertura_seguro !== false;
    const seguroActivo = coberturaSeguro && (seguro ?? Boolean(pedido.aplica_seguro));
    const lineasCaptura = cajas.map((caja) => ({
        uuid_operativo: caja.uuid_operativo,
        costo_envio: Number(fletes[caja.id] ?? (cotizada ? caja.costo_envio : '')) || 0,
    }));
    const { datos: vistaPrevia, cargando: calculando } = useVistaPreviaCotizacion({
        pedidoId: pedido.id,
        paqueteriaId: pedido.catalogo_paqueteria_id,
        zonaId: valorSeleccion('catalogo_zona_id'),
        lineas: lineasCaptura,
        aplicaSeguro: seguroActivo,
        activo: puedeCotizar,
    });
    const desgloseVivo = vistaPrevia ? [
        ['Mercancía', vistaPrevia.venta],
        ['Flete', vistaPrevia.flete],
        ['Reexpedición', vistaPrevia.reexpedicion],
        ['Seguro', vistaPrevia.seguro],
        ['Total', vistaPrevia.total],
    ] : [];
    const direccionElegida = direcciones.find((item) => String(item.id) === String(valorSeleccion('cliente_direccion_id')));
    const etiquetaOpcionDireccion = (item) => [
        item.es_principal ? 'Principal' : (item.etiqueta || 'Adicional'),
        item.nombre_destinatario,
        item.codigo_postal,
    ].filter(Boolean).join(' · ');
    const direccionAgregada = (nueva) => {
        setDirecciones((previas) => [nueva, ...previas.filter((item) => String(item.id) !== String(nueva.id))]);
        setSeleccion((previa) => ({ ...previa, cliente_direccion_id: String(nueva.id) }));
        setError('');
    };
    const ejecutar = async (accion) => {
        if (ocupado) return;
        setOcupado(true);
        setError('');
        try {
            const { data } = await accion();
            onPedido(data);
        } catch (fallo) {
            const datos = fallo.response?.status < 500 ? fallo.response.data : null;
            setError(Object.values(datos?.errors || {}).flat()[0] || datos?.message || 'No se completó la operación. Intente de nuevo.');
        } finally {
            setOcupado(false);
        }
    };

    const cotizar = () => {
        const cotizacion = Object.fromEntries(
            ['cliente_direccion_id', 'catalogo_tipo_guia_id', 'catalogo_zona_id'].map((campo) => [campo, Number(valorSeleccion(campo))]),
        );
        const lineas = cajas.map((caja) => ({
            uuid_operativo: caja.uuid_operativo,
            costo_envio: Number(fletes[caja.id] ?? (cotizada ? caja.costo_envio : '')),
        }));
        if (Object.values(cotizacion).some((id) => !id) || lineas.some((linea) => !(linea.costo_envio > 0))) {
            setError('Elija dirección, tipo de guía y reexpedición. Indique el flete de cada caja.');
            return;
        }
        ejecutar(async () => {
            const respuesta = await axios.post(`/control-pedidos/${pedido.id}/paqueteria-nacional`, {
                catalogo_paqueteria_id: pedido.catalogo_paqueteria_id,
                solicitud_id: clave.current,
                revision_esperada: pedido.revision_registro,
                base_captura: pedido.base_captura,
                aplica_seguro: seguroActivo,
                lineas,
                cotizacion,
            }, opcionesJson);
            clave.current = uuidSolicitud();
            setSeleccion({});
            setFletes({});
            setSeguro(null);
            return respuesta;
        });
    };

    return (
        <section className="registrar-tienda-panel space-y-3" aria-label="Cotización y pago">
            <h3 className="m-0 text-base">{pendiente ? 'Pago de mercancía' : 'Cotización y pago'}</h3>
            {mercanciaPagada && <p className="m-0 text-sm" role="status">Mercancía pagada. Se conservan las exhibiciones verificadas.</p>}
            {puedeCotizar && (
                <details className="space-y-3" open={!cotizada || undefined}>
                    <summary className="cursor-pointer font-semibold min-h-[44px] py-2">{cotizada ? 'Revisar cotización' : 'Capturar cotización'}</summary>
                    <div role="group" aria-label="Dirección de entrega" className="space-y-2 rounded-lg border theme-border p-3">
                        <div className="min-w-0">
                            <label className="block min-w-0 text-sm">
                                Dirección de entrega
                                <select className={`${THEME_SELECT} mt-1 w-full min-h-[44px]`} value={valorSeleccion('cliente_direccion_id')}
                                    onChange={(evento) => { setSeleccion({ ...seleccion, cliente_direccion_id: evento.target.value }); setError(''); }}>
                                    <option value="">Elegir</option>
                                    {direcciones.map((item) => <option key={item.id} value={item.id}>{etiquetaOpcionDireccion(item)}</option>)}
                                </select>
                            </label>
                            {cargandoDireccion && <p className="m-0 mt-2 text-sm theme-text-muted" role="status">Cargando direcciones…</p>}
                            {direccionElegida && (
                                <dl className="m-0 mt-2 grid gap-1 rounded-lg border theme-border p-3 text-sm" aria-label="Dirección elegida">
                                    <div>
                                        <dt className="theme-text-muted text-xs">Nombre de quien recibe</dt>
                                        <dd className="m-0 font-semibold">{direccionElegida.nombre_destinatario || '—'}</dd>
                                    </div>
                                    {direccionElegida.telefono_destinatario ? (
                                        <div>
                                            <dt className="theme-text-muted text-xs">Teléfono</dt>
                                            <dd className="m-0">{direccionElegida.telefono_destinatario}</dd>
                                        </div>
                                    ) : null}
                                    <div>
                                        <dt className="theme-text-muted text-xs">Domicilio</dt>
                                        <dd className="m-0">{resumirCamposDireccion(camposDesdeDireccion(direccionElegida)) || '—'}</dd>
                                    </div>
                                </dl>
                            )}
                            {sinDirecciones && (
                                <p className="m-0 mt-2 text-sm theme-text-aviso" role="status">
                                    Este cliente no tiene dirección principal ni adicional. {ayudaSinDirecciones}
                                </p>
                            )}
                            {sinDirecciones && (
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {puedeAgregarDireccion && (
                                        <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} onClick={() => setAgregandoDireccion(true)}>Agregar dirección</button>
                                    )}
                                    {puedeGenerarEnlace && (
                                        <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} onClick={() => setEnviandoEnlace(true)}>Enviar enlace al cliente</button>
                                    )}
                                    <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} onClick={() => setRecarga((n) => n + 1)}>Actualizar direcciones</button>
                                </div>
                            )}
                            {!sinDirecciones && puedeAgregarDireccion && !cargandoDireccion && !errorDireccion && (
                                <button type="button" className={`${GELIA_BTN_OUTLINE} mt-2 min-h-[44px]`} onClick={() => setAgregandoDireccion(true)}>Agregar dirección</button>
                            )}
                            {errorDireccion && <p className="m-0 mt-2 text-sm theme-text-aviso" role="alert">{errorDireccion}</p>}
                        </div>
                    </div>
                    <div role="group" aria-label="Costo de envío" className="space-y-3 rounded-lg border theme-border p-3">
                        <div className="grid gap-3 sm:grid-cols-2">
                            {[
                                ['catalogo_tipo_guia_id', 'Tipo de guía', catalogos.tipos_guia || []],
                                ['catalogo_zona_id', 'Reexpedición', catalogos.zonas || []],
                            ].map(([campo, etiqueta, opciones]) => (
                                <div className="min-w-0" key={campo}>
                                    <label className="block min-w-0 text-sm">
                                        {etiqueta}
                                        <select className={`${THEME_SELECT} mt-1 w-full min-h-[44px]`} value={valorSeleccion(campo)}
                                            onChange={(evento) => { setSeleccion({ ...seleccion, [campo]: evento.target.value }); setError(''); }}>
                                            <option value="">Elegir</option>
                                            {opciones.map((item) => <option key={item.id} value={item.id}>{item.nombre}</option>)}
                                        </select>
                                    </label>
                                </div>
                            ))}
                            {cajas.map((caja, indice) => (
                                <label className="block min-w-0 text-sm" key={caja.id}>
                                    Flete de caja {indice + 1} (MXN)
                                    <input className={`${THEME_INPUT} mt-1 w-full min-h-[44px]`} type="number" inputMode="decimal" min="0.01" step="0.01" placeholder="0.00"
                                        value={fletes[caja.id] ?? (cotizada ? caja.costo_envio ?? '' : '')}
                                        onChange={(evento) => { setFletes({ ...fletes, [caja.id]: evento.target.value }); setError(''); }} />
                                </label>
                            ))}
                        </div>
                        {coberturaSeguro && (
                            <label className="flex min-h-[44px] items-center gap-2 text-sm">
                                <input type="checkbox" checked={seguroActivo} onChange={(evento) => setSeguro(evento.target.checked)} />
                                Incluir seguro (opcional)
                            </label>
                        )}
                        <DesgloseCotizacionNacional filas={desgloseVivo} cargando={calculando} />
                    </div>
                    <button type="button" className={`${cotizada ? GELIA_BTN_OUTLINE : THEME_BTN_PRIMARY} min-h-[44px]`} disabled={ocupado} onClick={cotizar}>Guardar cotización</button>
                </details>
            )}
            {!desgloseVivo.length && <DesgloseCotizacionNacional filas={desglose} />}
            {importePendiente && <p className="m-0 text-sm theme-text-muted" role="status">Guardando el importe de mercancía.</p>}
            {listaParaPago && (
                // Cualquier acción en pagos invalida un aviso anterior («Faltan $…») que ya no aplica.
                <div className="contents" onClickCapture={() => { if (error) setError(''); }}>
                <SeccionPagosExhibicion
                    pedidoId={pedido.id}
                    bancos={bancos}
                    formasPago={catalogos.formas_pago || []}
                    puedeRegistrar={!pedido.pago_validado_at}
                    totalMercancia={pedido.total_mercancia}
                    costoEnvio={pendiente ? 0 : pedido.costo_envio}
                    aplicaSeguro={!pendiente && pedido.aplica_seguro}
                    costoSeguro={pedido.costo_seguro}
                    saldoAFavorAplicado={pedido.saldo_a_favor}
                    omiteEnvio={pendiente}
                    costoReexpedicion={0}
                    envioEsBase
                    revisionEsperada={pedido.revision_registro}
                    totalMercanciaConfirmado={pedido.total_mercancia}
                    saldoAFavorConfirmado={pedido.saldo_a_favor}
                    revisionResumen={pedido.revision_registro}
                />
                </div>
            )}
            {puedeEnviar && listaParaPago && (
                <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} disabled={ocupado}
                    onClick={() => ejecutar(() => axios.put(`/control-pedidos/${pedido.id}/enviar`, {}, opcionesJson))}>
                    {ocupado ? 'Guardando…' : 'Enviar al auxiliar'}
                </button>
            )}
            {fase === 'PENDIENTE_AUXILIAR' && <p className="m-0 text-sm" role="status">El auxiliar verifica el pago y la remisión.</p>}
            {error && <p ref={avisoError} className="m-0 text-sm theme-text-aviso" role="alert">{error}</p>}
            <ModalGenerarLinkDireccion
                abierto={enviandoEnlace} onClose={() => setEnviandoEnlace(false)}
                clientePreseleccionado={pedido.cliente_id ? {
                    id: pedido.cliente_id,
                    numero_cliente: pedido.cliente?.numero_cliente,
                    nombre: pedido.cliente?.nombre,
                } : null}
            />
            <ModalAgregarDireccionNacional
                abierto={agregandoDireccion} onClose={() => setAgregandoDireccion(false)}
                pedido={pedido} esPrimera={direcciones.length === 0} onGuardada={direccionAgregada}
            />
        </section>
    );
}
