import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import SeccionPagosExhibicion from '../Partials/SeccionPagosExhibicion';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_INPUT, THEME_SELECT } from '../../../utils/geliaTheme';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';

const opcionesJson = { headers: { Accept: 'application/json' } };
const fasesCaptura = ['PESAJE_RESPONDIDO', 'RECHAZADO_VENDEDORA'];

export default function ContinuacionEnvioNacional({ pedido, catalogos, pendiente, importePendiente, onPedido }) {
    const [direcciones, setDirecciones] = useState([]);
    const [errorDireccion, setErrorDireccion] = useState('');
    const [cargandoDireccion, setCargandoDireccion] = useState(false);
    const [seleccion, setSeleccion] = useState({});
    const [fletes, setFletes] = useState({});
    const [seguro, setSeguro] = useState(null);
    const [ocupado, setOcupado] = useState(false);
    const [error, setError] = useState('');
    const clave = useRef(uuidSolicitud());
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
    }, [pedido.cliente_id, puedeCotizar]);

    const valorSeleccion = (campo) => seleccion[campo] ?? String(pedido[campo] || '');
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
                aplica_seguro: seguro ?? Boolean(pedido.aplica_seguro),
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
                <details className="space-y-3">
                    <summary className="cursor-pointer font-semibold min-h-[44px] py-2">{cotizada ? 'Revisar cotización' : 'Capturar cotización'}</summary>
                    {[
                        ['cliente_direccion_id', 'Dirección de entrega', direcciones, (item) => [item.etiqueta, item.nombre_destinatario, item.codigo_postal].filter(Boolean).join(' · ')],
                        ['catalogo_tipo_guia_id', 'Tipo de guía', catalogos.tipos_guia || [], (item) => item.nombre],
                        ['catalogo_zona_id', 'Reexpedición', catalogos.zonas || [], (item) => item.nombre],
                    ].map(([campo, etiqueta, opciones, nombre]) => (
                        <label className="block min-w-0 text-sm" key={campo}>
                            {etiqueta}
                            <select className={`${THEME_SELECT} mt-1 w-full min-h-[44px]`} value={valorSeleccion(campo)}
                                onChange={(evento) => { setSeleccion({ ...seleccion, [campo]: evento.target.value }); setError(''); }}>
                                <option value="">Elegir</option>
                                {opciones.map((item) => <option key={item.id} value={item.id}>{nombre(item)}</option>)}
                            </select>
                        </label>
                    ))}
                    {cargandoDireccion && <p className="m-0 text-sm theme-text-muted" role="status">Cargando direcciones…</p>}
                    {!cargandoDireccion && !errorDireccion && direcciones.length === 0 && (
                        <p className="m-0 text-sm theme-text-muted">Agregue una dirección verificada en el catálogo del cliente y vuelva a abrir el pedido.</p>
                    )}
                    {errorDireccion && <p className="m-0 text-sm theme-text-aviso" role="alert">{errorDireccion}</p>}
                    {cajas.map((caja, indice) => (
                        <label className="block min-w-0 text-sm" key={caja.id}>
                            Flete de caja {indice + 1}
                            <input className={`${THEME_INPUT} mt-1 w-full min-h-[44px]`} type="number" min="0.01" step="0.01"
                                value={fletes[caja.id] ?? (cotizada ? caja.costo_envio ?? '' : '')}
                                onChange={(evento) => { setFletes({ ...fletes, [caja.id]: evento.target.value }); setError(''); }} />
                        </label>
                    ))}
                    <label className="flex min-h-[44px] items-center gap-2 text-sm">
                        <input type="checkbox" checked={seguro ?? Boolean(pedido.aplica_seguro)} onChange={(evento) => setSeguro(evento.target.checked)} />
                        Incluir seguro
                    </label>
                    <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={ocupado} onClick={cotizar}>Guardar cotización</button>
                </details>
            )}
            {importePendiente && <p className="m-0 text-sm theme-text-muted" role="status">Guardando el importe de mercancía.</p>}
            {listaParaPago && (
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
            )}
            {puedeEnviar && listaParaPago && (
                <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} disabled={ocupado}
                    onClick={() => ejecutar(() => axios.put(`/control-pedidos/${pedido.id}/enviar`, {}, opcionesJson))}>
                    {ocupado ? 'Guardando…' : 'Enviar al auxiliar'}
                </button>
            )}
            {fase === 'PENDIENTE_AUXILIAR' && <p className="m-0 text-sm" role="status">El auxiliar verifica el pago y la remisión.</p>}
            {error && <p className="m-0 text-sm theme-text-aviso" role="alert">{error}</p>}
        </section>
    );
}
