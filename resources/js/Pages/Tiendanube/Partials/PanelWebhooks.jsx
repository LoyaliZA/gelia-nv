import React, { useCallback, useEffect, useState } from 'react';
import { Link2, Plus, Trash2 } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';

export default function PanelWebhooks({
    permisos,
    credencialesOk,
    webhookUrl = '',
    eventosRecomendados = [],
    embedded = false,
}) {
    const [webhooks, setWebhooks] = useState([]);
    const [entregas, setEntregas] = useState([]);
    const [event, setEvent] = useState(eventosRecomendados[0] || 'product/updated');
    const [url, setUrl] = useState(webhookUrl);
    const [loading, setLoading] = useState(false);
    const [aplicando, setAplicando] = useState(false);
    const [error, setError] = useState(null);
    const [mensaje, setMensaje] = useState(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const cargarEntregas = useCallback(async () => {
        if (!permisos.configurar) return;
        try {
            const res = await fetch(route('tiendanube.webhooks.entregas'), {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok || !data.success) return;
            setEntregas(data.entregas || []);
        } catch {
            // diagnóstico opcional
        }
    }, [permisos.configurar]);

    const cargar = useCallback(async () => {
        if (!permisos.configurar || !credencialesOk) return;
        setLoading(true);
        setError(null);
        try {
            const res = await fetch(route('tiendanube.webhooks.index'), {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'No se pudieron listar los webhooks.');
            }
            setWebhooks(data.webhooks || []);
            if (data.webhook_url) setUrl(data.webhook_url);
            await cargarEntregas();
        } catch (err) {
            setError(err.message || 'Error al listar webhooks.');
        } finally {
            setLoading(false);
        }
    }, [permisos.configurar, credencialesOk, cargarEntregas]);

    useEffect(() => {
        cargar();
        if (permisos.configurar && !credencialesOk) {
            cargarEntregas();
        }
    }, [cargar, cargarEntregas, permisos.configurar, credencialesOk]);

    useEffect(() => {
        if (webhookUrl) setUrl(webhookUrl);
    }, [webhookUrl]);

    const crear = async (e) => {
        e.preventDefault();
        if (!permisos.configurar || !credencialesOk) return;
        setError(null);
        setMensaje(null);
        setLoading(true);
        try {
            const res = await fetch(route('tiendanube.webhooks.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ event, url: url || undefined }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'No se pudo crear el webhook.');
            }
            setMensaje(data.message);
            await cargar();
        } catch (err) {
            setError(err.message || 'Error al crear webhook.');
        } finally {
            setLoading(false);
        }
    };

    const eliminar = async (id) => {
        if (!permisos.configurar || !credencialesOk) return;
        if (!window.confirm('¿Eliminar este webhook en Tiendanube?')) return;
        setError(null);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.webhooks.destroy', id), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'No se pudo eliminar.');
            }
            setMensaje(data.message);
            await cargar();
        } catch (err) {
            setError(err.message || 'Error al eliminar webhook.');
        }
    };

    const aplicarRecomendados = async () => {
        if (!permisos.configurar || !credencialesOk) return;
        setAplicando(true);
        setError(null);
        setMensaje(null);
        try {
            const res = await fetch(route('tiendanube.webhooks.aplicar_recomendados'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ url: url || undefined }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'No se pudieron aplicar los webhooks.');
            }
            const r = data.resultado || {};
            setMensaje(
                `Creados: ${(r.creados || []).length}. Ya existentes: ${(r.ya_existentes || []).length}. Errores: ${(r.errores || []).length}.`
            );
            if ((r.errores || []).length) {
                setError(r.errores.map((x) => `${x.event}: ${x.message}`).join(' · '));
            }
            await cargar();
        } catch (err) {
            setError(err.message || 'Error al aplicar recomendados.');
        } finally {
            setAplicando(false);
        }
    };

    if (!permisos.configurar) return null;

    const shell = embedded ? 'space-y-4' : `${geliaCardClass()} p-5 space-y-4`;

    return (
        <div className={shell}>
            <div className="flex items-start gap-3">
                <Link2 className="w-6 h-6 shrink-0 mt-0.5" style={{ color: 'var(--color-primario)' }} />
                <div>
                    <h3 className="text-sm font-black uppercase tracking-widest theme-text-main">Webhooks</h3>
                    <p className="text-xs theme-text-muted mt-1">
                        Suscripciones en Tiendanube. Receptor público: {webhookUrl || url || '—'}
                    </p>
                    <p className="text-xs theme-text-muted mt-1">
                        Privacidad (Partners) ≠ eventos de producto; el catálogo usa las suscripciones de esta lista.
                    </p>
                </div>
            </div>

            {!credencialesOk && (
                <p className="text-xs text-amber-600">Configura credenciales antes de gestionar webhooks.</p>
            )}

            {error && <p className="text-xs text-red-600">{error}</p>}
            {mensaje && <p className="text-xs text-emerald-600">{mensaje}</p>}

            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={aplicarRecomendados}
                    disabled={!credencialesOk || aplicando}
                    className="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    {aplicando ? 'Aplicando…' : 'Aplicar recomendados'}
                </button>
                <button
                    type="button"
                    onClick={cargar}
                    disabled={!credencialesOk || loading}
                    className="px-4 py-2 rounded-xl border theme-border text-[10px] font-black uppercase tracking-widest theme-text-main disabled:opacity-50"
                >
                    Actualizar lista
                </button>
            </div>

            <form onSubmit={crear} className="grid grid-cols-1 md:grid-cols-3 gap-2 items-end">
                <label className="space-y-1">
                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Evento</span>
                    <select
                        value={event}
                        onChange={(e) => setEvent(e.target.value)}
                        className="w-full theme-element border theme-border rounded-xl px-3 py-2 text-sm theme-text-main"
                    >
                        {(eventosRecomendados.length ? eventosRecomendados : [event]).map((ev) => (
                            <option key={ev} value={ev}>{ev}</option>
                        ))}
                    </select>
                </label>
                <label className="space-y-1 md:col-span-2">
                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">URL HTTPS</span>
                    <div className="flex gap-2">
                        <input
                            value={url}
                            onChange={(e) => setUrl(e.target.value)}
                            className="flex-1 theme-element border theme-border rounded-xl px-3 py-2 text-sm theme-text-main"
                            placeholder="https://…"
                        />
                        <button
                            type="submit"
                            disabled={!credencialesOk || loading}
                            className="px-3 py-2 rounded-xl border theme-border text-[10px] font-black uppercase tracking-widest flex items-center gap-1 theme-text-main disabled:opacity-50"
                        >
                            <Plus className="w-3.5 h-3.5" /> Crear
                        </button>
                    </div>
                </label>
            </form>

            <div className="overflow-x-auto border theme-border rounded-xl">
                <table className="w-full text-left text-sm">
                    <thead>
                        <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                            <th className="px-3 py-2">ID</th>
                            <th className="px-3 py-2">Evento</th>
                            <th className="px-3 py-2">URL</th>
                            <th className="px-3 py-2 w-12" />
                        </tr>
                    </thead>
                    <tbody>
                        {loading && webhooks.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-3 py-4 text-xs theme-text-muted">Cargando…</td>
                            </tr>
                        )}
                        {!loading && webhooks.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-3 py-4 text-xs theme-text-muted">Sin webhooks registrados.</td>
                            </tr>
                        )}
                        {webhooks.map((wh) => (
                            <tr key={wh.id} className="border-b theme-border last:border-0">
                                <td className="px-3 py-2 theme-text-main">{wh.id}</td>
                                <td className="px-3 py-2 font-mono text-xs theme-text-main">{wh.event}</td>
                                <td className="px-3 py-2 text-xs theme-text-muted break-all">{wh.url}</td>
                                <td className="px-3 py-2">
                                    <button
                                        type="button"
                                        onClick={() => eliminar(wh.id)}
                                        className="p-2 rounded-lg theme-text-muted hover:text-red-600"
                                        title="Eliminar"
                                    >
                                        <Trash2 className="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="space-y-2">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                        Últimas entregas
                    </p>
                    <button
                        type="button"
                        onClick={cargarEntregas}
                        className="text-[10px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main"
                    >
                        Refrescar
                    </button>
                </div>
                <div className="overflow-x-auto border theme-border rounded-xl">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                <th className="px-3 py-2">Evento</th>
                                <th className="px-3 py-2">Recurso</th>
                                <th className="px-3 py-2">Estado</th>
                                <th className="px-3 py-2">Cuándo</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entregas.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-3 py-4 text-xs theme-text-muted">
                                        Sin entregas recientes.
                                    </td>
                                </tr>
                            )}
                            {entregas.map((d) => (
                                <tr key={d.id} className="border-b theme-border last:border-0">
                                    <td className="px-3 py-2 font-mono text-xs theme-text-main">{d.event}</td>
                                    <td className="px-3 py-2 text-xs theme-text-muted">{d.resource_id || '—'}</td>
                                    <td className="px-3 py-2 text-xs theme-text-main" title={d.error || undefined}>
                                        {d.status}
                                        {d.error ? ' · err' : ''}
                                    </td>
                                    <td className="px-3 py-2 text-xs theme-text-muted whitespace-nowrap">
                                        {d.created_at ? new Date(d.created_at).toLocaleString() : '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
