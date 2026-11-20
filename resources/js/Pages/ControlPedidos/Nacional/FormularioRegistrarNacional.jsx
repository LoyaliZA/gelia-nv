import React, { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import SeguimientoSolicitudNacional from './SeguimientoSolicitudNacional';
import { THEME_BTN_PRIMARY, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';

function paqueteriasNacionales(catalogo = []) {
    return catalogo.filter((item) => item?.activo !== false
        && item?.categoria === 'comercial'
        && item?.requiere_peso
        && item?.nombre !== 'PAQ. PENDIENTE');
}

function paqueteriasPosteriores(catalogo = []) {
    return catalogo.filter((item) => item?.activo !== false && (
        item?.nombre === 'PAQ. PENDIENTE'
        || (item?.categoria === 'comercial' && item?.requiere_peso)
    ));
}

function mensajeDe(error, reserva) {
    const datos = error?.response?.data;
    const lista = datos?.errors ? Object.values(datos.errors).flat() : [];

    return lista[0] || datos?.message || reserva;
}

export default function FormularioRegistrarNacional({ pedido, catalogos = {}, guardar, onPedido, children }) {
    const [actual, setActual] = useState(pedido);
    const [paqueteriaId, setPaqueteriaId] = useState(String(pedido?.catalogo_paqueteria_id || ''));
    const [tocada, setTocada] = useState(false);
    const [error, setError] = useState('');
    const [enviando, setEnviando] = useState(false);
    const [paqueteriaPosterior, setPaqueteriaPosterior] = useState('');
    useEffect(() => { setActual(pedido); }, [pedido]);
    const opciones = useMemo(() => paqueteriasNacionales(catalogos.paqueterias), [catalogos.paqueterias]);
    const posteriores = useMemo(() => paqueteriasPosteriores(catalogos.paqueterias), [catalogos.paqueterias]);
    const enviada = Boolean(actual?.pesaje_solicitado_at);
    const cerrada = Boolean(actual?.consulta_cerrada_at);
    const elegida = opciones.find((item) => String(item.id) === String(actual?.catalogo_paqueteria_id || paqueteriaId));

    const motivoEnvio = () => {
        if (!String(actual?.folio_remision || '').trim()) return 'Indique el folio del pedido.';
        const tieneArchivo = (actual?.documentos || []).some((documento) => documento?.url || documento?.id);
        if (!tieneArchivo) return 'Adjunte el PDF del pedido.';
        if (!opciones.length) return 'No hay paquetería comercial con peso disponible.';
        if (!paqueteriaId) return 'Elija la paquetería comercial.';

        return '';
    };

    const enviar = async () => {
        if (enviando) return;
        const motivo = motivoEnvio();
        setTocada(true);
        if (motivo) {
            setError(motivo);

            return;
        }
        setEnviando(true);
        setError('');
        try {
            const guardado = await guardar?.({ catalogo_paqueteria_id: Number(paqueteriaId) });
            const lectura = guardado?.id ? guardado : actual;
            if (!lectura?.id) {
                setError('Guarde el pedido antes de enviar la consulta.');

                return;
            }
            const { data } = await axios.post(`/control-pedidos/${lectura.id}/solicitar-pesaje`, {
                revision_esperada: lectura.revision_registro,
                base_captura: lectura.base_captura,
            }, { headers: { Accept: 'application/json' } });
            const siguiente = { ...lectura, ...data, documentos: lectura.documentos || actual?.documentos || [] };
            setActual(siguiente);
            onPedido?.(siguiente);
        } catch (fallo) {
            setError(mensajeDe(fallo, 'No se envió la consulta. Intente de nuevo.'));
        } finally {
            setEnviando(false);
        }
    };

    const actualizarPaqueteria = async () => {
        if (enviando) return;
        setTocada(true);
        if (!paqueteriaPosterior) {
            setError('Elija la paquetería.');

            return;
        }
        setEnviando(true);
        setError('');
        try {
            const { data } = await axios.post(`/control-pedidos/${actual.id}/paqueteria-nacional`, {
                catalogo_paqueteria_id: Number(paqueteriaPosterior),
                solicitud_id: crypto.randomUUID(),
                revision_esperada: actual.revision_registro,
                base_captura: actual.base_captura,
            }, { headers: { Accept: 'application/json' } });
            const siguiente = { ...actual, ...data, catalogo_paqueteria_id: Number(paqueteriaPosterior) };
            setActual(siguiente);
            onPedido?.(siguiente);
        } catch (fallo) {
            setError(mensajeDe(fallo, 'No se actualizó la paquetería. Intente de nuevo.'));
        } finally {
            setEnviando(false);
        }
    };

    const contenido = (
        <section className="registrar-tienda-panel space-y-3" aria-labelledby="envio-nacional-titulo">
            <h3 id="envio-nacional-titulo" className="m-0 text-base">Envío nacional</h3>
            <p className="text-sm theme-text-main m-0">Salida: CEDIS</p>
            {enviada ? (
                <p className="text-sm theme-text-main m-0">Paquetería: {elegida?.nombre || 'Comercial con peso'}</p>
            ) : (
                <div className="min-w-0">
                    <label className={THEME_LABEL} htmlFor="paqueteria-nacional">Paquetería</label>
                    <select
                        id="paqueteria-nacional"
                        className={`${THEME_SELECT} w-full min-h-[44px] text-base mt-1`}
                        value={paqueteriaId}
                        aria-invalid={tocada && !paqueteriaId ? 'true' : undefined}
                        aria-describedby={error ? 'paqueteria-nacional-error' : undefined}
                        onBlur={() => {
                            setTocada(true);
                            if (!paqueteriaId) setError(opciones.length ? 'Elija la paquetería comercial.' : 'No hay paquetería comercial con peso disponible.');
                        }}
                        onFocus={() => setError('')}
                        onChange={(evento) => {
                            setPaqueteriaId(evento.target.value);
                            setError('');
                        }}
                    >
                        <option value="">Elegir</option>
                        {opciones.map((item) => (
                            <option key={item.id} value={String(item.id)}>{item.nombre}</option>
                        ))}
                    </select>
                    {error && <p id="paqueteria-nacional-error" className="text-sm theme-text-aviso m-0 mt-1" role="alert">{error}</p>}
                </div>
            )}
            {cerrada && (
                <div className="min-w-0">
                    <label className={THEME_LABEL} htmlFor="paqueteria-posterior">Paquetería</label>
                    <p className="text-sm theme-text-main m-0 mt-1">PAQ. PENDIENTE espera la elección. No asigna guía.</p>
                    <select
                        id="paqueteria-posterior"
                        className={`${THEME_SELECT} w-full min-h-[44px] text-base mt-1`}
                        value={paqueteriaPosterior}
                        aria-invalid={tocada && !paqueteriaPosterior ? 'true' : undefined}
                        onBlur={() => {
                            setTocada(true);
                            if (!paqueteriaPosterior) setError('Elija la paquetería.');
                        }}
                        onFocus={() => setError('')}
                        onChange={(evento) => {
                            setPaqueteriaPosterior(evento.target.value);
                            setError('');
                        }}
                    >
                        <option value="">Elegir</option>
                        {posteriores.map((item) => (
                            <option key={item.id} value={String(item.id)}>{item.nombre}</option>
                        ))}
                    </select>
                    <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] mt-2`} onClick={actualizarPaqueteria}>
                        {enviando ? 'Actualizando…' : 'Actualizar paquetería'}
                    </button>
                    {error && <p className="text-sm theme-text-aviso m-0 mt-1" role="alert">{error}</p>}
                </div>
            )}
            {enviada && (
                <SeguimientoSolicitudNacional
                    pedido={actual}
                    onPedidoActualizado={(lectura) => {
                        const siguiente = { ...actual, ...lectura };
                        setActual(siguiente);
                        onPedido?.(siguiente);
                    }}
                />
            )}
        </section>
    );

    const pie = enviada ? null : (
        <div className="registrar-tienda-pie-acciones">
            <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} onClick={enviar}>
                {enviando ? 'Enviando…' : 'Enviar consulta a CEDIS'}
            </button>
        </div>
    );

    return children({ contenido, pie });
}
