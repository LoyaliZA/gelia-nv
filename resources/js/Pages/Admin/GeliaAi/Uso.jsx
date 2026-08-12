import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { ChartNoAxesColumn, MessageSquareText, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPageShell from '@/Components/GeliaPageShell';
import GeliaTituloCard from '@/Components/GeliaTituloCard';
import {
    geliaCardClass,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from '@/utils/geliaTheme';

function fmt(n) {
    return new Intl.NumberFormat('es-MX').format(n ?? 0);
}

export default function Uso({
    auth,
    desde,
    hasta,
    totales = {},
    ranking = [],
    por_mode: porMode = [],
    top_turnos: topTurnos = [],
}) {
    const [filtroDesde, setFiltroDesde] = useState(desde);
    const [filtroHasta, setFiltroHasta] = useState(hasta);
    const [modal, setModal] = useState(null);
    const [cargandoConv, setCargandoConv] = useState(false);

    const aplicar = (e) => {
        e.preventDefault();
        router.get(
            route('admin.gelia_ai.uso.index'),
            { desde: filtroDesde, hasta: filtroHasta },
            { preserveState: true, replace: true }
        );
    };

    const abrirConversacion = async (conversacionId) => {
        if (!conversacionId) return;
        setCargandoConv(true);
        setModal({ titulo: 'Cargando…', mensajes: [] });
        try {
            const { data } = await axios.get(
                route('admin.gelia_ai.uso.conversacion', conversacionId)
            );
            setModal({
                titulo: data.titulo || `Conversación #${data.id}`,
                mensajes: data.mensajes || [],
            });
        } catch {
            setModal({ titulo: 'Error', mensajes: [{ role: 'assistant', content: 'No se pudo cargar la conversación.' }] });
        } finally {
            setCargandoConv(false);
        }
    };

    const cards = [
        { label: 'Tokens totales', value: fmt(totales.total_tokens) },
        { label: 'Prompt', value: fmt(totales.prompt_tokens) },
        { label: 'Completion', value: fmt(totales.completion_tokens) },
        { label: 'Turnos', value: fmt(totales.turnos) },
        { label: 'Usuarios', value: fmt(totales.usuarios) },
    ];

    return (
        <AppLayout user={auth.user}>
            <Head title="Uso GELIA" />

            <GeliaPageShell className="space-y-8 py-6 md:py-10">
                <GeliaTituloCard
                    eyebrow="Administración"
                    title="Uso"
                    titleHighlight="GELIA"
                    description="Tokens por usuario, distribución por modo y turnos caros. Abre una conversación para auditar el texto del chat."
                    icon={ChartNoAxesColumn}
                />

                <form
                    onSubmit={aplicar}
                    className={geliaCardClass('p-4 md:p-5 flex flex-wrap items-end gap-3')}
                >
                    <div className="space-y-1">
                        <label className={THEME_LABEL}>Desde</label>
                        <input
                            type="date"
                            className={THEME_INPUT}
                            value={filtroDesde}
                            onChange={(e) => setFiltroDesde(e.target.value)}
                        />
                    </div>
                    <div className="space-y-1">
                        <label className={THEME_LABEL}>Hasta</label>
                        <input
                            type="date"
                            className={THEME_INPUT}
                            value={filtroHasta}
                            onChange={(e) => setFiltroHasta(e.target.value)}
                        />
                    </div>
                    <button type="submit" className={THEME_BTN_PRIMARY}>
                        Filtrar
                    </button>
                </form>

                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    {cards.map((c) => (
                        <div key={c.label} className={geliaCardClass('p-4')}>
                            <p className="text-[10px] uppercase tracking-wide theme-text-muted m-0">{c.label}</p>
                            <p className="text-xl font-bold theme-text-main m-0 mt-1 tabular-nums">{c.value}</p>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <section className={geliaCardClass('p-5 md:p-6 overflow-x-auto')}>
                        <h2 className="text-base font-bold theme-text-main m-0 mb-4">Ranking usuarios</h2>
                        {ranking.length === 0 ? (
                            <p className="text-sm theme-text-muted m-0">Sin uso en el rango.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-[10px] uppercase theme-text-muted border-b theme-border">
                                        <th className="py-2 pr-2 font-semibold">Usuario</th>
                                        <th className="py-2 pr-2 font-semibold text-right">Tokens</th>
                                        <th className="py-2 pr-2 font-semibold text-right">Turnos</th>
                                        <th className="py-2 font-semibold text-right">Prom.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ranking.map((r) => (
                                        <tr key={r.user_id} className="border-b theme-border last:border-0">
                                            <td className="py-2 pr-2">
                                                <p className="font-semibold theme-text-main m-0">{r.name}</p>
                                                <p className="text-[10px] theme-text-muted m-0">{r.email}</p>
                                            </td>
                                            <td className="py-2 pr-2 text-right tabular-nums">{fmt(r.total_tokens)}</td>
                                            <td className="py-2 pr-2 text-right tabular-nums">{fmt(r.turnos)}</td>
                                            <td className="py-2 text-right tabular-nums">{fmt(r.promedio_tokens)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>

                    <section className={geliaCardClass('p-5 md:p-6 overflow-x-auto')}>
                        <h2 className="text-base font-bold theme-text-main m-0 mb-4">Por modo</h2>
                        {porMode.length === 0 ? (
                            <p className="text-sm theme-text-muted m-0">Sin datos.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-[10px] uppercase theme-text-muted border-b theme-border">
                                        <th className="py-2 pr-2 font-semibold">Mode</th>
                                        <th className="py-2 pr-2 font-semibold text-right">Tokens</th>
                                        <th className="py-2 font-semibold text-right">Turnos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {porMode.map((m) => (
                                        <tr key={m.mode} className="border-b theme-border last:border-0">
                                            <td className="py-2 pr-2 font-mono text-xs">{m.mode}</td>
                                            <td className="py-2 pr-2 text-right tabular-nums">{fmt(m.total_tokens)}</td>
                                            <td className="py-2 text-right tabular-nums">{fmt(m.turnos)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>
                </div>

                <section className={geliaCardClass('p-5 md:p-6 overflow-x-auto')}>
                    <h2 className="text-base font-bold theme-text-main m-0 mb-4">Turnos caros</h2>
                    {topTurnos.length === 0 ? (
                        <p className="text-sm theme-text-muted m-0">Sin turnos.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[10px] uppercase theme-text-muted border-b theme-border">
                                    <th className="py-2 pr-2 font-semibold">Usuario</th>
                                    <th className="py-2 pr-2 font-semibold">Mode</th>
                                    <th className="py-2 pr-2 font-semibold text-right">Tokens</th>
                                    <th className="py-2 pr-2 font-semibold text-right">Rounds</th>
                                    <th className="py-2 pr-2 font-semibold">Fecha</th>
                                    <th className="py-2 font-semibold">Chat</th>
                                </tr>
                            </thead>
                            <tbody>
                                {topTurnos.map((t) => (
                                    <tr key={t.id} className="border-b theme-border last:border-0">
                                        <td className="py-2 pr-2">
                                            <p className="font-semibold theme-text-main m-0">{t.user_name}</p>
                                        </td>
                                        <td className="py-2 pr-2 font-mono text-xs">{t.mode || '—'}</td>
                                        <td className="py-2 pr-2 text-right tabular-nums">{fmt(t.total_tokens)}</td>
                                        <td className="py-2 pr-2 text-right tabular-nums">{t.rounds}</td>
                                        <td className="py-2 pr-2 text-xs theme-text-muted whitespace-nowrap">
                                            {t.created_at ? new Date(t.created_at).toLocaleString('es-MX') : '—'}
                                        </td>
                                        <td className="py-2">
                                            {t.conversacion_id ? (
                                                <button
                                                    type="button"
                                                    className={`${THEME_BTN_SECONDARY} !px-2 !py-1 inline-flex items-center gap-1`}
                                                    onClick={() => abrirConversacion(t.conversacion_id)}
                                                    disabled={cargandoConv}
                                                >
                                                    <MessageSquareText className="w-3.5 h-3.5" />
                                                    Ver
                                                </button>
                                            ) : (
                                                <span className="text-xs theme-text-muted">—</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </GeliaPageShell>

            {modal && (
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModal(null)} role="presentation">
                    <div
                        className={`${THEME_MODAL_SHELL} ${geliaCardClass('max-w-xl w-full max-h-[80vh] overflow-hidden flex flex-col')}`}
                        onClick={(e) => e.stopPropagation()}
                        role="dialog"
                        aria-modal="true"
                    >
                        <div className="flex items-center justify-between gap-3 p-4 border-b theme-border">
                            <h3 className="text-sm font-bold theme-text-main m-0 truncate">{modal.titulo}</h3>
                            <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setModal(null)} aria-label="Cerrar">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        <div className="p-4 space-y-3 overflow-y-auto flex-1">
                            {modal.mensajes.length === 0 && (
                                <p className="text-sm theme-text-muted m-0">Sin mensajes.</p>
                            )}
                            {modal.mensajes.map((m) => (
                                <div key={m.id || `${m.role}-${m.created_at}`} className="space-y-1">
                                    <p className="text-[10px] uppercase font-bold theme-text-muted m-0">{m.role}</p>
                                    <p className="text-sm theme-text-main m-0 whitespace-pre-wrap">{m.content}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
