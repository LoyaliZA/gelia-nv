import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Store, Layers, Printer, PlusCircle, ChevronDown, ChevronUp } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import { geliaCardClass } from '../../utils/geliaTheme';
import {
    BTN_BACK,
    BTN_PRIMARY,
    BTN_SECONDARY,
    FLASH_ERR,
    FLASH_OK,
    LABEL_CANAL,
    LABEL_CATEGORIA_MOTIVO,
    groupMotivosByCategoria,
    TD,
    TH,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
    THEME_TEXTAREA,
    fmtFecha,
    fmtMoneda,
} from './Partials/safStyles';

export default function Caja({
    auth,
    cliente = null,
    cuenta = null,
    motivos = [],
    sucursales = [],
    preferencia,
    comprobantes_recientes = [],
}) {
    const { flash } = usePage().props;
    const [clienteQuery, setClienteQuery] = useState(cliente ? `${cliente.numero_cliente} — ${cliente.nombre}` : '');
    const [resultados, setResultados] = useState([]);
    const [montoAplicar, setMontoAplicar] = useState('');
    const [fifoItems, setFifoItems] = useState([]);
    const [mostrarGenerar, setMostrarGenerar] = useState(false);
    const [evidencias, setEvidencias] = useState([]);

    const formGenerar = useForm({
        cliente_id: cliente?.id || '',
        monto: '',
        saf_motivo_id: '',
        detalle_motivo: '',
        documento_origen: '',
        observaciones: '',
    });

    const formAplicar = useForm({
        cliente_id: cliente?.id || '',
        referencia_venta: '',
        sucursal: preferencia?.sucursal || '',
        caja: preferencia?.caja || '',
        perfil_impresion: preferencia?.perfil || '80mm',
        items: [],
    });

    useEffect(() => {
        if (cliente?.id) {
            formGenerar.setData('cliente_id', cliente.id);
            formAplicar.setData('cliente_id', cliente.id);
        }
    }, [cliente?.id]);

    useEffect(() => {
        if (preferencia?.sucursal) formAplicar.setData('sucursal', preferencia.sucursal);
        if (preferencia?.caja) formAplicar.setData('caja', preferencia.caja);
        if (preferencia?.perfil) formAplicar.setData('perfil_impresion', preferencia.perfil);
    }, [preferencia?.sucursal, preferencia?.caja, preferencia?.perfil]);

    const motivoSeleccionado = useMemo(
        () => motivos.find((m) => String(m.id) === String(formGenerar.data.saf_motivo_id)),
        [motivos, formGenerar.data.saf_motivo_id]
    );

    const buscar = async (valor) => {
        setClienteQuery(valor);
        if (!valor) {
            setResultados([]);
            return;
        }
        const res = await fetch(route('saldos_favor.buscar_cliente', { q: valor }), { headers: { Accept: 'application/json' } });
        const json = await res.json();
        setResultados(json.data || []);
    };

    const seleccionarCliente = (c) => {
        setClienteQuery(`${c.numero_cliente} — ${c.nombre}`);
        setResultados([]);
        setMontoAplicar('');
        setFifoItems([]);
        router.get(route('saldos_favor.caja.index'), { cliente_id: c.id }, { preserveState: true });
    };

    const totalSeleccionado = useMemo(
        () => fifoItems.reduce((acc, i) => acc + (Number(i.monto) || 0), 0),
        [fifoItems]
    );

    const aplicarFifo = async (monto) => {
        if (!cliente?.id) return;
        const m = Number(monto) || 0;
        if (m <= 0) {
            setFifoItems([]);
            return;
        }
        const res = await fetch(`${route('saldos_favor.api.sugerir', cliente.id)}?monto=${encodeURIComponent(m)}`, {
            headers: { Accept: 'application/json' },
        });
        const json = await res.json();
        setFifoItems(json.items || []);
    };

    const aplicarTodo = () => {
        const disp = Number(cuenta?.disponible || 0);
        setMontoAplicar(String(disp));
        aplicarFifo(disp);
    };

    const enviarAplicacion = (e) => {
        e.preventDefault();
        const items = fifoItems
            .map((i) => ({ saf_credito_id: i.saf_credito_id, monto: Number(i.monto || 0) }))
            .filter((i) => i.monto > 0);
        formAplicar.transform((data) => ({ ...data, items }));
        formAplicar.post(route('saldos_favor.caja.aplicar'));
    };

    const enviarGenerar = (e) => {
        e.preventDefault();
        formGenerar.transform((data) => ({
            ...data,
            sucursal: formAplicar.data.sucursal || preferencia?.sucursal || '',
            evidencias,
        }));
        formGenerar.post(route('saldos_favor.caja.generar'), {
            forceFormData: true,
            onSuccess: () => {
                formGenerar.reset('monto', 'saf_motivo_id', 'detalle_motivo', 'documento_origen', 'observaciones');
                setEvidencias([]);
                setMostrarGenerar(false);
            },
        });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Caja · Saldos a favor" />
            <GeliaPageShell className="space-y-6">
                <div>
                    <Link href={route('saldos_favor.index')} className={BTN_BACK}>
                        <ArrowLeft className="w-3.5 h-3.5" /> Saldos a favor
                    </Link>
                </div>

                <GeliaTituloCard
                    eyebrow="Punto de venta"
                    title="Caja"
                    titleHighlight="saldos"
                    description="Identificar cliente · aplicar saldo · comprobante térmico"
                    icon={Store}
                />

                {flash?.success && <div className={FLASH_OK}>{flash.success}</div>}
                {flash?.error && <div className={FLASH_ERR}>{flash.error}</div>}

                <div className={geliaCardClass('p-5 space-y-2')}>
                    <label className={THEME_LABEL}>Identificar cliente (obligatorio — no público general)</label>
                    <input
                        className={`${THEME_INPUT} w-full`}
                        value={clienteQuery}
                        onChange={(e) => buscar(e.target.value)}
                        placeholder="Número o nombre"
                    />
                    {resultados.length > 0 && (
                        <div className="border theme-border rounded-xl max-h-48 overflow-auto theme-surface">
                            {resultados.map((c) => (
                                <button
                                    type="button"
                                    key={c.id}
                                    className="block w-full text-left px-3 py-2.5 text-sm theme-text-main hover:bg-[color-mix(in_srgb,var(--color-primario)_8%,transparent)]"
                                    onClick={() => seleccionarCliente(c)}
                                >
                                    <span className="font-bold">{c.numero_cliente}</span>
                                    <span className="theme-text-muted"> — {c.nombre}</span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {cliente && cuenta && (
                    <div className="space-y-4">
                        <div className={geliaCardClass('p-5 space-y-4')}>
                            <div className="flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Cuenta disponible</p>
                                    <p className="text-3xl font-black theme-text-main m-0 mt-1">{fmtMoneda(cuenta.disponible)}</p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <button type="button" className={BTN_SECONDARY} onClick={aplicarTodo}>
                                        <Layers className="w-4 h-4" /> Aplicar todo (FIFO)
                                    </button>
                                </div>
                            </div>

                            <form onSubmit={enviarAplicacion} className="space-y-3">
                                <div>
                                    <label className={THEME_LABEL}>Monto a aplicar (FIFO automático)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        className={`${THEME_INPUT} w-full mt-1`}
                                        value={montoAplicar}
                                        onChange={(e) => {
                                            setMontoAplicar(e.target.value);
                                            aplicarFifo(e.target.value);
                                        }}
                                        placeholder="0.00"
                                    />
                                    <p className="text-xs theme-text-muted m-0 mt-1">Se aplica el saldo más antiguo primero. No se elige crédito a crédito.</p>
                                </div>
                                <div>
                                    <label className={THEME_LABEL}>Referencia venta / ticket</label>
                                    <input className={`${THEME_INPUT} w-full mt-1`} value={formAplicar.data.referencia_venta} onChange={(e) => formAplicar.setData('referencia_venta', e.target.value)} />
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <label className={THEME_LABEL}>Sucursal</label>
                                        <select
                                            required
                                            className={`${THEME_SELECT} w-full mt-1`}
                                            value={formAplicar.data.sucursal}
                                            onChange={(e) => formAplicar.setData('sucursal', e.target.value)}
                                        >
                                            <option value="">Seleccionar…</option>
                                            {sucursales.map((s) => (
                                                <option key={s.id} value={s.nombre}>{s.nombre}</option>
                                            ))}
                                        </select>
                                        {formAplicar.errors.sucursal && <p className="text-xs text-rose-600 font-bold m-0 mt-1">{formAplicar.errors.sucursal}</p>}
                                    </div>
                                    <div>
                                        <label className={THEME_LABEL}>Caja</label>
                                        <input
                                            required
                                            className={`${THEME_INPUT} w-full mt-1`}
                                            value={formAplicar.data.caja}
                                            onChange={(e) => formAplicar.setData('caja', e.target.value)}
                                            placeholder="01"
                                        />
                                        {formAplicar.errors.caja && <p className="text-xs text-rose-600 font-bold m-0 mt-1">{formAplicar.errors.caja}</p>}
                                    </div>
                                </div>
                                <div>
                                    <label className={THEME_LABEL}>Perfil impresión</label>
                                    <select className={`${THEME_SELECT} w-full mt-1`} value={formAplicar.data.perfil_impresion} onChange={(e) => formAplicar.setData('perfil_impresion', e.target.value)}>
                                        <option value="80mm">Receipt 80 mm</option>
                                        <option value="58mm">Receipt 58 mm</option>
                                        <option value="carta">Carta / A4</option>
                                    </select>
                                </div>
                                <div className="space-y-2 max-h-64 overflow-auto">
                                    {fifoItems.length === 0 ? (
                                        <p className="text-xs theme-text-muted m-0">Indique un monto para ver la distribución FIFO.</p>
                                    ) : fifoItems.map((c) => {
                                        const parcial = Number(c.monto) + 0.001 < Number(c.disponible);
                                        return (
                                            <div key={c.saf_credito_id} className="border theme-border rounded-xl p-3 text-sm flex items-center justify-between gap-2 theme-element">
                                                <div className="min-w-0">
                                                    <div className="font-bold theme-text-main">{c.folio}</div>
                                                    <div className="text-[10px] font-bold uppercase tracking-wide theme-text-muted">
                                                        {LABEL_CANAL[c.canal_origen] || c.canal_origen} · vence {fmtFecha(c.fecha_vencimiento)} · disp. {fmtMoneda(c.disponible)}
                                                    </div>
                                                    {parcial && (
                                                        <div className="text-[10px] font-bold text-amber-600 mt-1">Se sugiere usar el saldo completo</div>
                                                    )}
                                                </div>
                                                <div className="font-black">{fmtMoneda(c.monto)}</div>
                                            </div>
                                        );
                                    })}
                                </div>
                                <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
                                    <p className="font-black theme-text-main m-0">Total: {fmtMoneda(totalSeleccionado)}</p>
                                    <button type="submit" disabled={formAplicar.processing || totalSeleccionado <= 0} className={`${BTN_PRIMARY} disabled:opacity-50`}>
                                        <Printer className="w-4 h-4" /> Reservar, aplicar e imprimir
                                    </button>
                                </div>
                                {formAplicar.errors.items && <p className="text-xs text-rose-600 font-bold m-0">{formAplicar.errors.items}</p>}
                            </form>
                        </div>

                        <div className={geliaCardClass('p-5 space-y-3')}>
                            <button type="button" className={`${BTN_SECONDARY} w-full`} onClick={() => setMostrarGenerar((v) => !v)}>
                                {mostrarGenerar ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                                {mostrarGenerar ? 'Ocultar generación' : 'Generar saldo a favor en Caja'}
                            </button>
                            {mostrarGenerar && (
                                <form onSubmit={enviarGenerar} className="space-y-3 pt-2 border-t theme-border">
                                    <div>
                                        <label className={THEME_LABEL}>Monto</label>
                                        <input type="number" step="0.01" required className={`${THEME_INPUT} w-full mt-1`} value={formGenerar.data.monto} onChange={(e) => formGenerar.setData('monto', e.target.value)} />
                                    </div>
                                    <div>
                                        <label className={THEME_LABEL}>Motivo</label>
                                        <select required className={`${THEME_SELECT} w-full mt-1`} value={formGenerar.data.saf_motivo_id} onChange={(e) => formGenerar.setData('saf_motivo_id', e.target.value)}>
                                            <option value="">Seleccionar…</option>
                                            {groupMotivosByCategoria(motivos).map(([cat, items]) => (
                                                <optgroup key={cat} label={LABEL_CATEGORIA_MOTIVO[cat] || cat}>
                                                    {items.map((m) => <option key={m.id} value={m.id}>{m.nombre}</option>)}
                                                </optgroup>
                                            ))}
                                        </select>
                                    </div>
                                    {motivoSeleccionado?.requiere_detalle && (
                                        <div>
                                            <label className={THEME_LABEL}>Detalle obligatorio</label>
                                            <textarea required className={`${THEME_TEXTAREA} w-full mt-1`} value={formGenerar.data.detalle_motivo} onChange={(e) => formGenerar.setData('detalle_motivo', e.target.value)} />
                                        </div>
                                    )}
                                    <div>
                                        <label className={THEME_LABEL}>Ticket / documento origen</label>
                                        <input className={`${THEME_INPUT} w-full mt-1`} value={formGenerar.data.documento_origen} onChange={(e) => formGenerar.setData('documento_origen', e.target.value)} placeholder="Ej. ticket 58421" />
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
                                        {formGenerar.errors.evidencias && <p className="text-xs text-rose-600 font-bold m-0 mt-1">{formGenerar.errors.evidencias}</p>}
                                    </div>
                                    <button type="submit" disabled={formGenerar.processing} className={BTN_PRIMARY}>
                                        <PlusCircle className="w-4 h-4" /> Generar
                                    </button>
                                    {formGenerar.errors.monto && <p className="text-xs text-rose-600 font-bold m-0">{formGenerar.errors.monto}</p>}
                                </form>
                            )}
                        </div>
                    </div>
                )}

                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="px-4 py-3 border-b theme-border">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Mis comprobantes de hoy</p>
                    </div>
                    <table className="table-fixed w-full">
                        <thead className="theme-element">
                            <tr>
                                <th className={`${TH} w-[22%]`}>Folio</th>
                                <th className={`${TH} w-[30%]`}>Cliente</th>
                                <th className={`${TH} w-[18%]`}>Monto</th>
                                <th className={`${TH} w-[18%]`}>Estado</th>
                                <th className={`${TH} w-[12%]`} />
                            </tr>
                        </thead>
                        <tbody>
                            {comprobantes_recientes.length === 0 && (
                                <tr>
                                    <td colSpan={5} className={`${TD} text-center theme-text-muted py-6`}>Sin comprobantes hoy.</td>
                                </tr>
                            )}
                            {comprobantes_recientes.map((c) => (
                                <tr key={c.id}>
                                    <td className={`${TD} font-bold`}>{c.folio}</td>
                                    <td className={TD}>{c.cliente?.nombre}</td>
                                    <td className={TD}>{fmtMoneda(c.monto_aplicado)}</td>
                                    <td className={TD}>{c.estado}</td>
                                    <td className={`${TD} text-right`}>
                                        <Link href={route('saldos_favor.caja.comprobante', c.id)} className="text-[10px] font-black uppercase tracking-wide" style={{ color: 'var(--color-primario)' }}>
                                            Ver
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}
