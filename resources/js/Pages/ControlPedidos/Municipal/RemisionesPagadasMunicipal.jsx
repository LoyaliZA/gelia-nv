import React, { useEffect, useState } from 'react';
import { Package } from 'lucide-react';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';

const MECANISMOS = [
    { id: 'custodia_local', label: 'Integrar en sucursal' },
    { id: 'custodia_cedis', label: 'Trasladar a CEDIS' },
    { id: 'resguardo_envio', label: 'Anexar al envío' },
];

export default function RemisionesPagadasMunicipal({ pedido, ocupado = false, onPedidoActualizado, onAviso }) {
    const [q, setQ] = useState('');
    const [candidatos, setCandidatos] = useState([]);
    const [origenId, setOrigenId] = useState('');
    const [mecanismo, setMecanismo] = useState('resguardo_envio');
    const [cargando, setCargando] = useState(false);
    const vinculos = pedido?.resguardo_envio?.vinculos || [];
    const puedeVincular = Boolean(pedido?.resguardo_envio?.capacidades?.puede_vincular);

    const cargar = async (texto = q) => {
        if (!pedido?.id) return;
        const { data } = await window.axios.get(route('control_pedidos.municipal.remisiones', pedido.id), {
            params: { q: texto },
            headers: { Accept: 'application/json' },
        });
        setCandidatos(data.data || []);
    };

    useEffect(() => {
        cargar('').catch(() => {});
        // Recarga al cambiar de pedido o de vínculos persistidos.
    }, [pedido?.id, vinculos.length]);

    const vincular = async () => {
        if (!origenId || ocupado || cargando) return;
        setCargando(true);
        try {
            const { data } = await window.axios.post(route('control_pedidos.municipal.remisiones.vincular', pedido.id), {
                origen_id: Number(origenId),
                mecanismo,
            }, { headers: { Accept: 'application/json' } });
            onPedidoActualizado?.({ ...pedido, ...data });
            setOrigenId('');
            onAviso?.('Remisión vinculada. No se cobra de nuevo.');
        } catch (error) {
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
            onAviso?.('Remisión desvinculada.');
        } catch (error) {
            onAviso?.(error.response?.data?.message || 'No se pudo desvincular la remisión.');
        } finally {
            setCargando(false);
        }
    };

    const integrarLocal = async (origen) => {
        if (ocupado || cargando) return;
        setCargando(true);
        try {
            const { data } = await window.axios.post(route('control_pedidos.municipal.remisiones.integrar_local', [pedido.id, origen.origen_id]), {}, {
                headers: { Accept: 'application/json' },
            });
            onPedidoActualizado?.({ ...pedido, ...data });
            onAviso?.('Integración en sucursal confirmada.');
        } catch (error) {
            onAviso?.(error.response?.data?.message || 'No se pudo confirmar la integración.');
        } finally {
            setCargando(false);
        }
    };

    const etiquetaMecanismo = (codigo) => MECANISMOS.find((item) => item.id === codigo)?.label || codigo;

    return (
        <section className="registrar-tienda-panel">
            <div className="registrar-tienda-seccion-titulo"><Package size={18} /><h3>Remisiones pagadas</h3></div>
            <p className="text-xs theme-text-muted mt-0 mb-3">Integre compras del mismo cliente. El vínculo no activa el resguardo ni vuelve a cobrar la mercancía.</p>
            {vinculos.length > 0 && (
                <ul className="grid gap-2 m-0 mb-4 p-0 list-none">
                    {vinculos.map((item) => (
                        <li key={item.origen_id} className="flex flex-wrap items-center gap-2 text-sm theme-text-main">
                            <span>{item.folio} · {item.remision || 'Sin número'} · {etiquetaMecanismo(item.mecanismo)} · {item.estado}</span>
                            {item.estado === 'pendiente' && item.mecanismo === 'custodia_local' && (
                                <button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado || cargando} onClick={() => integrarLocal(item)}>Confirmar integración</button>
                            )}
                            {item.puede_desvincular && (
                                <button type="button" className={GELIA_BTN_OUTLINE} disabled={ocupado || cargando} onClick={() => desvincular(item)}>Desvincular</button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {puedeVincular && (
                <fieldset disabled={ocupado || cargando} className="grid sm:grid-cols-2 gap-4 min-w-0">
                    <label className={THEME_LABEL}>Buscar remisión
                        <input className={`${THEME_INPUT} w-full mt-1`} value={q} onChange={(evento) => {
                            setQ(evento.target.value);
                            cargar(evento.target.value).catch(() => {});
                        }} />
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
                        <select className={`${THEME_SELECT} w-full mt-1`} value={mecanismo} onChange={(evento) => setMecanismo(evento.target.value)}>
                            {MECANISMOS.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}
                        </select>
                    </label>
                    <div className="self-end">
                        <button type="button" className={THEME_BTN_PRIMARY} disabled={!origenId || ocupado || cargando} onClick={vincular}>Vincular remisión</button>
                    </div>
                </fieldset>
            )}
        </section>
    );
}
