import { useEffect, useState } from 'react';
import axios from 'axios';
import { THEME_INPUT, THEME_TEXTAREA } from '../../../utils/geliaTheme';

function etiqueta(pedido) {
    const remision = pedido.numero_remision || pedido.folio_remision;
    return [pedido.folio, remision ? `Remisión ${remision}` : null].filter(Boolean).join(' · ');
}

export default function SelectorResguardosEnvio({
    clienteId,
    pedidoId,
    ids = [],
    notas = '',
    solo = false,
    seleccionInicial = [],
    onChange,
}) {
    const [q, setQ] = useState('');
    const [buscando, setBuscando] = useState(false);
    const [opciones, setOpciones] = useState([]);
    const [elegidos, setElegidos] = useState(seleccionInicial);
    const porId = new Map();
    seleccionInicial.forEach((p) => porId.set(Number(p.id), p));
    elegidos.forEach((p) => porId.set(Number(p.id), p));
    const visibles = (ids || []).map((id) => porId.get(Number(id)) || { id, folio: `Pedido ${id}` });

    useEffect(() => {
        if (!clienteId) {
            setOpciones([]);
            return undefined;
        }
        let activo = true;
        const timer = setTimeout(async () => {
            setBuscando(true);
            try {
                const { data: json } = await axios.get(route('control_pedidos.candidatos_resguardo_envio'), {
                    params: { cliente_id: clienteId, pedido_id: pedidoId || undefined, q },
                });
                if (activo) setOpciones(json.data || []);
            } catch {
                if (activo) setOpciones([]);
            } finally {
                if (activo) setBuscando(false);
            }
        }, 300);
        return () => {
            activo = false;
            clearTimeout(timer);
        };
    }, [clienteId, pedidoId, q]);

    const publicar = (siguientes, extra = {}) => {
        setElegidos(siguientes);
        onChange({
            resguardo_ids: siguientes.map((p) => p.id),
            notas_resguardos_externos: notas,
            envio_solo_resguardos: solo,
            ...extra,
        });
    };

    const agregar = (pedido) => {
        if (elegidos.some((p) => Number(p.id) === Number(pedido.id))) return;
        publicar([...elegidos, pedido]);
        setQ('');
        setOpciones([]);
    };

    const quitar = (id) => {
        publicar(elegidos.filter((p) => Number(p.id) !== Number(id)));
    };

    return (
        <div className="md:col-span-2 space-y-3 rounded-xl border theme-border p-3" data-campo="resguardos_envio">
            <p className="text-xs font-bold theme-text-main m-0">Resguardos que salen en este envío</p>
            <label className="flex items-start gap-2 text-xs font-bold theme-text-main">
                <input
                    type="checkbox"
                    className="mt-0.5"
                    checked={solo}
                    onChange={(e) => onChange({
                        resguardo_ids: ids,
                        notas_resguardos_externos: notas,
                        envio_solo_resguardos: e.target.checked,
                    })}
                />
                Sin mercancía nueva: solo envío de resguardos
            </label>
            <p className="text-[11px] theme-text-muted m-0">
                Elija resguardos del mismo cliente que ya tengan remisión. CEDIS pesa el conjunto. Si la remisión no está en GELIA, anótela abajo.
            </p>
            {!clienteId && (
                <p className="text-xs font-bold theme-text-aviso m-0">Seleccione el cliente para buscar sus resguardos.</p>
            )}
            {visibles.length > 0 && (
                <ul className="m-0 p-0 list-none space-y-1">
                    {visibles.map((pedido) => (
                        <li key={pedido.id} className="flex items-center justify-between gap-2 text-xs font-bold theme-text-main">
                            <span>{etiqueta(pedido)}</span>
                            <button type="button" className="theme-text-peligro" onClick={() => quitar(pedido.id)}>Quitar</button>
                        </li>
                    ))}
                </ul>
            )}
            {clienteId && (
                <div className="relative">
                    <input
                        type="text"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Buscar folio o remisión..."
                        className={`${THEME_INPUT} w-full py-3`}
                    />
                    {(buscando || opciones.length > 0) && (
                        <div className="absolute z-50 mt-1 w-full theme-surface border theme-border rounded-xl shadow-xl max-h-48 overflow-y-auto p-2">
                            {buscando ? (
                                <p className="p-2 text-xs theme-text-muted m-0">Buscando...</p>
                            ) : opciones.map((pedido) => (
                                <button
                                    key={pedido.id}
                                    type="button"
                                    onClick={() => agregar(pedido)}
                                    className="w-full text-left p-2 rounded-lg text-xs font-bold theme-text-main"
                                >
                                    {etiqueta(pedido)}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}
            <label className="block text-[11px] font-bold theme-text-muted">
                Remisiones que no están en GELIA
                <textarea
                    value={notas}
                    onChange={(e) => onChange({
                        resguardo_ids: ids,
                        notas_resguardos_externos: e.target.value,
                        envio_solo_resguardos: solo,
                    })}
                    className={`${THEME_TEXTAREA} w-full mt-1 min-h-[72px]`}
                    placeholder="Ej. remisión 1546 y remisión 4897"
                />
            </label>
            {solo && (
                <p className="text-[11px] theme-text-muted m-0">
                    Después del pesaje, anexe el pago de envío. Ese cobro no genera remisión.
                </p>
            )}
        </div>
    );
}
