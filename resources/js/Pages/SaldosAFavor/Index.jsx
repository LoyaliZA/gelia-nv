import React, { useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Search, Wallet, PlusCircle, ClipboardCheck, Upload, AlertTriangle } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import GeliaPaginacion from '../../Components/GeliaPaginacion';
import { geliaCardClass, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../utils/geliaTheme';
import {
    BTN_PRIMARY,
    BTN_SECONDARY,
    FLASH_ERR,
    FLASH_OK,
    LABEL_CANAL,
    LABEL_CATEGORIA_MOTIVO,
    LABEL_ESTADO_FIN,
    LABEL_ESTADO_REV,
    TD,
    TH,
    THEME_INPUT,
    THEME_LABEL,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_SELECT,
    THEME_TEXTAREA,
    fmtMoneda,
    groupMotivosByCategoria,
} from './Partials/safStyles';

const TABS = [
    { id: 'creditos', label: 'Saldos' },
    { id: 'pendientes', label: 'Pendientes revisión' },
    { id: 'pagos', label: 'Pagos' },
    { id: 'caja', label: 'Caja' },
    { id: 'incidencias', label: 'Incidencias' },
];

export default function Index({
    auth,
    creditos,
    filtros = {},
    metricas = {},
    motivos = [],
    generadores = [],
    colas = {},
    tab = 'creditos',
}) {
    const { flash } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p) || auth?.user?.roles?.includes('Super Admin');

    const [q, setQ] = useState(filtros.q || '');
    const [estadoRevision, setEstadoRevision] = useState(filtros.estado_revision || '');
    const [estadoFinanciero, setEstadoFinanciero] = useState(filtros.estado_financiero || '');
    const [canalOrigen, setCanalOrigen] = useState(filtros.canal_origen || '');
    const [generadoPorId, setGeneradoPorId] = useState(filtros.generado_por_id || '');
    const [montoMin, setMontoMin] = useState(filtros.monto_min || '');
    const [montoMax, setMontoMax] = useState(filtros.monto_max || '');
    const [antiguedadDias, setAntiguedadDias] = useState(filtros.antiguedad_dias || '');
    const [mostrarGenerar, setMostrarGenerar] = useState(false);
    const [clienteQuery, setClienteQuery] = useState('');
    const [clientes, setClientes] = useState([]);
    const [evidencias, setEvidencias] = useState([]);
    const [notaIncidencia, setNotaIncidencia] = useState({});

    const form = useForm({
        cliente_id: '',
        monto: '',
        saf_motivo_id: '',
        detalle_motivo: '',
        canal_origen: 'bellaroma',
        documento_origen: '',
        observaciones: '',
    });

    const filtrosPayload = () => ({
        q: q || undefined,
        estado_revision: estadoRevision || undefined,
        estado_financiero: estadoFinanciero || undefined,
        canal_origen: canalOrigen || undefined,
        generado_por_id: generadoPorId || undefined,
        monto_min: montoMin || undefined,
        monto_max: montoMax || undefined,
        antiguedad_dias: antiguedadDias || undefined,
        tab,
    });

    const buscar = (tabOverride) => {
        router.get(route('saldos_favor.index'), {
            ...filtrosPayload(),
            tab: tabOverride || tab,
        }, { preserveState: true, preserveScroll: true });
    };

    const buscarClientes = async (valor) => {
        setClienteQuery(valor);
        if (!valor || valor.length < 1) {
            setClientes([]);
            return;
        }
        const res = await fetch(route('saldos_favor.buscar_cliente', { q: valor }), {
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        setClientes(json.data || []);
    };

    const enviarGenerar = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, evidencias }));
        form.post(route('saldos_favor.generar'), {
            forceFormData: true,
            onSuccess: () => {
                setMostrarGenerar(false);
                form.reset();
                setClienteQuery('');
                setEvidencias([]);
            },
        });
    };

    const motivoSeleccionado = useMemo(
        () => motivos.find((m) => String(m.id) === String(form.data.saf_motivo_id)),
        [motivos, form.data.saf_motivo_id]
    );

    const conteoTab = (id) => {
        if (id === 'pendientes') return metricas.pendientes_revision ?? 0;
        if (id === 'pagos') return metricas.pagos_pendientes ?? 0;
        if (id === 'caja') return metricas.caja_pendientes ?? 0;
        if (id === 'incidencias') return metricas.incidencias_abiertas ?? 0;
        return undefined;
    };

    const revisarPago = (pagoId, estado) => {
        router.post(route('saldos_favor.pagos.revisar', pagoId), {
            estado_revision: estado,
        }, { preserveScroll: true });
    };

    const resolverIncidencia = (id) => {
        router.post(route('saldos_favor.incidencias.resolver', id), {
            nota: notaIncidencia[id] || '',
        }, { preserveScroll: true });
    };

    const acciones = (
        <div className="flex flex-wrap gap-2">
            {can('saldos_favor.caja') && (
                <Link href={route('saldos_favor.caja.index')} className={`${BTN_SECONDARY} inline-flex items-center gap-2`}>
                    <Wallet className="w-4 h-4" /> Caja
                </Link>
            )}
            {can('saldos_favor.migrar') && (
                <Link href={route('saldos_favor.migrar.index')} className={`${BTN_SECONDARY} inline-flex items-center gap-2`}>
                    <Upload className="w-4 h-4" /> Migrar
                </Link>
            )}
            {can('saldos_favor.generar') && (
                <button type="button" onClick={() => setMostrarGenerar(true)} className={`${BTN_PRIMARY} inline-flex items-center gap-2`}>
                    <PlusCircle className="w-4 h-4" /> Generar saldo
                </button>
            )}
        </div>
    );

    return (
        <AppLayout auth={auth}>
            <Head title="Saldos a favor" />
            <GeliaPageShell className="space-y-6">
                <GeliaTituloCard
                    eyebrow="Finanzas"
                    title="Saldos"
                    titleHighlight="a favor"
                    description="Cuenta unificada · libro de movimientos · revisión administrativa no bloqueante"
                    icon={Wallet}
                    aside={acciones}
                />

                {flash?.success && <div className={FLASH_OK}>{flash.success}</div>}
                {flash?.error && <div className={FLASH_ERR}>{flash.error}</div>}

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div className={geliaCardClass('p-4')}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Pendientes revisión</p>
                        <p className="text-xl font-black m-0 mt-1">{metricas.pendientes_revision ?? 0}</p>
                    </div>
                    <div className={geliaCardClass('p-4')}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Pagos</p>
                        <p className="text-xl font-black m-0 mt-1">{metricas.pagos_pendientes ?? 0}</p>
                    </div>
                    <div className={geliaCardClass('p-4')}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Caja</p>
                        <p className="text-xl font-black m-0 mt-1">{metricas.caja_pendientes ?? 0}</p>
                    </div>
                    <div className={geliaCardClass('p-4')}>
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Incidencias</p>
                        <p className="text-xl font-black m-0 mt-1">{metricas.incidencias_abiertas ?? 0}</p>
                    </div>
                </div>

                <div className={GELIA_SEGMENT_TABS_SCROLL}>
                    <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label="Bandeja SAF">
                        {TABS.map((t) => (
                            <button
                                key={t.id}
                                type="button"
                                role="tab"
                                aria-selected={tab === t.id}
                                data-active={tab === t.id}
                                className="gelia-segment-btn whitespace-nowrap gap-1.5"
                                onClick={() => buscar(t.id)}
                            >
                                {t.label}
                                {conteoTab(t.id) !== undefined && (
                                    <span className="text-[9px] font-black px-1.5 py-0.5 rounded-md theme-element border theme-border">
                                        {conteoTab(t.id)}
                                    </span>
                                )}
                            </button>
                        ))}
                    </div>
                </div>

                {tab === 'creditos' && (
                    <>
                        <div className={geliaCardClass('p-4 md:p-5 flex flex-wrap gap-3 items-end')}>
                            <div className="flex-1 min-w-[160px]">
                                <label className={THEME_LABEL}>Buscar</label>
                                <div className="relative mt-1">
                                    <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 theme-text-muted pointer-events-none" />
                                    <input
                                        className={`${THEME_INPUT} theme-field-with-icon w-full pl-9`}
                                        value={q}
                                        onChange={(e) => setQ(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && buscar()}
                                        placeholder="Folio, cliente…"
                                    />
                                </div>
                            </div>
                            <div className="min-w-[140px]">
                                <label className={THEME_LABEL}>Revisión</label>
                                <select className={`${THEME_SELECT} w-full mt-1`} value={estadoRevision} onChange={(e) => setEstadoRevision(e.target.value)}>
                                    <option value="">Todas</option>
                                    {Object.entries(LABEL_ESTADO_REV).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                            </div>
                            <div className="min-w-[140px]">
                                <label className={THEME_LABEL}>Estado financiero</label>
                                <select className={`${THEME_SELECT} w-full mt-1`} value={estadoFinanciero} onChange={(e) => setEstadoFinanciero(e.target.value)}>
                                    <option value="">Todos</option>
                                    {Object.entries(LABEL_ESTADO_FIN).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                            </div>
                            <div className="min-w-[140px]">
                                <label className={THEME_LABEL}>Canal</label>
                                <select className={`${THEME_SELECT} w-full mt-1`} value={canalOrigen} onChange={(e) => setCanalOrigen(e.target.value)}>
                                    <option value="">Todos</option>
                                    {Object.entries(LABEL_CANAL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                            </div>
                            <div className="min-w-[160px]">
                                <label className={THEME_LABEL}>Generado por</label>
                                <select className={`${THEME_SELECT} w-full mt-1`} value={generadoPorId} onChange={(e) => setGeneradoPorId(e.target.value)}>
                                    <option value="">Todos</option>
                                    {generadores.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
                                </select>
                            </div>
                            <div className="min-w-[100px]">
                                <label className={THEME_LABEL}>Monto min</label>
                                <input type="number" step="0.01" className={`${THEME_INPUT} w-full mt-1`} value={montoMin} onChange={(e) => setMontoMin(e.target.value)} />
                            </div>
                            <div className="min-w-[100px]">
                                <label className={THEME_LABEL}>Monto max</label>
                                <input type="number" step="0.01" className={`${THEME_INPUT} w-full mt-1`} value={montoMax} onChange={(e) => setMontoMax(e.target.value)} />
                            </div>
                            <div className="min-w-[110px]">
                                <label className={THEME_LABEL}>Antigüedad (días)</label>
                                <input type="number" className={`${THEME_INPUT} w-full mt-1`} value={antiguedadDias} onChange={(e) => setAntiguedadDias(e.target.value)} />
                            </div>
                            <button type="button" onClick={() => buscar()} className={BTN_PRIMARY}>Filtrar</button>
                        </div>

                        <div className={geliaCardClass('overflow-x-auto')}>
                            <table className="min-w-full">
                                <thead className="theme-element">
                                    <tr>
                                        <th className={TH}>Folio</th>
                                        <th className={TH}>Cliente</th>
                                        <th className={TH}>Disponible</th>
                                        <th className={TH}>Estado</th>
                                        <th className={TH}>Revisión</th>
                                        <th className={TH}>Vence</th>
                                        <th className={TH} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {(creditos?.data || []).length === 0 && (
                                        <tr>
                                            <td colSpan={7} className={`${TD} text-center theme-text-muted py-8`}>
                                                Sin saldos a favor para los filtros seleccionados.
                                            </td>
                                        </tr>
                                    )}
                                    {(creditos?.data || []).map((c) => (
                                        <tr key={c.id} className="hover:bg-[color-mix(in_srgb,var(--color-primario)_4%,transparent)]">
                                            <td className={`${TD} font-bold`}>{c.folio}</td>
                                            <td className={TD}>
                                                <div className="font-bold">{c.cliente?.nombre}</div>
                                                <div className="text-[10px] font-bold uppercase tracking-wider theme-text-muted">#{c.cliente?.numero_cliente}</div>
                                            </td>
                                            <td className={`${TD} font-bold`}>{fmtMoneda(c.monto_disponible)}</td>
                                            <td className={TD}>
                                                <span className="text-[10px] font-black uppercase tracking-wide theme-text-muted">
                                                    {LABEL_ESTADO_FIN[c.estado_financiero] || c.estado_financiero}
                                                </span>
                                            </td>
                                            <td className={TD}>
                                                <span className={`inline-flex px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wide border ${
                                                    c.estado_revision === 'pendiente'
                                                        ? 'bg-amber-500/15 text-amber-700 border-amber-500/30'
                                                        : c.estado_revision === 'revisado'
                                                            ? 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30'
                                                            : 'theme-element theme-border theme-text-muted'
                                                }`}>
                                                    {LABEL_ESTADO_REV[c.estado_revision] || c.estado_revision}
                                                </span>
                                            </td>
                                            <td className={TD}>{c.fecha_vencimiento}</td>
                                            <td className={`${TD} text-right`}>
                                                <Link
                                                    href={route('saldos_favor.cuenta', c.cliente_id)}
                                                    className="inline-flex items-center gap-1 text-xs font-black uppercase tracking-wide"
                                                    style={{ color: 'var(--color-primario)' }}
                                                >
                                                    <ClipboardCheck className="w-4 h-4" /> Cuenta
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div className="p-3 border-t theme-border">
                                <GeliaPaginacion
                                    paginator={creditos}
                                    onIrAPagina={(page) => router.get(route('saldos_favor.index'), { ...filtrosPayload(), page }, { preserveState: true })}
                                />
                            </div>
                        </div>
                    </>
                )}

                {tab === 'pendientes' && (
                    <ColaTabla
                        rows={colas.creditos_pendientes || []}
                        empty="Sin saldos pendientes de revisión."
                        columns={['Folio', 'Cliente', 'Disponible', 'Canal']}
                        renderRow={(c) => (
                            <>
                                <td className={`${TD} font-bold`}>{c.folio}</td>
                                <td className={TD}>{c.cliente?.nombre}</td>
                                <td className={TD}>{fmtMoneda(c.monto_disponible)}</td>
                                <td className={TD}>{LABEL_CANAL[c.canal_origen] || c.canal_origen}</td>
                                <td className={`${TD} text-right`}>
                                    <Link href={route('saldos_favor.cuenta', c.cliente_id)} className="text-xs font-black uppercase" style={{ color: 'var(--color-primario)' }}>Revisar</Link>
                                </td>
                            </>
                        )}
                    />
                )}

                {tab === 'pagos' && (
                    <ColaTabla
                        rows={colas.pagos_pendientes || []}
                        empty="Sin exhibiciones pendientes."
                        columns={['Pedido', 'Cliente', 'Monto', 'Estado']}
                        renderRow={(p) => (
                            <>
                                <td className={`${TD} font-bold`}>{p.pedido?.folio || p.pedido_bma_id}</td>
                                <td className={TD}>{p.pedido?.cliente?.nombre}</td>
                                <td className={TD}>{fmtMoneda(p.monto)}</td>
                                <td className={TD}>{LABEL_ESTADO_REV[p.estado_revision] || p.estado_revision}</td>
                                <td className={`${TD} text-right whitespace-nowrap space-x-2`}>
                                    {can('saldos_favor.revisar') && (
                                        <>
                                            <button type="button" className="text-xs font-black uppercase text-emerald-700" onClick={() => revisarPago(p.id, 'verificado')}>
                                                Verificar
                                            </button>
                                            <button type="button" className="text-xs font-black uppercase text-amber-700" onClick={() => revisarPago(p.id, 'con_observaciones')}>
                                                Observaciones
                                            </button>
                                            <button type="button" className="text-xs font-black uppercase text-rose-700" onClick={() => revisarPago(p.id, 'rechazado')}>
                                                Rechazar
                                            </button>
                                        </>
                                    )}
                                </td>
                            </>
                        )}
                    />
                )}

                {tab === 'caja' && (
                    <ColaTabla
                        rows={colas.comprobantes_caja || []}
                        empty="Sin comprobantes pendientes de firma/revisión."
                        columns={['Folio', 'Cliente', 'Monto', 'Estado']}
                        renderRow={(c) => (
                            <>
                                <td className={`${TD} font-bold`}>{c.folio}</td>
                                <td className={TD}>{c.cliente?.nombre}</td>
                                <td className={TD}>{fmtMoneda(c.monto_aplicado)}</td>
                                <td className={TD}>{c.estado}</td>
                                <td className={`${TD} text-right`}>
                                    <Link href={route('saldos_favor.caja.comprobante', c.id)} className="text-xs font-black uppercase" style={{ color: 'var(--color-primario)' }}>Abrir</Link>
                                </td>
                            </>
                        )}
                    />
                )}

                {tab === 'incidencias' && (
                    <div className={geliaCardClass('overflow-x-auto')}>
                        <table className="min-w-full">
                            <thead className="theme-element">
                                <tr>
                                    <th className={TH}>Tipo</th>
                                    <th className={TH}>Cliente</th>
                                    <th className={TH}>Descripción</th>
                                    <th className={TH}>Nota resolución</th>
                                    <th className={TH} />
                                </tr>
                            </thead>
                            <tbody>
                                {(colas.incidencias || []).length === 0 && (
                                    <tr>
                                        <td colSpan={5} className={`${TD} text-center theme-text-muted py-8`}>
                                            <span className="inline-flex items-center gap-2"><AlertTriangle className="w-4 h-4" /> Sin incidencias abiertas.</span>
                                        </td>
                                    </tr>
                                )}
                                {(colas.incidencias || []).map((i) => (
                                    <tr key={i.id}>
                                        <td className={`${TD} font-bold`}>{i.tipo}</td>
                                        <td className={TD}>{i.cliente?.nombre || '—'}</td>
                                        <td className={`${TD} max-w-md truncate`}>{i.descripcion}</td>
                                        <td className={TD}>
                                            {can('saldos_favor.ajustar') && (
                                                <input
                                                    className={`${THEME_INPUT} w-full min-w-[140px]`}
                                                    placeholder="Nota (opcional)"
                                                    value={notaIncidencia[i.id] || ''}
                                                    onChange={(e) => setNotaIncidencia((prev) => ({ ...prev, [i.id]: e.target.value }))}
                                                />
                                            )}
                                        </td>
                                        <td className={`${TD} text-right`}>
                                            {can('saldos_favor.ajustar') && (
                                                <button type="button" className="text-xs font-black uppercase text-emerald-700" onClick={() => resolverIncidencia(i.id)}>
                                                    Resolver
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {mostrarGenerar && (
                    <div className={THEME_MODAL_OVERLAY} role="dialog" aria-modal="true">
                        <form onSubmit={enviarGenerar} className={`${THEME_MODAL_SHELL} w-full max-w-lg p-6 space-y-4`}>
                            <h2 className="text-lg font-black italic uppercase tracking-tight theme-text-main m-0">Generar saldo a favor</h2>
                            <div>
                                <label className={THEME_LABEL}>Cliente</label>
                                <input className={`${THEME_INPUT} w-full mt-1`} value={clienteQuery} onChange={(e) => buscarClientes(e.target.value)} placeholder="Número o nombre" />
                                {clientes.length > 0 && (
                                    <div className="border theme-border rounded-xl mt-1 max-h-40 overflow-auto theme-surface">
                                        {clientes.map((c) => (
                                            <button
                                                type="button"
                                                key={c.id}
                                                className="block w-full text-left px-3 py-2 text-sm theme-text-main hover:bg-[color-mix(in_srgb,var(--color-primario)_8%,transparent)]"
                                                onClick={() => {
                                                    form.setData('cliente_id', c.id);
                                                    setClienteQuery(`${c.numero_cliente} — ${c.nombre}`);
                                                    setClientes([]);
                                                }}
                                            >
                                                <span className="font-bold">{c.numero_cliente}</span>
                                                <span className="theme-text-muted"> — {c.nombre}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                                {form.errors.cliente_id && <p className="text-xs font-bold text-rose-600 mt-1 m-0">{form.errors.cliente_id}</p>}
                            </div>
                            <div>
                                <label className={THEME_LABEL}>Monto</label>
                                <input type="number" step="0.01" required className={`${THEME_INPUT} w-full mt-1`} value={form.data.monto} onChange={(e) => form.setData('monto', e.target.value)} />
                                {form.errors.monto && <p className="text-xs font-bold text-rose-600 mt-1 m-0">{form.errors.monto}</p>}
                            </div>
                            <div>
                                <label className={THEME_LABEL}>Motivo</label>
                                <select required className={`${THEME_SELECT} w-full mt-1`} value={form.data.saf_motivo_id} onChange={(e) => form.setData('saf_motivo_id', e.target.value)}>
                                    <option value="">Seleccionar…</option>
                                    {groupMotivosByCategoria(motivos).map(([cat, items]) => (
                                        <optgroup key={cat} label={LABEL_CATEGORIA_MOTIVO[cat] || cat}>
                                            {items.map((m) => <option key={m.id} value={m.id}>{m.nombre}</option>)}
                                        </optgroup>
                                    ))}
                                </select>
                                {form.errors.saf_motivo_id && <p className="text-xs font-bold text-rose-600 mt-1 m-0">{form.errors.saf_motivo_id}</p>}
                            </div>
                            {motivoSeleccionado?.requiere_detalle && (
                                <div>
                                    <label className={THEME_LABEL}>Detalle obligatorio</label>
                                    <textarea required className={`${THEME_TEXTAREA} w-full mt-1`} value={form.data.detalle_motivo} onChange={(e) => form.setData('detalle_motivo', e.target.value)} />
                                </div>
                            )}
                            <div>
                                <label className={THEME_LABEL}>Canal</label>
                                <select className={`${THEME_SELECT} w-full mt-1`} value={form.data.canal_origen} onChange={(e) => form.setData('canal_origen', e.target.value)}>
                                    {Object.entries(LABEL_CANAL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className={THEME_LABEL}>Documento origen</label>
                                <input className={`${THEME_INPUT} w-full mt-1`} value={form.data.documento_origen} onChange={(e) => form.setData('documento_origen', e.target.value)} />
                            </div>
                            <div>
                                <label className={THEME_LABEL}>Evidencias (obligatorio)</label>
                                <input
                                    type="file"
                                    required
                                    multiple
                                    className="block w-full text-sm mt-1"
                                    onChange={(e) => setEvidencias(Array.from(e.target.files || []))}
                                />
                                {form.errors.evidencias && <p className="text-xs font-bold text-rose-600 mt-1 m-0">{form.errors.evidencias}</p>}
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <button type="button" className={BTN_SECONDARY} onClick={() => setMostrarGenerar(false)}>Cancelar</button>
                                <button type="submit" disabled={form.processing} className={BTN_PRIMARY}>Guardar</button>
                            </div>
                        </form>
                    </div>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}

function ColaTabla({ rows, empty, columns, renderRow }) {
    return (
        <div className={geliaCardClass('overflow-x-auto')}>
            <table className="min-w-full">
                <thead className="theme-element">
                    <tr>
                        {columns.map((c) => <th key={c} className={TH}>{c}</th>)}
                        <th className={TH} />
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={columns.length + 1} className={`${TD} text-center theme-text-muted py-8`}>
                                <span className="inline-flex items-center gap-2"><AlertTriangle className="w-4 h-4" /> {empty}</span>
                            </td>
                        </tr>
                    )}
                    {rows.map((row) => (
                        <tr key={row.id}>{renderRow(row)}</tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
