import React, { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Filter, X, Calendar } from 'lucide-react';
import { THEME_INPUT } from '../../../../utils/geliaTheme';
import { RhSearchField, RhSelect } from '../../Partials/rhFilterFields';
import RhFilterTabs from '../../Partials/RhFilterTabs';
import { TABS_ESTADO, TAB_ESTADO_MAP } from './incidenciasStyles';

export default function FiltrosIncidencias({
    filtros = {},
    colaboradores = [],
    tiposFalta = [],
    departamentos = [],
    tabActiva = 'TODAS',
    onCambiarTab,
}) {
    const [busqueda, setBusqueda] = useState(filtros.busqueda || '');

    useEffect(() => {
        setBusqueda(filtros.busqueda || '');
    }, [filtros.busqueda]);

    const areasFiltradas = useMemo(() => {
        if (!filtros.departamento_id) return [];
        const depto = departamentos.find((d) => String(d.id) === String(filtros.departamento_id));
        return depto?.areas || [];
    }, [departamentos, filtros.departamento_id]);

    const aplicar = (cambios) => {
        router.get(route('rh.incidencias.index'), { ...filtros, ...cambios, page: 1 }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const ejecutarBusqueda = () => {
        aplicar({ busqueda: busqueda.trim() || undefined });
    };

    const limpiar = () => {
        setBusqueda('');
        onCambiarTab?.('TODAS');
        router.get(route('rh.incidencias.index'), {}, { preserveState: true, preserveScroll: true });
    };

    const hayFiltros = filtros.busqueda || filtros.rh_colaborador_id || filtros.catalogo_tipo_falta_id
        || filtros.departamento_id || filtros.area_id || filtros.fecha_inicio || filtros.fecha_fin
        || filtros.solo_hoy || (filtros.estado_deduccion && filtros.estado_deduccion !== '');

    return (
        <div className="p-4 md:p-6 border-b theme-border space-y-4">
            <RhFilterTabs
                tabs={TABS_ESTADO}
                tabActiva={tabActiva}
                onTabChange={(tab) => {
                    onCambiarTab?.(tab);
                    aplicar({ estado_deduccion: TAB_ESTADO_MAP[tab] || undefined });
                }}
                formatLabel={(tab) => (
                    tab === 'TODAS'
                        ? 'Todas'
                        : tab === 'APLICADAS'
                            ? 'Aplicadas'
                            : tab.charAt(0) + tab.slice(1).toLowerCase()
                )}
            />

            <div className="flex flex-col lg:flex-row gap-3">
                <RhSearchField
                    value={busqueda}
                    onChange={(e) => setBusqueda(e.target.value)}
                    onSubmit={ejecutarBusqueda}
                    placeholder="Buscar folio, colaborador o tipo..."
                />
                <button type="button" onClick={ejecutarBusqueda} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white shrink-0" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Filter className="w-4 h-4 inline mr-2" /> Buscar
                </button>
                <button type="button" onClick={() => aplicar({ solo_hoy: 1, fecha_inicio: undefined, fecha_fin: undefined })} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border shrink-0">
                    <Calendar className="w-4 h-4 inline mr-2" /> Hoy
                </button>
                {hayFiltros && (
                    <button type="button" onClick={limpiar} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border shrink-0">
                        <X className="w-4 h-4 inline mr-2" /> Limpiar
                    </button>
                )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <RhSelect value={filtros.rh_colaborador_id || ''} onChange={(e) => aplicar({ rh_colaborador_id: e.target.value || undefined })}>
                    <option value="">Todos los colaboradores</option>
                    {colaboradores.map((c) => (
                        <option key={c.id} value={c.id}>{c.nombre} {c.apellido_paterno || ''}</option>
                    ))}
                </RhSelect>
                <RhSelect value={filtros.catalogo_tipo_falta_id || ''} onChange={(e) => aplicar({ catalogo_tipo_falta_id: e.target.value || undefined })}>
                    <option value="">Todos los tipos</option>
                    {tiposFalta.map((t) => (
                        <option key={t.id} value={t.id}>{t.nombre}</option>
                    ))}
                </RhSelect>
                <RhSelect value={filtros.departamento_id || ''} onChange={(e) => aplicar({ departamento_id: e.target.value || undefined, area_id: undefined })}>
                    <option value="">Departamento</option>
                    {departamentos.map((d) => <option key={d.id} value={d.id}>{d.nombre}</option>)}
                </RhSelect>
                <RhSelect value={filtros.area_id || ''} onChange={(e) => aplicar({ area_id: e.target.value || undefined })} disabled={!filtros.departamento_id}>
                    <option value="">Área</option>
                    {areasFiltradas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                </RhSelect>
                <input type="date" value={filtros.fecha_inicio || ''} onChange={(e) => aplicar({ fecha_inicio: e.target.value || undefined, solo_hoy: undefined })} className={`${THEME_INPUT} px-4 py-3 rounded-2xl text-[11px] font-bold`} />
            </div>
        </div>
    );
}
