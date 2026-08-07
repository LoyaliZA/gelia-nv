import React, { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Wallet } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import { geliaCardClass } from '../../utils/geliaTheme';
import {
    BTN_PRIMARY,
    BTN_SECONDARY,
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

const KPI = ({ label, valor, tone }) => (
    <div className={geliaCardClass('p-4')}>
        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
        <p className={`text-xl font-black m-0 mt-1 ${tone || 'theme-text-main'}`}>{fmtMoneda(valor)}</p>
    </div>
);

export default function Cuenta({ auth, cliente, cuenta, creditos = [], movimientos = [], motivos = [] }) {
    const { flash } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p) || auth?.user?.roles?.includes('Super Admin');
    const [creditoAccion, setCreditoAccion] = useState(null);
    const [modo, setModo] = useState(null);

    const formRevision = useForm({ estado_revision: 'revisado', observaciones: '' });
    const formAjuste = useForm({ monto_delta: '', saf_motivo_id: '', observaciones: '' });
    const formCancelar = useForm({ observaciones: '' });
    const formReactivar = useForm({ observaciones: '' });
    const formRevertir = useForm({ monto: '', saf_pedido_aplicacion_id: '', observaciones: '' });

    const abrir = (credito, tipo) => {
        setCreditoAccion(credito);
        setModo(tipo);
        formRevision.reset();
        formAjuste.reset();
        formCancelar.reset();
        formReactivar.reset();
        formRevertir.reset();
        if (tipo === 'revertir') {
            const apps = credito.aplicaciones_pedido || credito.aplicacionesPedido || [];
            if (apps.length === 1) {
                formRevertir.setData({
                    monto: String(apps[0].monto),
                    saf_pedido_aplicacion_id: String(apps[0].id),
                    observaciones: '',
                });
            } else if (credito.monto_aplicado) {
                formRevertir.setData('monto', String(credito.monto_aplicado));
            }
        }
    };

    const cerrar = () => {
        setModo(null);
        setCreditoAccion(null);
    };

    const appsCredito = creditoAccion
        ? (creditoAccion.aplicaciones_pedido || creditoAccion.aplicacionesPedido || [])
        : [];

    return (
        <AppLayout auth={auth}>
            <Head title={`Cuenta SAF · ${cliente.nombre}`} />
            <GeliaPageShell className="space-y-6">
                <div>
                    <Link
                        href={route('saldos_favor.index')}
                        className="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest theme-text-muted hover:opacity-80"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" /> Saldos a favor
                    </Link>
                </div>

                <GeliaTituloCard
                    eyebrow={`Cliente #${cliente.numero_cliente}`}
                    title={cliente.nombre}
                    description={`Cuenta unificada · moneda ${cuenta.moneda}`}
                    icon={Wallet}
                />

                {flash?.success && <div className={FLASH_OK}>{flash.success}</div>}

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <KPI label="Disponible" valor={cuenta.disponible} tone="text-emerald-600 dark:text-emerald-400" />
                    <KPI label="Reservado" valor={cuenta.reservado} tone="text-amber-600 dark:text-amber-400" />
                    <KPI label="Aplicado" valor={cuenta.aplicado} />
                    <KPI label="Vencido" valor={cuenta.vencido} tone="text-rose-600 dark:text-rose-400" />
                </div>

                <div className={geliaCardClass('overflow-x-auto')}>
                    <div className="px-4 py-3 border-b theme-border">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Saldos a favor</p>
                    </div>
                    <table className="min-w-full">
                        <thead className="theme-element">
                            <tr>
                                <th className={TH}>Folio</th>
                                <th className={TH}>Origen</th>
                                <th className={TH}>Motivo / Doc</th>
                                <th className={TH}>Original</th>
                                <th className={TH}>Disponible</th>
                                <th className={TH}>Reservado</th>
                                <th className={TH}>Estados</th>
                                <th className={TH}>Vence</th>
                                <th className={TH} />
                            </tr>
                        </thead>
                        <tbody>
                            {creditos.map((c) => (
                                <tr key={c.id}>
                                    <td className={`${TD} font-bold`}>{c.folio}</td>
                                    <td className={TD}>{LABEL_CANAL[c.canal_origen] || c.canal_origen || '—'}</td>
                                    <td className={TD}>
                                        <div className="text-xs font-bold">{c.motivo?.nombre || '—'}</div>
                                        <div className="text-[10px] theme-text-muted">{c.documento_origen || ''}</div>
                                    </td>
                                    <td className={TD}>{fmtMoneda(c.monto_original)}</td>
                                    <td className={`${TD} font-bold`}>{fmtMoneda(c.monto_disponible)}</td>
                                    <td className={TD}>{fmtMoneda(c.monto_reservado)}</td>
                                    <td className={TD}>
                                        <div className="text-xs font-bold">{LABEL_ESTADO_FIN[c.estado_financiero] || c.estado_financiero}</div>
                                        <div className="text-[10px] font-bold uppercase tracking-wide theme-text-muted">
                                            {LABEL_ESTADO_REV[c.estado_revision] || c.estado_revision}
                                        </div>
                                    </td>
                                    <td className={TD}>{c.fecha_vencimiento}</td>
                                    <td className={`${TD} text-right whitespace-nowrap space-x-2`}>
                                        {can('saldos_favor.revisar') && (
                                            <button type="button" className="text-[10px] font-black uppercase tracking-wide" style={{ color: 'var(--color-primario)' }} onClick={() => abrir(c, 'revisar')}>Revisar</button>
                                        )}
                                        {can('saldos_favor.ajustar') && (
                                            <button type="button" className="text-[10px] font-black uppercase tracking-wide text-amber-700" onClick={() => abrir(c, 'ajustar')}>Ajustar</button>
                                        )}
                                        {can('saldos_favor.ajustar') && c.estado_financiero === 'vencido' && Number(c.monto_disponible) > 0 && (
                                            <button type="button" className="text-[10px] font-black uppercase tracking-wide text-emerald-700" onClick={() => abrir(c, 'reactivar')}>Reactivar</button>
                                        )}
                                        {can('saldos_favor.ajustar') && Number(c.monto_aplicado) > 0 && (
                                            <button type="button" className="text-[10px] font-black uppercase tracking-wide text-sky-700" onClick={() => abrir(c, 'revertir')}>Revertir</button>
                                        )}
                                        {can('saldos_favor.cancelar') && c.estado_financiero !== 'cancelado' && (
                                            <button type="button" className="text-[10px] font-black uppercase tracking-wide text-rose-700" onClick={() => abrir(c, 'cancelar')}>Cancelar</button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className={geliaCardClass('overflow-x-auto')}>
                    <div className="px-4 py-3 border-b theme-border">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Bitácora reciente</p>
                    </div>
                    <table className="min-w-full">
                        <thead className="theme-element">
                            <tr>
                                <th className={TH}>Fecha</th>
                                <th className={TH}>Saldo</th>
                                <th className={TH}>Tipo</th>
                                <th className={TH}>Monto</th>
                                <th className={TH}>Saldo</th>
                                <th className={TH}>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            {movimientos.map((m) => (
                                <tr key={m.id}>
                                    <td className={TD}>{m.created_at}</td>
                                    <td className={`${TD} font-bold`}>{m.credito?.folio}</td>
                                    <td className={TD}>{m.tipo}</td>
                                    <td className={TD}>{fmtMoneda(m.monto)}</td>
                                    <td className={TD}>{fmtMoneda(m.saldo_anterior)} → {fmtMoneda(m.saldo_posterior)}</td>
                                    <td className={TD}>{m.usuario?.name || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {creditoAccion && modo && (
                    <div className={THEME_MODAL_OVERLAY} role="dialog" aria-modal="true">
                        <div className={`${THEME_MODAL_SHELL} w-full max-w-md p-6 space-y-4`}>
                            <h2 className="text-lg font-black italic uppercase tracking-tight theme-text-main m-0 capitalize">
                                {modo} · {creditoAccion.folio}
                            </h2>
                            {modo === 'revisar' && (
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        formRevision.post(route('saldos_favor.revisar', creditoAccion.id), { onSuccess: cerrar });
                                    }}
                                    className="space-y-3"
                                >
                                    <select className={`${THEME_SELECT} w-full`} value={formRevision.data.estado_revision} onChange={(e) => formRevision.setData('estado_revision', e.target.value)}>
                                        <option value="revisado">Revisado correctamente</option>
                                        <option value="con_diferencia">Con diferencia</option>
                                        <option value="requiere_correccion">Requiere corrección</option>
                                        <option value="rechazado">Rechazado</option>
                                    </select>
                                    <textarea className={`${THEME_TEXTAREA} w-full`} placeholder="Observaciones" value={formRevision.data.observaciones} onChange={(e) => formRevision.setData('observaciones', e.target.value)} />
                                    <div className="flex justify-end gap-2">
                                        <button type="button" className={BTN_SECONDARY} onClick={cerrar}>Cerrar</button>
                                        <button type="submit" className={BTN_PRIMARY}>Guardar</button>
                                    </div>
                                </form>
                            )}
                            {modo === 'ajustar' && (
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        formAjuste.post(route('saldos_favor.ajustar', creditoAccion.id), { onSuccess: cerrar });
                                    }}
                                    className="space-y-3"
                                >
                                    <div>
                                        <label className={THEME_LABEL}>Delta (+/−)</label>
                                        <input type="number" step="0.01" required className={`${THEME_INPUT} w-full mt-1`} value={formAjuste.data.monto_delta} onChange={(e) => formAjuste.setData('monto_delta', e.target.value)} />
                                    </div>
                                    <select required className={`${THEME_SELECT} w-full`} value={formAjuste.data.saf_motivo_id} onChange={(e) => formAjuste.setData('saf_motivo_id', e.target.value)}>
                                        <option value="">Motivo</option>
                                        {groupMotivosByCategoria(motivos).map(([cat, items]) => (
                                            <optgroup key={cat} label={LABEL_CATEGORIA_MOTIVO[cat] || cat}>
                                                {items.map((m) => <option key={m.id} value={m.id}>{m.nombre}</option>)}
                                            </optgroup>
                                        ))}
                                    </select>
                                    <textarea required className={`${THEME_TEXTAREA} w-full`} placeholder="Observaciones / evidencia" value={formAjuste.data.observaciones} onChange={(e) => formAjuste.setData('observaciones', e.target.value)} />
                                    <div className="flex justify-end gap-2">
                                        <button type="button" className={BTN_SECONDARY} onClick={cerrar}>Cerrar</button>
                                        <button type="submit" className={BTN_PRIMARY}>Aplicar ajuste</button>
                                    </div>
                                </form>
                            )}
                            {modo === 'cancelar' && (
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        formCancelar.post(route('saldos_favor.cancelar', creditoAccion.id), { onSuccess: cerrar });
                                    }}
                                    className="space-y-3"
                                >
                                    <textarea required className={`${THEME_TEXTAREA} w-full`} placeholder="Motivo de cancelación" value={formCancelar.data.observaciones} onChange={(e) => formCancelar.setData('observaciones', e.target.value)} />
                                    <div className="flex justify-end gap-2">
                                        <button type="button" className={BTN_SECONDARY} onClick={cerrar}>Cerrar</button>
                                        <button type="submit" className={`${BTN_PRIMARY} !bg-rose-600`}>Cancelar saldo</button>
                                    </div>
                                </form>
                            )}
                            {modo === 'reactivar' && (
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        formReactivar.post(route('saldos_favor.reactivar', creditoAccion.id), { onSuccess: cerrar });
                                    }}
                                    className="space-y-3"
                                >
                                    <p className="text-sm theme-text-muted m-0">Reactiva el remanente vencido ({fmtMoneda(creditoAccion.monto_disponible)}) con nueva vigencia de 365 días.</p>
                                    <textarea className={`${THEME_TEXTAREA} w-full`} placeholder="Observaciones" value={formReactivar.data.observaciones} onChange={(e) => formReactivar.setData('observaciones', e.target.value)} />
                                    <div className="flex justify-end gap-2">
                                        <button type="button" className={BTN_SECONDARY} onClick={cerrar}>Cerrar</button>
                                        <button type="submit" className={BTN_PRIMARY}>Reactivar</button>
                                    </div>
                                </form>
                            )}
                            {modo === 'revertir' && (
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        formRevertir.post(route('saldos_favor.revertir_aplicacion', creditoAccion.id), { onSuccess: cerrar });
                                    }}
                                    className="space-y-3"
                                >
                                    {appsCredito.length > 0 ? (
                                        <div>
                                            <label className={THEME_LABEL}>Aplicación a revertir</label>
                                            <select
                                                className={`${THEME_SELECT} w-full mt-1`}
                                                value={formRevertir.data.saf_pedido_aplicacion_id}
                                                onChange={(e) => {
                                                    const app = appsCredito.find((a) => String(a.id) === e.target.value);
                                                    formRevertir.setData({
                                                        ...formRevertir.data,
                                                        saf_pedido_aplicacion_id: e.target.value,
                                                        monto: app ? String(app.monto) : formRevertir.data.monto,
                                                    });
                                                }}
                                            >
                                                <option value="">Seleccionar…</option>
                                                {appsCredito.map((a) => (
                                                    <option key={a.id} value={a.id}>
                                                        #{a.id} · pedido {a.pedido_bma_id} · {fmtMoneda(a.monto)}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    ) : null}
                                    <div>
                                        <label className={THEME_LABEL}>Monto a revertir</label>
                                        <input type="number" step="0.01" required className={`${THEME_INPUT} w-full mt-1`} value={formRevertir.data.monto} onChange={(e) => formRevertir.setData('monto', e.target.value)} />
                                    </div>
                                    <textarea required className={`${THEME_TEXTAREA} w-full`} placeholder="Motivo de reversión" value={formRevertir.data.observaciones} onChange={(e) => formRevertir.setData('observaciones', e.target.value)} />
                                    <div className="flex justify-end gap-2">
                                        <button type="button" className={BTN_SECONDARY} onClick={cerrar}>Cerrar</button>
                                        <button type="submit" className={BTN_PRIMARY}>Revertir aplicación</button>
                                    </div>
                                </form>
                            )}
                        </div>
                    </div>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}
