import React, { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { Filter, RefreshCw } from 'lucide-react';
import TimelineResguardo from './TimelineResguardo';
import { BTN_SECONDARY } from './resguardosStyles';

const estadoInicialFiltros = {
    tipo_evento: '',
    categoria: '',
    desde: '',
    hasta: '',
};

export default function PanelAuditoriaResguardo({
    resguardoId,
    timelineInicial = [],
    catalogos = {},
}) {
    const [timeline, setTimeline] = useState(timelineInicial);
    const [filtros, setFiltros] = useState(estadoInicialFiltros);
    const [filtrosAplicados, setFiltrosAplicados] = useState(estadoInicialFiltros);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState(null);
    const [catalogosAuditoria, setCatalogosAuditoria] = useState({
        eventos: catalogos.eventos || {},
        categorias: {},
    });

    const consultar = useCallback(async (filtrosConsulta = filtrosAplicados) => {
        setCargando(true);
        setError(null);

        try {
            const params = Object.fromEntries(
                Object.entries(filtrosConsulta).filter(([, valor]) => valor !== '' && valor != null),
            );

            const { data } = await axios.get(route('punto_venta.resguardos.auditoria', resguardoId), {
                params,
                headers: { Accept: 'application/json' },
            });

            setTimeline(data.timeline || []);
            if (data.catalogos) {
                setCatalogosAuditoria(data.catalogos);
            }
        } catch (err) {
            setError(err?.response?.data?.message || 'No se pudo cargar la auditoría.');
        } finally {
            setCargando(false);
        }
    }, [filtrosAplicados, resguardoId]);

    useEffect(() => {
        setTimeline(timelineInicial);
    }, [timelineInicial]);

    const aplicarFiltros = () => {
        setFiltrosAplicados({ ...filtros });
        consultar(filtros);
    };

    const limpiarFiltros = () => {
        setFiltros(estadoInicialFiltros);
        setFiltrosAplicados(estadoInicialFiltros);
        consultar(estadoInicialFiltros);
    };

    return (
        <div className="space-y-4">
            <div className="rounded-2xl border theme-border p-4 md:p-5 space-y-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Filter className="w-4 h-4 theme-text-muted" />
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Auditoría
                    </h2>
                    <button
                        type="button"
                        onClick={() => consultar()}
                        disabled={cargando}
                        className={`${BTN_SECONDARY} ml-auto inline-flex items-center gap-2 min-h-[40px] text-[10px]`}
                    >
                        <RefreshCw className={`w-3.5 h-3.5 ${cargando ? 'animate-spin' : ''}`} />
                        Actualizar
                    </button>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <label className="space-y-1">
                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Tipo de evento</span>
                        <select
                            value={filtros.tipo_evento}
                            onChange={(e) => setFiltros((prev) => ({ ...prev, tipo_evento: e.target.value }))}
                            className="w-full rounded-xl border theme-border bg-transparent px-3 py-2 text-sm min-h-[44px]"
                        >
                            <option value="">Todos</option>
                            {Object.entries(catalogosAuditoria.eventos || catalogos.eventos || {}).map(([clave, etiqueta]) => (
                                <option key={clave} value={clave}>{etiqueta}</option>
                            ))}
                        </select>
                    </label>
                    <label className="space-y-1">
                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Categoría</span>
                        <select
                            value={filtros.categoria}
                            onChange={(e) => setFiltros((prev) => ({ ...prev, categoria: e.target.value }))}
                            className="w-full rounded-xl border theme-border bg-transparent px-3 py-2 text-sm min-h-[44px]"
                        >
                            <option value="">Todas</option>
                            {Object.entries(catalogosAuditoria.categorias || {}).map(([clave, etiqueta]) => (
                                <option key={clave} value={clave}>{etiqueta}</option>
                            ))}
                        </select>
                    </label>
                    <label className="space-y-1">
                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Desde</span>
                        <input
                            type="date"
                            value={filtros.desde}
                            onChange={(e) => setFiltros((prev) => ({ ...prev, desde: e.target.value }))}
                            className="w-full rounded-xl border theme-border bg-transparent px-3 py-2 text-sm min-h-[44px]"
                        />
                    </label>
                    <label className="space-y-1">
                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Hasta</span>
                        <input
                            type="date"
                            value={filtros.hasta}
                            onChange={(e) => setFiltros((prev) => ({ ...prev, hasta: e.target.value }))}
                            className="w-full rounded-xl border theme-border bg-transparent px-3 py-2 text-sm min-h-[44px]"
                        />
                    </label>
                </div>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={aplicarFiltros}
                        disabled={cargando}
                        className={`${BTN_SECONDARY} min-h-[44px] text-[10px] font-black uppercase tracking-widest`}
                    >
                        Aplicar filtros
                    </button>
                    <button
                        type="button"
                        onClick={limpiarFiltros}
                        disabled={cargando}
                        className={`${BTN_SECONDARY} min-h-[44px] text-[10px] font-black uppercase tracking-widest`}
                    >
                        Limpiar
                    </button>
                </div>

                {error && (
                    <p className="text-sm text-red-600 dark:text-red-300 font-semibold m-0">{error}</p>
                )}
            </div>

            <TimelineResguardo eventos={timeline} soloLectura />
        </div>
    );
}
