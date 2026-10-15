import React, { useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import { geliaCardClass, THEME_INPUT, THEME_SELECT, THEME_BTN_PRIMARY } from '../../utils/geliaTheme';

function useOperacion() {
    const [error, setError] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const clave = useRef(null);
    const enviar = (url, datos) => {
        if (ocupado) return;
        clave.current ||= crypto.randomUUID();
        setOcupado(true);
        setError('');
        router.post(url, { ...datos, idempotency_key: clave.current }, {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => { clave.current = null; },
            onError: (errores) => setError(Object.values(errores).flat().join(' ')),
            onFinish: () => setOcupado(false),
        });
    };
    return { enviar, error, ocupado };
}

export default function Abastecimiento({ auth, tarea, traspasos, almacenes = [] }) {
    return <AppLayout auth={auth}>
        <Head title="Abastecimiento a preparación" />
        <GeliaPageShell className="max-w-5xl space-y-5">
            <h1 className="theme-text-main text-xl font-bold">Abastecimiento a preparación en PDV</h1>
            <p className="theme-text-muted">Traslade piezas a una tarea existente. La salida no acredita llegada. Cada recepción incorpora únicamente las piezas buenas verificadas; no modifica existencias del catálogo.</p>
            {tarea && <Solicitar key={`${tarea.id}-${tarea.version}`} tarea={tarea} almacenes={almacenes} />}
            {!tarea && <p className="theme-text-muted">Para solicitar piezas abra la preparación receptora en Tienda y elija «Abastecer preparación». Aquí se muestran los traslados de sus orígenes y destinos autorizados.</p>}
            {traspasos.data.map((s) => <Traslado key={`${s.id}-${s.version}`} traslado={s} />)}
            {traspasos.data.length === 0 && <p className="theme-text-muted">No hay traslados de abastecimiento en este alcance.</p>}
            <GeliaPaginacion paginator={traspasos} onIrAPagina={(page) => router.get(route('traspasos.abastecimiento.index'), { page, ...(tarea ? { tarea: tarea.id } : {}) })} />
        </GeliaPageShell>
    </AppLayout>;
}

function Solicitar({ tarea, almacenes }) {
    const [origen, setOrigen] = useState('');
    const [cantidades, setCantidades] = useState({});
    const { enviar, error, ocupado } = useOperacion();
    const lineas = tarea.productos.filter((p) => Number(cantidades[p.id]) > 0).map((p) => ({ id: p.id, cantidad: Number(cantidades[p.id]) }));
    return <form className={`${geliaCardClass()} p-5 space-y-4`} onSubmit={(e) => {
        e.preventDefault(); enviar(route('traspasos.abastecimiento.crear', tarea.id), { version: tarea.version, almacen_origen_id: origen, lineas });
    }}>
        <h2 className="theme-text-main font-bold">Pedido {tarea.folio} · Preparación #{tarea.id} · {tarea.almacen}</h2>
        <label className="block theme-text-main">Almacén físico de origen
            <select required className={THEME_SELECT} value={origen} onChange={(e) => setOrigen(e.target.value)}>
                <option value="">Seleccione origen</option>{almacenes.map((a) => <option key={a.id} value={a.id}>{a.codigo} · {a.nombre}</option>)}
            </select>
        </label>
        {tarea.productos.map((p) => <label key={p.id} className="grid sm:grid-cols-2 gap-2 items-center theme-text-main">
            <span>{p.sku} · {p.descripcion}<small className="block theme-text-muted">Solicitadas {p.solicitadas} · En preparación {p.encontradas} · Pendientes de abastecimiento {p.comprometidas} · En tránsito {p.en_traslado} · Por abastecer {p.por_abastecer}</small></span>
            <input aria-label={`Solicitar ${p.sku}`} type="number" min="0" max={p.por_abastecer} disabled={!p.por_abastecer || ocupado} className={THEME_INPUT} value={cantidades[p.id] || ''} onChange={(e) => setCantidades({ ...cantidades, [p.id]: e.target.value })} />
        </label>)}
        {error && <p role="alert" className="theme-text-peligro">{error}</p>}
        <button disabled={ocupado || !lineas.length} className={THEME_BTN_PRIMARY}>Solicitar traslado para esta preparación</button>
    </form>;
}

function Traslado({ traslado: s }) {
    const { enviar, error, ocupado } = useOperacion();
    const [tipo, setTipo] = useState(s.salida_at ? (s.puede_llegada ? 'LLEGADA' : 'RECHAZO_AUTORIZADO') : 'SALIDA');
    const [conteos, setConteos] = useState({});
    const [revisadas, setRevisadas] = useState({});
    const [evidencia, setEvidencia] = useState(null);
    const [observacion, setObservacion] = useState('');
    const abiertas = s.lineas.some((p) => p.pendientes > 0 || p.incidencia_abierta > 0);
    const puedeOperar = !s.salida_at ? s.puede_salida : abiertas && (s.puede_llegada || s.puede_resolver);
    const confirmar = (e) => {
        e.preventDefault();
        let lineas = [];
        const revisiones = [];
        if (tipo === 'SALIDA') {
            s.lineas.forEach((p) => { for (let i = 0; i < p.esperadas; i++) revisiones.push({ solicitud_traspaso_producto_id: p.id, estado_fisico: revisadas[`${p.id}-${i}`] ? 'bueno' : '' }); });
        } else {
            lineas = s.lineas.map((p) => tipo === 'LLEGADA'
                ? { id: p.id, buenas: Number(conteos[p.id]?.buenas || 0), danadas: Number(conteos[p.id]?.danadas || 0) }
                : { id: p.id, cantidad: Number(conteos[p.id]?.cantidad || 0) })
                .filter((p) => (p.buenas || 0) + (p.danadas || 0) + (p.cantidad || 0) > 0);
        }
        enviar(route('traspasos.abastecimiento.operar', s.id), { tipo, version: s.version, lineas, revisiones, evidencia, observacion });
    };
    const campo = (p, clave, max, etiqueta) => <label className="theme-text-main text-sm">{etiqueta}
        <input type="number" min="0" max={max} className={THEME_INPUT} value={conteos[p.id]?.[clave] || ''} onChange={(e) => setConteos({ ...conteos, [p.id]: { ...conteos[p.id], [clave]: e.target.value } })} />
    </label>;
    return <section className={`${geliaCardClass()} p-5 space-y-4`}>
        <h2 className="theme-text-main font-bold">{s.folio} · {s.origen} → {s.destino}</h2>
        <p className="theme-text-muted">Pedido {s.pedido} · Tarea receptora #{s.tarea_id} · {s.salida_at ? (abiertas ? 'En tránsito / conciliación pendiente' : 'Conciliado') : 'Pendiente de revisión y salida'}</p>
        <Link className="text-primario underline" href={route('control_pedidos.tienda.show', s.tarea_id)}>Ver preparación receptora</Link>
        {s.lineas.map((p) => <div key={p.id} className="border-b theme-border pb-2 theme-text-main">
            {p.sku} · {p.descripcion}<p className="theme-text-muted text-sm">Esperadas {p.esperadas} · Buenas recibidas {p.buenas} · Dañadas {p.danadas} · En tránsito {p.pendientes} · Faltantes reportados {p.faltantes_reportados} · Daños sin resolver {p.incidencia_abierta} · Rechazadas con autorización {p.rechazadas}</p>
        </div>)}
        {puedeOperar && <form onSubmit={confirmar} className="space-y-3">
            {s.salida_at && <label className="block theme-text-main">Operación
                <select className={THEME_SELECT} value={tipo} onChange={(e) => setTipo(e.target.value)}>
                    {s.puede_llegada && <><option value="LLEGADA">Registrar llegada real</option><option value="FALTANTE">Reportar piezas faltantes</option></>}
                    {s.puede_resolver && <option value="RECHAZO_AUTORIZADO">Autorizar rechazo de piezas dañadas</option>}
                </select>
            </label>}
            {s.lineas.map((p) => <fieldset key={p.id} className="space-y-2"><legend className="theme-text-main font-semibold">{p.sku}</legend>
                {tipo === 'SALIDA' ? Array.from({ length: p.esperadas }, (_, i) => <label className="flex gap-2 min-h-[44px] items-center theme-text-main" key={i}>
                    <input type="checkbox" required checked={Boolean(revisadas[`${p.id}-${i}`])} onChange={(e) => setRevisadas({ ...revisadas, [`${p.id}-${i}`]: e.target.checked })} /> Pieza {i + 1}: identidad, cantidad y condición buena verificadas
                </label>) : tipo === 'LLEGADA' ? <div className="grid sm:grid-cols-2 gap-3">{campo(p, 'buenas', p.pendientes, 'Buenas recibidas ahora')}{campo(p, 'danadas', p.pendientes, 'Dañadas recibidas ahora')}</div> : campo(p, 'cantidad', tipo === 'FALTANTE' ? p.pendientes - p.faltantes_reportados : p.incidencia_abierta, tipo === 'FALTANTE' ? 'Piezas que se reportan faltantes' : 'Piezas dañadas rechazadas con autorización')}
            </fieldset>)}
            {tipo === 'RECHAZO_AUTORIZADO' && <p className="theme-text-muted text-sm">El rechazo documenta la incidencia y libera esas unidades para solicitar reposición. Las piezas rechazadas no abastecen la preparación.</p>}
            <label className="block theme-text-main">Observaciones / motivo de autorización<textarea className={THEME_INPUT} value={observacion} minLength={['FALTANTE', 'RECHAZO_AUTORIZADO'].includes(tipo) ? 8 : undefined} required={['FALTANTE', 'RECHAZO_AUTORIZADO'].includes(tipo)} onChange={(e) => setObservacion(e.target.value)} /></label>
            <label className="block theme-text-main">Evidencia física de esta operación<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required onChange={(e) => setEvidencia(e.target.files[0])} /></label>
            {error && <p role="alert" className="theme-text-peligro">{error}</p>}
            <button className={THEME_BTN_PRIMARY} disabled={ocupado}>{tipo === 'SALIDA' ? 'Confirmar salida física' : tipo === 'LLEGADA' ? 'Registrar cantidades de esta llegada' : tipo === 'FALTANTE' ? 'Registrar incidencia de faltante' : 'Autorizar rechazo documentado'}</button>
        </form>}
        <details><summary className="theme-text-main cursor-pointer">Historial de operaciones y evidencias</summary>
            {s.operaciones.map((op) => <div key={op.id} className="theme-text-muted text-sm py-2">{op.tipo} · {op.fecha} · Responsable {op.actor_nombre || `#${op.actor_id}`}<p>{op.observacion}</p>
                {op.lineas.map((p, i) => <p key={i}>Línea #{p.id}: {op.tipo === 'LLEGADA' ? `${p.buenas} buenas y ${p.danadas} dañadas` : `${p.cantidad || 0} piezas`}</p>)}
                {op.tiene_evidencia && <a className="text-primario underline" href={route('traspasos.abastecimiento.evidencia', [s.id, op.id])} target="_blank" rel="noreferrer">Ver evidencia</a>}
            </div>)}
        </details>
    </section>;
}
