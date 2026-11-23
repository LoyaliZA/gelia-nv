import React, { useEffect, useRef, useState } from 'react';
import { Package } from 'lucide-react';
import SeccionPlegableMunicipal from './SeccionPlegableMunicipal';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL } from '../../../utils/geliaTheme';
import ConjuntoRemisionesMunicipal from './ConjuntoRemisionesMunicipal';

const ESPERA_BUSQUEDA_MS = 300;

/**
 * Ventas consulta, selecciona y vincula remisiones pagadas. La confirmación física (recepción,
 * integración, bolsas juntas) vive en la superficie de CEDIS o de la sucursal, no aquí.
 */
export default function RemisionesPagadasMunicipal({ pedido, ocupado = false, onPedidoActualizado, onAviso }) {
    const [q, setQ] = useState('');
    const [lista, setLista] = useState(false);
    const [candidatos, setCandidatos] = useState([]);
    const [origenId, setOrigenId] = useState('');
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
    // Lo ya vinculado deja de ofrecerse: solo quedan las remisiones por incorporar.
    const idsVinculados = new Set(vinculos.map((item) => String(item.origen_id)));
    const disponibles = candidatos.filter((item) => !idsVinculados.has(String(item.id)));
    const etiquetaRemision = (item) => `${item.folio} · ${item.numero_remision || item.folio_remision}`;
    const candidato = disponibles.find((item) => String(item.id) === String(origenId));
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

    const vincular = async () => {
        if (!origenId || ocupado || cargando) return;
        setCargando(true);
        try {
            const { data } = await window.axios.post(route('control_pedidos.municipal.remisiones.vincular', pedido.id), {
                origen_id: Number(origenId),
            }, { headers: { Accept: 'application/json' } });
            onPedidoActualizado?.({ ...pedido, ...data });
            setOrigenId('');
            onAviso?.('Pedido anexado. Se avisó al área que lo tiene para confirmarlo o trasladarlo; no se cobra de nuevo.');
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
        <SeccionPlegableMunicipal titulo="Anexar pedidos pagados" icono={Package} abiertaInicial={vinculos.length > 0} resumen={vinculos.length > 0 ? `${vinculos.length} anexados` : null}>
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
                    <div className={`${THEME_LABEL} relative`} onBlur={(evento) => { if (!evento.currentTarget.contains(evento.relatedTarget)) setLista(false); }}>
                        <label htmlFor="remision-anexar">Pedido pagado a anexar</label>
                        <input
                            id="remision-anexar"
                            role="combobox"
                            aria-expanded={lista}
                            aria-controls="remision-anexar-lista"
                            aria-autocomplete="list"
                            aria-describedby="remisiones-estado-lista"
                            autoComplete="off"
                            placeholder="Buscar o elegir remisión"
                            className={`${THEME_INPUT} w-full mt-1`}
                            value={lista || !candidato ? q : etiquetaRemision(candidato)}
                            onFocus={() => setLista(true)}
                            onChange={(evento) => { setQ(evento.target.value); setOrigenId(''); setLista(true); }}
                            onKeyDown={(evento) => { if (evento.key === 'Escape') setLista(false); }}
                        />
                        {lista && (
                            <ul id="remision-anexar-lista" role="listbox" className="absolute z-20 left-0 right-0 mt-1 max-h-60 overflow-auto m-0 p-1 list-none rounded-xl border theme-border theme-element shadow-lg">
                                {disponibles.length === 0 && <li className="px-3 py-2 text-sm theme-text-muted">{cargandoLista ? 'Buscando…' : 'Sin remisiones por anexar.'}</li>}
                                {disponibles.map((item) => (
                                    <li key={item.id} role="option" aria-selected={String(item.id) === String(origenId)}>
                                        <button type="button" className="w-full text-left px-3 py-2 min-h-[44px] rounded-lg bg-transparent border-0 theme-text-main cursor-pointer hover:bg-[var(--theme-element-hover-bg,rgba(255,255,255,0.06))]" onClick={() => { setOrigenId(String(item.id)); setLista(false); }}>{etiquetaRemision(item)}</button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <div className="self-end">
                        <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} disabled={!origenId || ocupado || cargando} onClick={vincular}>Anexar pedido</button>
                    </div>
                    <p id="remisiones-estado-lista" className="text-xs theme-text-muted m-0 sm:col-span-2" role="status" aria-live="polite">
                        {cargandoLista ? 'Buscando remisiones…'
                            : errorLista ? errorLista
                                : disponibles.length === 0 ? (q ? 'Ninguna remisión coincide. Solo se listan compras pagadas y auditadas de este cliente, sin envío propio.' : 'Este cliente no tiene remisiones pagadas disponibles para incorporar.')
                                    : candidato && candidato.mecanismos?.includes('resguardo_envio') ? 'Esa mercancía ya está en CEDIS: CEDIS confirmará el anexo antes de empacar.'
                                        : candidato ? 'Esa mercancía está en custodia de una sucursal: se avisa a quien la resguarda para trasladarla a CEDIS.'
                                            : `${disponibles.length} ${disponibles.length === 1 ? 'remisión disponible' : 'remisiones disponibles'}.`}
                    </p>
                </fieldset>
            )}
        </SeccionPlegableMunicipal>
    );
}
