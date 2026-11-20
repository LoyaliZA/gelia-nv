import React, { useEffect, useRef, useState } from 'react';
import { Package } from 'lucide-react';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';
import ConjuntoRemisionesMunicipal from './ConjuntoRemisionesMunicipal';
import { MECANISMOS_REMISION } from './estadosResguardoMunicipal';

const ESPERA_BUSQUEDA_MS = 300;

/**
 * Ventas consulta, selecciona y vincula remisiones pagadas. La confirmación física (recepción,
 * integración, bolsas juntas) vive en la superficie de CEDIS o de la sucursal, no aquí.
 */
export default function RemisionesPagadasMunicipal({ pedido, ocupado = false, onPedidoActualizado, onAviso }) {
    const [q, setQ] = useState('');
    const [candidatos, setCandidatos] = useState([]);
    const [origenId, setOrigenId] = useState('');
    const [mecanismo, setMecanismo] = useState('');
    const [cargandoLista, setCargandoLista] = useState(false);
    const [errorLista, setErrorLista] = useState('');
    const [cargando, setCargando] = useState(false);
    const peticion = useRef(0);
    const abortar = useRef(null);
    const vinculos = pedido?.resguardo_envio?.vinculos || [];
    const caps = pedido?.resguardo_envio?.capacidades || {};
    const puedeVincular = Boolean(caps.puede_vincular);
    const resguardoActual = pedido?.resguardo_envio || {};
    // Cerrado o ya salido: la lista se protege y se explica por qué, sin ocultar las operaciones físicas pendientes.
    const avisoConjunto = resguardoActual.envio_salio
        ? 'El envío ya salió: no se agregan ni se quitan remisiones. La salida registrada no se modifica.'
        : resguardoActual.conjunto_cerrado
            ? 'El cliente ya definió el conjunto al cerrar la acumulación. No se agregan ni se quitan remisiones por este medio; las confirmaciones físicas pendientes continúan.'
            : '';
    const candidato = candidatos.find((item) => String(item.id) === String(origenId));
    const mecanismosPosibles = candidato?.mecanismos || [];
    const sinMercanciaNueva = Number(pedido?.total_mercancia || 0) === 0 && vinculos.length > 0;

    const cargar = async (texto) => {
        if (!pedido?.id) return;
        abortar.current?.abort();
        const controlador = new AbortController();
        abortar.current = controlador;
        const turno = ++peticion.current;
        setCargandoLista(true);
        setErrorLista('');
        try {
            const { data } = await window.axios.get(route('control_pedidos.municipal.remisiones', pedido.id), {
                params: { q: texto },
                headers: { Accept: 'application/json' },
                signal: controlador.signal,
            });
            // Una respuesta anterior nunca pisa a una más reciente.
            if (turno === peticion.current) setCandidatos(data.data || []);
        } catch (error) {
            if (turno !== peticion.current || error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') return;
            setErrorLista(error.response?.data?.message || 'No se pudo consultar las remisiones. Intente de nuevo.');
        } finally {
            if (turno === peticion.current) setCargandoLista(false);
        }
    };

    useEffect(() => {
        const espera = setTimeout(() => { cargar(q); }, q === '' ? 0 : ESPERA_BUSQUEDA_MS);
        return () => clearTimeout(espera);
        // Recarga al cambiar de pedido, de búsqueda o de vínculos persistidos.
    }, [pedido?.id, q, vinculos.length]);

    useEffect(() => () => { abortar.current?.abort(); peticion.current += 1; }, []);

    useEffect(() => {
        // Si la remisión elegida deja de ser posible, se conserva la selección solo cuando sigue en la lista.
        if (origenId && !candidato) return;
        if (candidato && !mecanismosPosibles.includes(mecanismo)) setMecanismo(mecanismosPosibles[0] || '');
    }, [origenId, candidato]);

    const vincular = async () => {
        if (!origenId || !mecanismo || ocupado || cargando) return;
        setCargando(true);
        try {
            const { data } = await window.axios.post(route('control_pedidos.municipal.remisiones.vincular', pedido.id), {
                origen_id: Number(origenId),
                mecanismo,
            }, { headers: { Accept: 'application/json' } });
            onPedidoActualizado?.({ ...pedido, ...data });
            setOrigenId('');
            setMecanismo('');
            onAviso?.('Remisión vinculada. Falta su confirmación física; no se cobra de nuevo.');
        } catch (error) {
            // La selección se conserva para reintentar.
            onAviso?.(error.response?.data?.message || error.response?.data?.errors?.remision?.[0] || 'No se pudo vincular la remisión.');
        } finally {
            setCargando(false);
        }
    };

    const desvincular = async (origen) => {
        if (ocupado || cargando || !origen.puede_desvincular) return;
        setCargando(true);
        try {
            const { data } = await window.axios.delete(route('control_pedidos.municipal.remisiones.desvincular', [pedido.id, origen.origen_id]), {
                headers: { Accept: 'application/json' },
            });
            onPedidoActualizado?.({ ...pedido, ...data });
            onAviso?.('Remisión desvinculada. Vuelve a estar disponible.');
        } catch (error) {
            onAviso?.(error.response?.data?.message || 'No se pudo desvincular la remisión.');
        } finally {
            setCargando(false);
        }
    };

    return (
        <section className="registrar-tienda-panel" aria-label="Remisiones pagadas">
            <div className="registrar-tienda-seccion-titulo"><Package size={18} aria-hidden="true" /><h3>Remisiones pagadas</h3></div>
            <p className="text-xs theme-text-muted mt-0 mb-3">Incorpore compras anteriores del mismo cliente. Vincular no activa el resguardo ni vuelve a cobrar lo ya pagado; la integración física la confirma el área que tiene la mercancía.</p>
            {sinMercanciaNueva && (
                <p className="text-sm theme-text-main mt-0 mb-3" role="status">Este envío no tiene mercancía nueva: solo incorpora remisiones ya pagadas, y no hay nada nuevo por cobrar.</p>
            )}
            {avisoConjunto && <p className="text-sm theme-text-main mt-0 mb-3" role="status" data-conjunto-protegido>{avisoConjunto}</p>}
            <div className="mb-4">
                <ConjuntoRemisionesMunicipal
                    vinculos={vinculos}
                    acciones={(item) => (item.puede_desvincular ? (
                        <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={ocupado || cargando} onClick={() => desvincular(item)}>Desvincular</button>
                    ) : null)}
                />
            </div>
            {puedeVincular && (
                <fieldset disabled={ocupado || cargando} className="grid sm:grid-cols-2 gap-4 min-w-0">
                    <label className={THEME_LABEL}>Buscar remisión
                        <input className={`${THEME_INPUT} w-full mt-1`} value={q} onChange={(evento) => setQ(evento.target.value)} aria-describedby="remisiones-estado-lista" />
                    </label>
                    <label className={THEME_LABEL}>Remisión
                        <select className={`${THEME_SELECT} w-full mt-1`} value={origenId} onChange={(evento) => setOrigenId(evento.target.value)}>
                            <option value="">Elegir remisión</option>
                            {candidatos.map((item) => (
                                <option key={item.id} value={item.id}>{item.folio} · {item.numero_remision || item.folio_remision}</option>
                            ))}
                        </select>
                    </label>
                    <label className={THEME_LABEL}>Cómo se integra
                        <select className={`${THEME_SELECT} w-full mt-1`} value={mecanismo} disabled={!candidato} onChange={(evento) => setMecanismo(evento.target.value)}>
                            <option value="">{candidato ? 'Elegir cómo se integra' : 'Elija primero la remisión'}</option>
                            {mecanismosPosibles.map((id) => <option key={id} value={id}>{MECANISMOS_REMISION[id] || id}</option>)}
                        </select>
                    </label>
                    <div className="self-end">
                        <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} disabled={!origenId || !mecanismo || ocupado || cargando} onClick={vincular}>Vincular remisión</button>
                    </div>
                    <p id="remisiones-estado-lista" className="text-xs theme-text-muted m-0 sm:col-span-2" role="status" aria-live="polite">
                        {cargandoLista ? 'Buscando remisiones…'
                            : errorLista ? errorLista
                                : candidatos.length === 0 ? (q ? 'Ninguna remisión coincide. Solo se listan compras pagadas y auditadas de este cliente, sin envío propio.' : 'Este cliente no tiene remisiones pagadas disponibles para incorporar.')
                                    : candidato && candidato.mecanismos?.length === 1 && candidato.mecanismos[0] === 'resguardo_envio' ? 'Esa mercancía ya está en CEDIS: CEDIS confirma su integración. No hay traslado.'
                                        : candidato ? 'Esa mercancía está en custodia de una sucursal: se integra allí o se traslada a CEDIS.'
                                            : `${candidatos.length} remisiones disponibles.`}
                    </p>
                </fieldset>
            )}
        </section>
    );
}
