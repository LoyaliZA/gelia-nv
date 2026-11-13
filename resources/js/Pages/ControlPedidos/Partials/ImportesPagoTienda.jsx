import CambioModalidadPagoTienda from './CambioModalidadPagoTienda';
import AccionPrincipalTienda from './AccionPrincipalTienda';
import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Banknote, Wallet, TriangleAlert, Save, Info } from 'lucide-react';
import { THEME_BTN_PRIMARY, GELIA_BTN_OUTLINE, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';
import InputMoneda from './InputMoneda';
import VigenciaApartadoTienda from './VigenciaApartadoTienda';
import SeccionPagosExhibicion from './SeccionPagosExhibicion';
import { calcularResumenCoberturaPago, formatearMoneda } from './pedidosBmaStyles';

const SOLICITUD_VACIA = {};

const MEDIOS_TIENDA = ['efectivo', 'tarjeta', 'deposito', 'otro'];

function cabeceras() {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function mensajeDe(error, reserva) {
    if (error?.response?.status === 419) return 'La sesión cambió o venció. Recargue GELIA antes de volver a guardar; no se confirmó la operación.';
    const datos = error?.response?.data;
    const lista = datos?.errors ? Object.values(datos.errors).flat() : [];
    return lista[0] || datos?.message || reserva;
}

function concepto(valor) {
    if (valor === null || valor === undefined || valor === '') return 'No aplica';
    return formatearMoneda(valor);
}

function repartirSaf(creditos, montoDeseado) {
    let restante = Math.max(0, Number(montoDeseado) || 0);
    const items = [];
    const ordenados = [...(creditos || [])].sort((a, b) => {
        const fa = String(a.fecha_vencimiento || '');
        const fb = String(b.fecha_vencimiento || '');
        if (fa !== fb) return fa < fb ? -1 : 1;
        return Number(a.id) - Number(b.id);
    });
    ordenados.forEach((credito) => {
        if (restante <= 0) return;
        const disponible = Number(credito.monto_disponible) || 0;
        const tomar = Math.min(disponible, restante);
        if (tomar <= 0) return;
        items.push({
            saf_credito_id: credito.id,
            folio: credito.folio,
            monto: Math.round(tomar * 100) / 100,
            fecha_vencimiento: credito.fecha_vencimiento,
        });
        restante = Math.round((restante - tomar) * 100) / 100;
    });
    return items;
}

export default function ImportesPagoTienda({
    notaMismoDia = 'Mismo día: consulta el horario operativo vigente.',
    pedidoId,
    destinoAccion,
    clienteId,
    solicitud: solicitudInicial = SOLICITUD_VACIA,
    onSolicitud,
    editable = false,
    totalMercancia = '',
    onTotalMercancia,
    aplicaSaf = false,
    onAplicaSaf,
    saldoFavor = '',
    onSaldoFavor,
    safAplicaciones = [],
    onSafAplicaciones,
    costos = {},
    importesListos = false,
    bancos = [],
    revisionEsperada = null,
    totalMercanciaConfirmado = null,
    saldoAFavorConfirmado = null,
    onLectura,
    onEstadoConfirmacion,
    encolarGuardado,
}) {
    const [solicitud, setSolicitud] = useState(solicitudInicial);
    useEffect(() => setSolicitud(solicitudInicial), [solicitudInicial]);
    const tipoGuardado = solicitud?.cobertura_tienda?.tipo || '';
    const [cobertura, setCobertura] = useState(tipoGuardado || (solicitud?.atencion_tienda === 'prevista' ? 'transferencia' : ''));
    const [mismoDia, setMismoDia] = useState(solicitud?.atencion_tienda === 'prevista' && ['', 'transferencia'].includes(tipoGuardado));
    const [cambioEnCaptura, setCambioEnCaptura] = useState(false);
    const [aviso, setAviso] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [safCuenta, setSafCuenta] = useState(null);
    const [cargandoSaf, setCargandoSaf] = useState(false);
    const [resumenServidor, setResumenServidor] = useState(null);
    const [coberturaGuardada, setCoberturaGuardada] = useState(null);
    const [atencionMd, setAtencionMd] = useState(null);
    const [errorSaf, setErrorSaf] = useState('');
    const sello = `${tipoGuardado}|${solicitud?.atencion_tienda || ''}|${solicitud?.tienda_md_reporte?.estado || ''}|${solicitud?.tienda_md_reporte?.primer_pago_at || ''}`;

    useEffect(() => {
        setCobertura(tipoGuardado || (solicitud?.atencion_tienda === 'prevista' ? 'transferencia' : ''));
        setMismoDia(solicitud?.atencion_tienda === 'prevista' && ['', 'transferencia'].includes(tipoGuardado));
    }, [sello]);

    useEffect(() => {
        if (!clienteId || !pedidoId) {
            setSafCuenta(null);
            return undefined;
        }
        let cancelado = false;
        setCargandoSaf(true);
        setErrorSaf('');
        axios.get(route('control_pedidos.cliente.saldo_favor', clienteId), {
            headers: { Accept: 'application/json' },
            params: { pedido_id: pedidoId },
        }).then((respuesta) => {
            if (!cancelado) setSafCuenta(respuesta.data);
        }).catch(() => {
            if (!cancelado) { setSafCuenta(null); setErrorSaf('No se pudo consultar el saldo. Intente de nuevo antes de aplicarlo.'); }
        }).finally(() => {
            if (!cancelado) setCargandoSaf(false);
        });
        return () => { cancelado = true; };
    }, [clienteId, pedidoId, revisionEsperada]);

    const saldoAplicado = aplicaSaf
        ? (safAplicaciones.length
            ? safAplicaciones.reduce((suma, fila) => suma + Number(fila.monto || 0), 0)
            : Number(saldoFavor || 0))
        : 0;
    const anticipo = calcularResumenCoberturaPago({
        totalMercancia: totalMercancia === '' ? 0 : totalMercancia,
        costoEnvio: costos.costo_envio || 0,
        aplicaSeguro: Boolean(costos.aplica_seguro),
        costoSeguro: costos.costo_seguro || 0,
        saldoAFavorAplicado: saldoAplicado,
        totalPagado: Number(resumenServidor?.total_pagado || 0),
    });
    const pendiente = importesListos && resumenServidor ? Number(resumenServidor.pendiente || 0) : anticipo.pendiente;
    const reporte = solicitud?.atencion_tienda === 'prevista' && tipoGuardado === 'transferencia'
        ? solicitud?.tienda_md_reporte
        : null;
    const mostrarCaptura = editable && cobertura === tipoGuardado && tipoGuardado === 'transferencia';
    const pagoEnPdv = cobertura === 'apartado';
    const mostrarHistorial = tipoGuardado === 'credito' || tipoGuardado === 'apartado' || tipoGuardado === 'transferencia';

    const guardarModalidad = async (tipo, dia) => {
        setOcupado(true);
        setAviso('');
        const guardar = async () => {
            const respuesta = await axios.post(
                `/control-pedidos/${pedidoId}/cobertura-tienda`,
                { cobertura: tipo, mismo_dia: tipo === 'transferencia' && dia },
                { headers: cabeceras() },
            );
            onLectura?.(respuesta.data, true);
            if (respuesta.data?.solicitud_preparacion) {
                setSolicitud(respuesta.data.solicitud_preparacion);
                onSolicitud?.(respuesta.data.solicitud_preparacion);
            }
            setAviso('Modalidad guardada.');
        };
        try {
            await (encolarGuardado ? encolarGuardado(guardar) : guardar());
        } catch (error) {
            setAviso(mensajeDe(error, 'No se guardó la modalidad.'));
        } finally {
            setOcupado(false);
        }
    };

    const confirmarPedido = async () => {
        if (ocupado) return;
        setOcupado(true);
        setAviso('');
        try {
            const { data } = await axios.post(`/control-pedidos/${pedidoId}/confirmar-pedido-tienda`, {}, { headers: cabeceras() });
            if (data?.solicitud_preparacion) {
                setSolicitud(data.solicitud_preparacion);
                onSolicitud?.(data.solicitud_preparacion);
            }
            setAviso('Confirmación enviada a tienda.');
        } catch (error) {
            setAviso(mensajeDe(error, 'No se envió la confirmación.'));
        } finally { setOcupado(false); }
    };
    const confirmacionEnviada = Boolean(solicitud.cobertura_tienda?.confirmacion_enviada_at);
    const venceApartado = solicitud.cobertura_tienda?.vence_at;
    const horariosActivos = solicitud.horarios_operativos?.activos !== false;
    const bloqueoConfirmacion = !horariosActivos ? '' : solicitud.cobertura_tienda?.motivo_vigencia
        || (!venceApartado ? 'Guarda la modalidad para calcular la vigencia.' : Date.parse(venceApartado) <= Date.now() ? 'La vigencia terminó. Revisa los horarios de Tienda.' : '');

    const modalidadPendiente = cobertura !== tipoGuardado
        || (cobertura === 'transferencia' && mismoDia !== (solicitud?.atencion_tienda === 'prevista'));
    const transferenciaCubierta = coberturaGuardada !== null && coberturaGuardada.pedidoId === pedidoId
        && coberturaGuardada.revision === revisionEsperada
        && coberturaGuardada.resumen?.cubierto === true
        && !(coberturaGuardada.resumen.bloqueos || []).length
        && resumenServidor?.cubierto === true;
    const procesosListos = editable && importesListos && !ocupado && !cambioEnCaptura && !modalidadPendiente && Boolean(tipoGuardado)
        && (tipoGuardado !== 'transferencia' || transferenciaCubierta);
    useEffect(() => { onEstadoConfirmacion?.(procesosListos); }, [onEstadoConfirmacion, procesosListos]);

    const cambiarCobertura = (valor) => {
        setCobertura(valor);
        if (valor === 'transferencia') return;
        setMismoDia(false);
        // Guardar modalidad persiste cobertura y atención juntas, sin cambios implícitos al seleccionar.
    };

    const creditosParaPedido = [...(safCuenta?.creditos_usables || [])].map((credito) => ({ ...credito }));
    for (const reserva of safCuenta?.reservas_pedido || []) {
        const credito = creditosParaPedido.find((fila) => Number(fila.id) === Number(reserva.id));
        if (credito) credito.monto_disponible = Number(credito.monto_disponible || 0) + Number(reserva.monto_reservado || 0);
        else creditosParaPedido.push({ ...reserva, monto_disponible: Number(reserva.monto_reservado || 0) });
    }
    const disponibleParaPedido = creditosParaPedido.reduce((suma, credito) => suma + Number(credito.monto_disponible || 0), 0);
    const aplicarSaldo = (encendido, monto = saldoFavor) => {
        onAplicaSaf?.(encendido);
        if (!encendido) {
            onSaldoFavor?.('');
            onSafAplicaciones?.([]);
            return;
        }
        const filas = repartirSaf(creditosParaPedido, Math.min(Number(monto || 0), Math.max(0, anticipo.total_a_cubrir - Number(resumenServidor?.total_pagado || 0))));
        const cubierto = filas.reduce((suma, fila) => suma + Number(fila.monto || 0), 0);
        onSaldoFavor?.(cubierto || '');
        onSafAplicaciones?.(filas.map((fila) => ({ saf_credito_id: fila.saf_credito_id, monto: fila.monto })));
    };

    return (
        <>
            <section className="registrar-tienda-panel" aria-labelledby="registrar-tienda-pago">
                <div className="registrar-tienda-seccion-titulo">
                    <Banknote size={18} aria-hidden="true" />
                    <h3 id="registrar-tienda-pago">Modalidad de pago</h3>
                </div>
                <div className="grid gap-3">
                    <p className="text-sm theme-text-muted m-0">
                        La modalidad no confirma el pago, la remisión ni la entrega.
                    </p>
                    <label className={THEME_LABEL} htmlFor="modalidad-cobertura-tienda">Modalidad de cobertura</label>
                    <select
                        id="modalidad-cobertura-tienda"
                        className={THEME_SELECT}
                        value={cobertura}
                        disabled={!editable || ocupado || Boolean(tipoGuardado)}
                        onChange={(evento) => cambiarCobertura(evento.target.value)}
                    >
                        <option value="">Elegir</option>
                        <option value="apartado">Pago al recoger</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="credito">Crédito</option>
                    </select>
                    {cobertura === 'transferencia' && (
                        <label className="flex items-start gap-2 text-sm theme-text-main">
                            <input
                                type="checkbox"
                                className="gelia-checkbox mt-1"
                                checked={mismoDia}
                                disabled={!editable || ocupado || Boolean(tipoGuardado)}
                                onChange={(evento) => setMismoDia(evento.target.checked)}
                            />
                            <span>
                                {notaMismoDia}
                            </span>
                        </label>
                    )}
                    {editable && !tipoGuardado && (
                        <button
                            type="button"
                            className={`${GELIA_BTN_OUTLINE} registrar-tienda-guardar-modalidad min-h-[44px] justify-self-start`}
                            disabled={ocupado || !cobertura}
                            onClick={() => guardarModalidad(cobertura, mismoDia)}
                        >
                            <Save size={16} aria-hidden="true" />
                            Guardar modalidad
                        </button>
                    )}
                    {tipoGuardado && <CambioModalidadPagoTienda pedidoId={pedidoId} solicitud={solicitud} onSolicitud={siguiente => { setSolicitud(siguiente); onSolicitud?.(siguiente); }} onPendiente={setCambioEnCaptura} />}
                    {aviso && <p className="text-sm theme-text-main m-0 break-words" role="status">{aviso}</p>}
                    {pagoEnPdv && <div role="note" aria-label="Pago y remisión en PDV" className="flex items-start gap-3 rounded-xl border border-[color-mix(in_srgb,var(--color-aviso)_40%,transparent)] bg-[color-mix(in_srgb,var(--color-aviso)_10%,transparent)] p-4">
                        <Info size={20} className="shrink-0 theme-text-aviso mt-0.5" aria-hidden="true" />
                        <div className="min-w-0 text-sm theme-text-main"><p className="font-semibold m-0">Pago y remisión en PDV</p><p className="mt-1 mb-0">Aquí no se registra el cobro ni se solicita comprobante. El auxiliar registra la entrega con número de remisión y fotografía.</p></div>
                    </div>}
                    {tipoGuardado === 'apartado' && horariosActivos && <VigenciaApartadoTienda venceAt={solicitud.cobertura_tienda?.vence_at} />}
                </div>
            </section>
            <section className="registrar-tienda-panel" aria-labelledby="registrar-tienda-importes">
                <div className="registrar-tienda-seccion-titulo">
                    <Wallet size={18} aria-hidden="true" />
                    <h3 id="registrar-tienda-importes">Importes del pedido</h3>
                </div>
                <div className="grid gap-3">
                    <div className="grid min-w-0 gap-1 min-[32rem]:grid-cols-[minmax(0,1fr)_auto] min-[32rem]:items-center min-[32rem]:gap-4">
                        <label className={THEME_LABEL} htmlFor="total-mercancia-tienda">Total de mercancía</label>
                        <InputMoneda
                            id="total-mercancia-tienda"
                            value={totalMercancia}
                            onChange={onTotalMercancia}
                            disabled={!editable || ocupado}
                            className="w-full min-h-[44px]"
                        />
                    </div>
                    {(Number(costos.costo_envio || 0) > 0 || costos.envio_realizado) && <><div className="grid min-w-0 gap-1 min-[32rem]:grid-cols-[minmax(0,1fr)_auto] min-[32rem]:items-center min-[32rem]:gap-4">
                        <span className="text-sm theme-text-muted">Envío</span>
                        <strong className="text-sm theme-text-main">{concepto(costos.costo_envio)}</strong>
                    </div>
                    <div className="grid min-w-0 gap-1 min-[32rem]:grid-cols-[minmax(0,1fr)_auto] min-[32rem]:items-center min-[32rem]:gap-4">
                        <span className="text-sm theme-text-muted">Reexpedición</span>
                        <strong className="text-sm theme-text-main">{Number(costos.costo_envio || 0) > 0 ? 'Incluida en envío, si corresponde' : 'No aplica'}</strong>
                    </div>
                    </>}
                    {(costos.aplica_seguro || Number(costos.costo_seguro || 0) > 0) && <div className="grid min-w-0 gap-1 min-[32rem]:grid-cols-[minmax(0,1fr)_auto] min-[32rem]:items-center min-[32rem]:gap-4">
                        <span className="text-sm theme-text-muted">Seguro</span>
                        <strong className="text-sm theme-text-main">
                            {costos.aplica_seguro ? concepto(costos.costo_seguro) : 'No aplica'}
                        </strong>
                    </div>
                    }<div className="grid gap-2">
                        <label className="flex items-center gap-2 text-sm theme-text-main">
                            <input
                                type="checkbox"
                                className="gelia-checkbox"
                                checked={aplicaSaf}
                                disabled={!editable || cargandoSaf || (!aplicaSaf && disponibleParaPedido <= 0)}
                                onChange={(evento) => aplicarSaldo(evento.target.checked, disponibleParaPedido)}
                            />
                            Saldo a favor
                            <span className="theme-text-muted">
                                {cargandoSaf ? 'Consultando…' : `Disponible ${formatearMoneda(safCuenta?.disponible || 0)}`}
                            </span>
                        </label>
                        {errorSaf && <p role="alert" className="text-sm theme-text-main m-0">{errorSaf}</p>}
                        {aplicaSaf && (
                            <InputMoneda
                                aria-label="Saldo a favor a aplicar"
                                value={saldoFavor}
                                disabled={!editable}
                                onChange={(valor) => aplicarSaldo(true, valor)}
                                className="w-full min-h-[44px]"
                            />
                        )}
                        {aplicaSaf && safAplicaciones.length === 0 && (
                            <p className="text-sm theme-text-muted m-0">No hay saldo aplicable en este almacén o área.</p>
                        )}
                    </div>
                    <div className="grid min-w-0 gap-1 min-[32rem]:grid-cols-[minmax(0,1fr)_auto] min-[32rem]:items-center min-[32rem]:gap-4">
                        <span className="text-sm theme-text-muted">Total exigible</span>
                        <strong className="text-sm theme-text-main">{formatearMoneda(anticipo.total_a_cubrir)}</strong>
                    </div>
                </div>
                <dl className="registrar-tienda-importes-resumen">
                    <div><dt>Saldo a favor aplicado</dt><dd>− {formatearMoneda(saldoAplicado)}</dd></div>
                    <div><dt>Pagos registrados computables</dt><dd>{formatearMoneda(resumenServidor?.total_pagado || 0)}</dd></div>
                    {Number(resumenServidor?.excedente_generado || anticipo.excedente_generado || 0) > 0 && <div><dt>Excedente del pedido</dt><dd>{formatearMoneda(resumenServidor?.excedente_generado || anticipo.excedente_generado)}</dd></div>}
                    <div className="registrar-tienda-importes-pendiente"><dt>{pagoEnPdv ? 'Pago al recoger' : 'Importe pendiente por pagar'}</dt><dd>{pagoEnPdv ? 'Se paga en tienda' : formatearMoneda(pendiente)}</dd></div>
                </dl>
            </section>
            <section className="registrar-tienda-panel" aria-label="Pagos y confirmación">
                <div className="grid gap-3">
                    {tipoGuardado === 'credito' && (
                        <p className="text-sm theme-text-main m-0">
                            {solicitud?.credito_wizerp?.resultado === 'vencido'
                                ? (solicitud.credito_wizerp.causa || 'El cliente tiene un crédito vencido y necesita liquidarlo.')
                                : `Crédito${solicitud?.credito_wizerp?.resultado ? `: ${solicitud.credito_wizerp.resultado}` : ''}. La gestión vigente no pide una exhibición, un banco ni un comprobante de pago.`}
                        </p>
                    )}
                    {reporte && (
                        <p className="text-sm theme-text-main m-0" role="status">
                            {atencionMd?.motivo || reporte.motivo || (reporte.oportuno
                                ? 'Reporte oportuno. Complete la verificación del ingreso y la remisión.'
                                : 'El reporte quedó pendiente de acuerdo. No se reprograma solo.')}
                        </p>
                    )}
                    {!importesListos && editable && tipoGuardado === 'credito' && !(Number(totalMercancia) > 0) && (
                        <p className="text-sm theme-text-muted m-0">El total a cubrir no está completo.</p>
                    )}
                    {!importesListos && editable && tipoGuardado !== 'credito' && (
                        <p className="text-sm theme-text-muted m-0">El pago espera a que los importes queden guardados.</p>
                    )}
                    {mostrarHistorial && (
                        <SeccionPagosExhibicion
                            pedidoId={pedidoId}
                            bancos={bancos}
                            puedeRegistrar={mostrarCaptura && importesListos}
                            ocultarSiVacio={tipoGuardado === 'apartado'}
                            ocultarEncabezado
                            ocultarResumen
                            etiquetaCubrir="Cubrir pendiente"
                            capturaBloqueada={!importesListos}
                            destinoCajaPdv={tipoGuardado === 'apartado'}
                            formasPermitidas={tipoGuardado === 'apartado' ? MEDIOS_TIENDA : null}
                            formaPagoFija={tipoGuardado === 'transferencia' ? 'transferencia' : null}
                            formaPagoInicial={tipoGuardado === 'transferencia' ? 'transferencia' : 'efectivo'}
                            totalMercancia={totalMercancia === '' ? null : totalMercancia}
                            costoEnvio={costos.costo_envio || 0}
                            aplicaSeguro={Boolean(costos.aplica_seguro)}
                            costoSeguro={costos.costo_seguro || 0}
                            saldoAFavorAplicado={saldoAplicado}
                            envioEsBase
                            costoReexpedicion={0}
                            revisionEsperada={revisionEsperada}
                            revisionResumen={tipoGuardado === 'transferencia' ? revisionEsperada : null}
                            totalMercanciaConfirmado={totalMercanciaConfirmado}
                            saldoAFavorConfirmado={saldoAFavorConfirmado}
                            onLectura={(json) => {
                                setResumenServidor(json?.resumen || null);
                                if (tipoGuardado === 'transferencia') {
                                    setCoberturaGuardada({ pedidoId, revision: json?.revision_registro ?? null, resumen: json?.resumen });
                                }
                                setAtencionMd(json?.atencion_md || null);
                                onLectura?.(json);
                            }}
                            onResumenChange={(resumen) => setResumenServidor(resumen)}
                        />
                    )}
                    {tipoGuardado === 'apartado' && solicitud.area_revision === 'preparacion_tienda' && (
                        <div className="border-t theme-border pt-3 grid gap-2">
                            <h4 className="text-sm font-semibold theme-text-main m-0">Confirmación del pedido</h4>
                            {confirmacionEnviada ? <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--exito justify-self-start">Enviada a tienda</span> : <>
                                {horariosActivos && (bloqueoConfirmacion || solicitud.horarios_operativos?.detalle_apartado) && <div role="alert" className="flex items-start gap-3 rounded-xl border border-[color-mix(in_srgb,var(--color-aviso)_40%,transparent)] bg-[color-mix(in_srgb,var(--color-aviso)_10%,transparent)] p-4">
                                    <TriangleAlert size={20} className="shrink-0 theme-text-aviso mt-0.5" aria-hidden="true" />
                                    <div className="min-w-0 text-sm theme-text-main"><p className="font-semibold m-0">{bloqueoConfirmacion ? 'Confirmación fuera de horario' : 'Horario de confirmación'}</p>
                                        <p className="mt-1 mb-0 break-words">{bloqueoConfirmacion || solicitud.horarios_operativos.detalle_apartado}</p>
                                    </div>
                                </div>}
                                <AccionPrincipalTienda destino={destinoAccion}><button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] justify-self-start`} disabled={!procesosListos || Boolean(bloqueoConfirmacion)} onClick={confirmarPedido}>{ocupado ? 'Enviando…' : 'Enviar confirmación a tienda'}</button></AccionPrincipalTienda>
                            </>}
                        </div>
                    )}
                </div>
            </section>
        </>
    );
}
