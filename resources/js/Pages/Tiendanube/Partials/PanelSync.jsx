import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';

export default function PanelSync({
    permisos,
    procesoActivo,
    ultimosSyncs = [],
    syncLogId,
    onSyncStarted,
    credencialesOk,
    embedded = false,
}) {
    const [progreso, setProgreso] = useState(null);
    const [error, setError] = useState(null);
    const [iniciando, setIniciando] = useState(false);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const sincronizar = async () => {
        if (!permisos.sincronizar || !credencialesOk) return;
        setIniciando(true);
        setError(null);
        try {
            const res = await fetch(route('tiendanube.sincronizar'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: '{}',
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'No se pudo iniciar la sincronización.');
            }
            onSyncStarted?.(data.sync_log_id);
        } catch (err) {
            setError(err.message);
        } finally {
            setIniciando(false);
        }
    };

    useEffect(() => {
        if (!syncLogId) return undefined;

        let cancelled = false;
        const poll = async () => {
            try {
                const res = await fetch(route('tiendanube.progreso', syncLogId), {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (cancelled) return;
                setProgreso(data);
                if (['completado', 'error'].includes(data.estado)) {
                    router.reload({ only: ['productos', 'totales', 'ultimosSyncs', 'procesoActivo'] });
                }
            } catch {
                // ignore poll errors
            }
        };

        poll();
        const id = setInterval(poll, 2000);
        return () => {
            cancelled = true;
            clearInterval(id);
        };
    }, [syncLogId]);

    const activo = progreso && ['pendiente', 'en_proceso'].includes(progreso.estado);

    return (
        <div className={embedded ? 'space-y-4' : `${geliaCardClass()} p-5 md:p-6 space-y-4`}>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main">Sincronización de catálogo</h2>
                    <p className="text-xs theme-text-muted mt-1">
                        Descarga categorías, productos, SEO, imágenes y variantes desde Tiendanube.
                    </p>
                </div>
                {permisos.sincronizar && (
                    <button
                        type="button"
                        onClick={sincronizar}
                        disabled={iniciando || activo || !credencialesOk}
                        className="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-[10px] font-black uppercase text-white disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <RefreshCw className={`w-4 h-4 ${activo || iniciando ? 'animate-spin' : ''}`} />
                        {activo ? 'Sincronizando…' : 'Sincronizar ahora'}
                    </button>
                )}
            </div>

            {!credencialesOk && (
                <p className="text-xs font-bold text-amber-600">Configura credenciales antes de sincronizar.</p>
            )}
            {error && <p className="text-xs font-bold text-red-500">{error}</p>}

            {(progreso || procesoActivo) && (
                <div className="space-y-2">
                    <div className="flex justify-between text-[10px] font-black uppercase tracking-widest theme-text-muted">
                        <span>Estado: {progreso?.estado || procesoActivo?.estado}</span>
                        <span>{progreso?.porcentaje ?? 0}%</span>
                    </div>
                    <div className="h-2 rounded-full bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
                        <div
                            className="h-full transition-all"
                            style={{
                                width: `${progreso?.porcentaje ?? 0}%`,
                                backgroundColor: 'var(--color-primario)',
                            }}
                        />
                    </div>
                    <p className="text-xs theme-text-muted">
                        Categorías {progreso?.procesados_categorias ?? procesoActivo?.procesados_categorias ?? 0}
                        {' / '}
                        {progreso?.total_categorias || '…'}
                        {' · '}
                        Productos {progreso?.procesados_productos ?? procesoActivo?.procesados_productos ?? 0}
                        {' / '}
                        {progreso?.total_productos || '…'}
                        {(progreso?.eliminados_productos > 0 || progreso?.eliminados_categorias > 0) && (
                            <>
                                {' · '}
                                Eliminados {progreso.eliminados_productos || 0} prod / {progreso.eliminados_categorias || 0} cat
                            </>
                        )}
                    </p>
                    {progreso?.mensaje_error && (
                        <p className="text-xs font-bold text-red-500">{progreso.mensaje_error}</p>
                    )}
                </div>
            )}

            {ultimosSyncs?.length > 0 && (
                <div className="pt-2 border-t theme-border">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Últimos syncs</p>
                    <ul className="space-y-1">
                        {ultimosSyncs.map((s) => (
                            <li key={s.id} className="text-xs theme-text-muted flex justify-between gap-2">
                                <span>#{s.id} · {s.estado} · {s.tipo}</span>
                                <span>{s.procesados_productos}/{s.total_productos} prod</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
