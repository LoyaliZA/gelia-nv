import React, { useState } from 'react';
import axios from 'axios';
import { Layers } from 'lucide-react';
import { AjustePiezas } from '../Partials/AjustePiezas';
import { adaptadorConsultaNacional } from '../Partials/adaptadoresConsulta';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import { LABELS_ESTADO_FISICO } from '../Partials/pedidosBmaStyles';
import { vinculoFisicoNacional } from './superficieNacional';

function mensajeDe(error, reserva) {
    const datos = error?.response?.data;
    const lista = datos?.errors ? Object.values(datos.errors).flat() : [];

    return lista[0] || datos?.message || reserva;
}

export default function SeguimientoSolicitudNacional({ pedido, onPedidoActualizado, onVer, referenciaMercancia }) {
    const [aviso, setAviso] = useState('');
    const [error, setError] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const actualizando = Boolean(pedido?.consulta_actualizacion_pendiente);
    const respondida = Boolean(pedido?.pesaje_respondido_at) && !actualizando;
    const cerrada = Boolean(pedido?.consulta_cerrada || pedido?.consulta_cerrada_at);
    const adaptador = adaptadorConsultaNacional({ pedidoId: pedido?.id, pedido });
    const puedeAjustar = !cerrada && (adaptador.capacidades.anexar || adaptador.capacidades.retirar);
    const puedeCerrar = !cerrada && Boolean(pedido?.puede_cerrar_consulta);
    const documentosRespuesta = (pedido?.documentos || []).filter((doc) => doc.tipo === 'evidencia_condicion'
        && doc.activo !== false && doc.url && ['revision_producto', 'envio_caja', 'revision_general'].includes(doc.relacion_tipo));
    const vinculo = vinculoFisicoNacional(pedido);
    const puedeRegistrarSalida = Boolean(vinculo && vinculo.vinculo.estado === 'vinculado');

    const refrescar = async () => {
        const { data } = await axios.get(`/control-pedidos/${pedido.id}/consulta-nacional`, {
            headers: { Accept: 'application/json' },
        });
        onPedidoActualizado?.(data);

        return data;
    };

    const ejecutar = async (accion) => {
        if (ocupado) return;
        setOcupado(true);
        setError('');
        try {
            await accion();
        } catch (fallo) {
            setError(mensajeDe(fallo, 'No se completó la operación. Intente de nuevo.'));
        } finally {
            setOcupado(false);
        }
    };

    return (
        <>
        <section className="registrar-tienda-panel space-y-3" aria-label="Consulta a CEDIS">
            <h3 className="m-0 text-base">Consulta a CEDIS</h3>
            {!respondida && !cerrada && (
                <p className="text-sm theme-text-main m-0">
                    {actualizando ? 'CEDIS tiene pendiente actualizar la disponibilidad y el peso.' : 'Consulta enviada. CEDIS tiene pendiente confirmar disponibilidad y peso.'}
                </p>
            )}
            {respondida && (
                <>
                    <p className="text-sm theme-text-main m-0">
                        {(pedido.revisiones_producto || []).length
                            ? `${pedido.revisiones_producto.length} unidad${pedido.revisiones_producto.length === 1 ? '' : 'es'} en la respuesta.`
                            : 'CEDIS respondió la consulta.'}
                        {pedido.numero_cajas ? ` ${pedido.numero_cajas} caja${Number(pedido.numero_cajas) === 1 ? '' : 's'}.` : ''}
                        {pedido.peso_real_kg != null && ` Peso real ${Number(pedido.peso_real_kg)} kg.`}
                        {pedido.peso_cobrado_guia_kg != null && ` Peso a cobrar ${Number(pedido.peso_cobrado_guia_kg)} kg.`}
                    </p>
                    <ul className="m-0 pl-5 text-sm theme-text-main">
                        {(pedido.revisiones_producto || []).map((pieza) => (
                            <li className="break-words" key={pieza.id}>
                                {pieza.sku ? `${pieza.sku} · ` : ''}{pieza.descripcion_producto}
                                {pieza.cantidad_solicitada != null && ` · ${pieza.cantidad_encontrada ?? '—'} de ${pieza.cantidad_solicitada}`}
                                {LABELS_ESTADO_FISICO[pieza.estado_fisico] && ` · ${LABELS_ESTADO_FISICO[pieza.estado_fisico]}`}
                                {pieza.comentario && <span className="block theme-text-muted">{pieza.comentario}</span>}
                            </li>
                        ))}
                    </ul>
                    {documentosRespuesta.length > 0 && onVer && (
                        <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} onClick={() => onVer(documentosRespuesta, 0)}>
                            Ver evidencias de CEDIS
                        </button>
                    )}
                </>
            )}
            {cerrada && <p className="text-sm theme-text-main m-0">Ventas registró el cierre de la consulta.</p>}
            {aviso && <p className="text-sm theme-text-main m-0" role="status">{aviso}</p>}
            {error && <p className="text-sm theme-text-aviso m-0" role="alert">{error}</p>}

            <div className="flex flex-col sm:flex-row gap-2">
                <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} onClick={() => ejecutar(refrescar)}>
                    {ocupado ? 'Actualizando…' : 'Actualizar consulta'}
                </button>
                {puedeCerrar && (
                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} min-h-[44px]`}
                        onClick={() => ejecutar(async () => {
                            const version = Number(pedido?.version_consulta);
                            await axios.post(`/control-pedidos/${pedido.id}/cerrar-consulta`, {
                                modalidad_esperada: 'nacional',
                                ...(Number.isInteger(version) && version > 0 ? { version_consulta: version } : {}),
                            }, { headers: { Accept: 'application/json' } });
                            await refrescar();
                        })}
                    >
                        {ocupado ? 'Cerrando…' : 'Cerrar consulta'}
                    </button>
                )}
                {puedeRegistrarSalida && (
                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} min-h-[44px]`}
                        onClick={() => ejecutar(async () => {
                            await axios.post(`/control-pedidos/${vinculo.pedidoId}/custodia-nacional`, {
                                accion: 'salida',
                            }, { headers: { Accept: 'application/json' } });
                            await refrescar();
                        })}
                    >
                        {ocupado ? 'Registrando…' : 'Registrar salida'}
                    </button>
                )}
            </div>
        </section>
        {respondida && (
            <section className="registrar-tienda-panel space-y-3" aria-labelledby="mercancia-nacional-titulo">
                <div className="registrar-tienda-seccion-titulo">
                    <Layers size={18} aria-hidden="true" />
                    <h3 id="mercancia-nacional-titulo">Mercancía</h3>
                </div>
                {referenciaMercancia}
                {puedeAjustar && (
                    <AjustePiezas
                        pedidoId={pedido.id}
                        pedido={pedido}
                        solicitud={pedido.solicitud_preparacion || {}}
                        adaptador={adaptador}
                        avisoAnexo="Anexo registrado. CEDIS debe responder el nuevo peso antes de cerrar."
                        avisoRetiro="Retiro registrado. CEDIS debe responder el nuevo peso antes de cerrar."
                        onVer={onVer}
                        onSolicitud={(_, version, lectura) => lectura ? onPedidoActualizado?.(lectura) : ejecutar(refrescar)}
                        onAviso={setAviso}
                    />
                )}
            </section>
        )}
        </>
    );
}
