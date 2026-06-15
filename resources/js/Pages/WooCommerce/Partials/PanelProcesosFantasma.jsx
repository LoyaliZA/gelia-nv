import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Ghost, Trash2, Play } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { dismissWooSyncTracking, etiquetaTipoSync, startWooSyncTracking } from '../../../utils/woocommerceSyncTracker';

export default function PanelProcesosFantasma({ procesosFantasma, permisos }) {
    const [eliminando, setEliminando] = useState(null);

    if (!permisos?.sincronizar || !procesosFantasma?.length) return null;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const descartar = async (id) => {
        if (!window.confirm(`¿Eliminar el proceso #${id}? Esta acción no se puede deshacer.`)) return;

        setEliminando(id);
        try {
            const res = await fetch(route('woocommerce.sync.descartar', id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message);

            dismissWooSyncTracking();
            router.reload({ only: ['procesosFantasma', 'procesoActivo'] });
        } catch (e) {
            alert(e.message);
        } finally {
            setEliminando(null);
        }
    };

    const descartarTodos = async () => {
        if (!window.confirm(`¿Eliminar los ${procesosFantasma.length} proceso(s) fantasma?`)) return;

        setEliminando('todos');
        try {
            const res = await fetch(route('woocommerce.sync.descartar_todos'), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message);

            dismissWooSyncTracking();
            router.reload({ only: ['procesosFantasma', 'procesoActivo'] });
        } catch (e) {
            alert(e.message);
        } finally {
            setEliminando(null);
        }
    };

    const continuar = async (id) => {
        setEliminando(`continuar-${id}`);
        try {
            const res = await fetch(route('woocommerce.sync.continuar', id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message);

            startWooSyncTracking(data.log_id);
            router.reload({ only: ['procesosFantasma', 'procesoActivo'] });
        } catch (e) {
            alert(e.message);
        } finally {
            setEliminando(null);
        }
    };

    return (
        <div className={`${geliaCardClass()} p-5 md:p-6 border-l-4 border-amber-500`}>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div className="flex items-center gap-2">
                    <Ghost className="w-5 h-5 text-amber-500" />
                    <h3 className="text-sm font-black uppercase theme-text-main m-0">
                        Procesos fantasma ({procesosFantasma.length})
                    </h3>
                </div>
                {procesosFantasma.length > 1 && (
                    <button
                        type="button"
                        onClick={descartarTodos}
                        disabled={eliminando === 'todos'}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border border-red-500/40 text-red-500 hover:bg-red-500/10 disabled:opacity-50"
                    >
                        <Trash2 className="w-3.5 h-3.5" /> Eliminar todos
                    </button>
                )}
            </div>

            <p className="text-xs theme-text-muted mb-4">
                Procesos detenidos, interrumpidos o pendientes que bloquean la cola. Puedes eliminarlos o continuar desde donde se quedaron.
            </p>

            <div className="space-y-2">
                {procesosFantasma.map((p) => {
                    const pct = Math.round((p.procesados / Math.max(p.total_productos, 1)) * 100);
                    const puedeContinuar = ['interrumpido', 'error'].includes(p.estado) && p.procesados < p.total_productos;

                    return (
                        <div key={p.id} className="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-xl theme-element border theme-border">
                            <div className="flex-1 min-w-0">
                                <p className="text-xs font-black uppercase theme-text-main m-0">
                                    #{p.id} · {etiquetaTipoSync(p.tipo)}
                                </p>
                                <p className="text-[10px] font-bold theme-text-muted mt-0.5">
                                    {p.estado} — {p.procesados}/{p.total_productos} ({pct}%)
                                </p>
                                {p.mensaje_error && (
                                    <p className="text-[10px] text-red-500 mt-1 truncate">{p.mensaje_error}</p>
                                )}
                            </div>
                            <div className="flex gap-2 shrink-0">
                                {puedeContinuar && (
                                    <button
                                        type="button"
                                        onClick={() => continuar(p.id)}
                                        disabled={eliminando === `continuar-${p.id}`}
                                        className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase text-white disabled:opacity-50"
                                        style={{ backgroundColor: 'var(--color-primario)' }}
                                    >
                                        <Play className="w-3 h-3" /> Continuar
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => descartar(p.id)}
                                    disabled={eliminando === p.id}
                                    className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase border border-red-500/40 text-red-500 hover:bg-red-500/10 disabled:opacity-50"
                                >
                                    <Trash2 className="w-3 h-3" /> Eliminar
                                </button>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
