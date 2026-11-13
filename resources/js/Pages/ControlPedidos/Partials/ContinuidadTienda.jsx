import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';
import ModalMovimientosTienda from './ModalMovimientosTienda';
import ImportesPagoTienda from './ImportesPagoTienda';
import { enviarEnLotes } from './lotesAdjuntosConsulta';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';

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

export default function ContinuidadTienda({
    pedidoId,
    solicitud = {},
    sucursales = [],
    notaMismoDia,
    puedeResolverTraslado = false,
    puedeOperarTienda = false,
    puedeCedis = false,
    siguiente = '',
    mostrarCustodia = false,
    consultaCerrada = false,
    importes = null,
}) {
    const [movimiento, setMovimiento] = useState(false);
    const [aviso, setAviso] = useState(siguiente);
    const [ocupado, setOcupado] = useState(false);
    const [solicitudLocal, setSolicitudLocal] = useState(solicitud || {});
    const [principalId, setPrincipalId] = useState('');
    const [anexoId, setAnexoId] = useState('');
    const [sku, setSku] = useState('');
    const [descripcion, setDescripcion] = useState('');
    const [cantidad, setCantidad] = useState('1');
    const [condicion, setCondicion] = useState('');
    const [evidencias, setEvidencias] = useState([]);
    useEffect(() => { setSolicitudLocal(solicitud || {}); }, [pedidoId, solicitud]);
    const revision = solicitudLocal?.revision_disponibilidad?.estado;
    const aceptacionVigente = solicitudLocal?.revision_disponibilidad?.aceptacion_vigente !== false && solicitudLocal?.revision_disponibilidad?.vigente !== false && !solicitudLocal?.revision_disponibilidad?.sustituida_at;
    const retiroPendiente = (solicitudLocal?.retiro_piezas || []).some((retiro) => (retiro?.estado || 'pendiente') === 'pendiente');
    const anexoPendiente = (solicitudLocal?.anexo_sin_remision || []).some((anexo) => !anexo?.respondida_at);
    const cierreVigente = consultaCerrada && aceptacionVigente && !retiroPendiente && !anexoPendiente;
    const puedeCobertura = cierreVigente && (revision === 'sin_excepciones' || revision === 'excepciones_pendientes' || revision === 'decision_registrada');

    const postSimple = async (url, exito) => {
        setOcupado(true);
        setAviso('');
        try {
            await axios.post(url, {}, { headers: cabeceras() });
            setAviso(exito);
        } catch (error) {
            setAviso(mensajeDe(error, 'No se completó la acción.'));
        } finally {
            setOcupado(false);
        }
    };

    const enviarCedis = () => postSimple(`/control-pedidos/${pedidoId}/retiro-custodia-envio/trasladar`, 'Bolsas en camino a CEDIS. El envío principal espera la recepción.');
    const recibirCedis = () => postSimple(`/control-pedidos/${pedidoId}/retiro-custodia-envio/recibir-cedis`, 'CEDIS recibió las bolsas e integró el complemento.');
    const integrarAnexo = () => postSimple(`/control-pedidos/${anexoId}/anexo-tienda/integrar`, 'Integración física confirmada.');

    const retirarCustodia = async () => {
        setOcupado(true);
        setAviso('');
        try {
            await axios.post(
                `/control-pedidos/${pedidoId}/retiro-custodia-envio`,
                { pedido_principal_id: Number(principalId) },
                { headers: cabeceras() },
            );
            setAviso('La custodia quedó pendiente de traslado a CEDIS. No se cobró de nuevo ni se confirmó la recepción.');
        } catch (error) {
            setAviso(mensajeDe(error, 'No se retiró la custodia.'));
        } finally {
            setOcupado(false);
        }
    };

    const anexar = async () => {
        if (evidencias.length === 0) {
            setAviso('Adjunte la evidencia del anexo.');
            return;
        }
        setOcupado(true);
        setAviso('');
        const solicitudId = uuidSolicitud();
        try {
            const respuesta = await enviarEnLotes({
                url: `/control-pedidos/${pedidoId}/anexo-tienda`,
                archivos: evidencias,
                headers: cabeceras(),
                post: (url, form, opciones) => axios.post(url, form, opciones),
                armar: (lote) => {
                    const form = new FormData();
                    form.append('solicitud_id', solicitudId);
                    form.append('sku', sku);
                    form.append('descripcion', descripcion);
                    form.append('cantidad', cantidad);
                    form.append('condicion', condicion);
                    lote.forEach((archivo) => form.append('evidencias[]', archivo));
                    if (lote[0]) form.append('evidencia', lote[0]);
                    return form;
                },
            });
            setAviso(
                respuesta.data?.folio
                    ? `Anexo registrado en ${respuesta.data.folio}. Falta su cobertura y la integración física.`
                    : 'Anexo documentado.',
            );
        } catch (error) {
            setAviso(mensajeDe(error, 'No se registró el anexo.'));
        } finally {
            setOcupado(false);
        }
    };

    const hayContenido = Boolean(aviso) || puedeCobertura || importes?.conservar || (cierreVigente && mostrarCustodia);
    if (!hayContenido) return null;

    return (
        <div className="mt-4 grid gap-4">
            {aviso && (
                <p className="text-sm theme-text-main m-0 break-words" role="status">
                    {aviso}
                </p>
            )}
            {(puedeCobertura || importes?.conservar) && (
                <ImportesPagoTienda
                    {...(importes || {})}
                    pedidoId={pedidoId}
                    solicitud={solicitudLocal}
                    onSolicitud={(siguienteSolicitud) => {
                        setSolicitudLocal(siguienteSolicitud || {});
                        importes?.onSolicitud?.(siguienteSolicitud);
                    }}
                    notaMismoDia={notaMismoDia}
                    editable={puedeCobertura && importes?.lecturaLista !== false}
                />
            )}
            {cierreVigente && mostrarCustodia && <section className="rounded-xl border theme-border theme-element p-4 grid gap-3">
                <h3 className="text-sm font-semibold theme-text-main m-0">Sucursal, anexo y custodia</h3>
                <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} disabled={ocupado} onClick={() => setMovimiento(true)}>Traslado entre sucursales</button>
                <ModalMovimientosTienda abierto={movimiento} pedido={{ id: pedidoId, folio: `Pedido ${pedidoId}` }} onClose={() => setMovimiento(false)} />
                <label className={THEME_LABEL} htmlFor="principal-retiro">Pedido de envío que recibe la custodia</label>
                <input id="principal-retiro" className={THEME_INPUT} inputMode="numeric" placeholder="Identificador del pedido de envío" value={principalId} onChange={(evento) => setPrincipalId(evento.target.value)} />
                <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} disabled={ocupado || !principalId} onClick={retirarCustodia}>
                    Retirar de recolección
                </button>
                {puedeOperarTienda && (
                    <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} disabled={ocupado} onClick={enviarCedis}>
                        Enviar bolsas a CEDIS
                    </button>
                )}
                {puedeCedis && (
                    <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} disabled={ocupado} onClick={recibirCedis}>
                        Confirmar recepción en CEDIS
                    </button>
                )}
                <label className={THEME_LABEL} htmlFor="anexo-integrar">Identificador del anexo a integrar</label>
                <input id="anexo-integrar" className={THEME_INPUT} inputMode="numeric" value={anexoId} onChange={(evento) => setAnexoId(evento.target.value)} />
                {puedeOperarTienda && (
                    <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} disabled={ocupado || !anexoId} onClick={integrarAnexo}>
                        Confirmar integración física
                    </button>
                )}
                <div className="grid gap-2 sm:grid-cols-2">
                    <input className={THEME_INPUT} placeholder="SKU" value={sku} onChange={(evento) => setSku(evento.target.value)} />
                    <input className={THEME_INPUT} placeholder="Descripción" value={descripcion} onChange={(evento) => setDescripcion(evento.target.value)} />
                    <input className={THEME_INPUT} type="number" min="1" value={cantidad} onChange={(evento) => setCantidad(evento.target.value)} />
                    <input className={THEME_INPUT} placeholder="Condición consultada" value={condicion} onChange={(evento) => setCondicion(evento.target.value)} />
                </div>
                <input
                    type="file"
                    multiple
                    accept="application/pdf,image/jpeg,image/png,image/webp"
                    onChange={(evento) => setEvidencias(Array.from(evento.target.files || []))}
                />
                <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} disabled={ocupado} onClick={anexar}>
                    Solicitar anexo
                </button>
            </section>}
        </div>
    );
}
