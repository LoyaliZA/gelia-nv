import React, { useState } from 'react';
import { router } from '@inertiajs/react';

export default function TabAuditoria({ auditorias, aplicaciones, filtros }) {
    const [appId, setAppId] = useState(filtros.app_id || '');
    const [status, setStatus] = useState(filtros.status || '');

    const aplicarFiltros = () => {
        const params = {};
        if (appId) params.app_id = appId;
        if (status) params.status = status;
        router.get(route('admin.api_externa.index'), params, { preserveState: true, replace: true });
    };

    const formatDate = (value) => {
        if (!value) return '—';
        return new Date(value).toLocaleString('es-MX');
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap gap-3">
                <select className="rounded-xl px-4 py-2 theme-border border bg-transparent text-sm" value={appId} onChange={(e) => setAppId(e.target.value)}>
                    <option value="">Todas las apps</option>
                    {aplicaciones.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                </select>
                <select className="rounded-xl px-4 py-2 theme-border border bg-transparent text-sm" value={status} onChange={(e) => setStatus(e.target.value)}>
                    <option value="">Todos los status</option>
                    {[200, 401, 403, 404, 422, 429, 500].map((c) => <option key={c} value={c}>{c}</option>)}
                </select>
                <button type="button" onClick={aplicarFiltros} className="px-4 py-2 rounded-xl text-white text-xs font-bold" style={{ backgroundColor: 'var(--color-primario)' }}>Filtrar</button>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="theme-text-muted text-left border-b theme-border">
                            <th className="py-2 pr-3">Fecha</th>
                            <th className="py-2 pr-3">App</th>
                            <th className="py-2 pr-3">Método</th>
                            <th className="py-2 pr-3">Ruta</th>
                            <th className="py-2 pr-3">IP</th>
                            <th className="py-2 pr-3">Status</th>
                            <th className="py-2 pr-3">ms</th>
                            <th className="py-2">Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        {auditorias.map((log) => (
                            <tr key={log.id} className="border-b theme-border/50">
                                <td className="py-2 pr-3 whitespace-nowrap">{formatDate(log.created_at)}</td>
                                <td className="py-2 pr-3">{log.aplicacion}</td>
                                <td className="py-2 pr-3 font-mono">{log.metodo}</td>
                                <td className="py-2 pr-3 font-mono">{log.ruta}</td>
                                <td className="py-2 pr-3">{log.ip}</td>
                                <td className="py-2 pr-3">{log.status_code}</td>
                                <td className="py-2 pr-3">{log.duracion_ms}</td>
                                <td className="py-2">{log.error_resumen || '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {!auditorias.length && <p className="text-sm theme-text-muted py-4">Sin registros de auditoría.</p>}
            </div>
        </div>
    );
}
