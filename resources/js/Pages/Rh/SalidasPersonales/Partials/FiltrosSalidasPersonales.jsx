import React, { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Search, Filter, X, Calendar } from 'lucide-react';
import { THEME_INPUT, THEME_SELECT } from '../../../../utils/geliaTheme';

const TABS_ESTADO = ['TODAS', 'PENDIENTES', 'COBRADAS'];

const TAB_ESTADO_MAP = {
    TODAS: '',
    PENDIENTES: 'pendiente',
    COBRADAS: 'cobrado',
};

export default function FiltrosSalidasPersonales({
    filtros = {},
    colaboradores = [],
    departamentos = [],
    tabActiva = 'TODAS',
    onCambiarTab,
}) {
    const [busqueda, setBusqueda] = useState(filtros.busqueda || '');

    const areasFiltradas = useMemo(() => {
        if (!filtros.departamento_id) return [];
        const depto = departamentos.find((d) => String(d.id) === String(filtros.departamento_id));
        return depto?.areas || [];
    }, [departamentos, filtros.departamento_id]);

    const aplicar = (cambios) => {
        router.get(route('rh.salidas_personales.index'), { ...filtros, ...cambios, page: 1 }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const limpiar = () => {
        setBusqueda('');
        onCambiarTab?.('TODAS');
        router.get(route('rh.salidas_personales.index'), {}, { preserveState: true, preserveScroll: true });
    };

    const hayFiltros = filtros.busqueda || filtros.rh_colaborador_id || filtros.departamento_id
        || filtros.area_id || filtros.fecha_inicio || filtros.fecha_fin
        || (filtros.estado_cobro && filtros.estado_cobro !== '');

    return (
        <div className="p-4 md:p-6 border-b theme-border space-y-4">
            <div className="flex flex-wrap gap-2">
                {TABS_ESTADO.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        onClick={() => {
                            onCambiarTab?.(tab);
                            aplicar({ estado_cobro: TAB_ESTADO_MAP[tab] || undefined });
                        }}
                        className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all ${
                            tabActiva === tab ? 'text-white border-transparent' : 'theme-element theme-border theme-text-muted'
                        }`}
                        style={tabActiva === tab ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        {tab === 'TODAS' ? 'Todas' : tab === 'PENDIENTES' ? 'Pendientes de cobro' : 'Cobradas'}
                    </button>
                ))}
            </div>

            <div className="flex flex-col lg:flex-row gap-3">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                    <input
                        type="search"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        placeholder="Buscar folio o colaborador..."
                        className={`w-full pl-10 pr-4 py-3 rounded-2xl ${THEME_INPUT} text-[11px] font-bold`}
                        onKeyDown={(e) => e.key === 'Enter' && aplicar({ busqueda })}
                    />
                </div>
                <button type="button" onClick={() => aplicar({ busqueda })} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center justify-center" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Filter className="w-4 h-4 mr-2" /> Buscar
                </button>
                <button type="button" onClick={() => aplicar({ fecha_inicio: new Date().toISOString().split('T')[0], fecha_fin: new Date().toISOString().split('T')[0] })} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border flex items-center justify-center">
                    <Calendar className="w-4 h-4 mr-2" /> Hoy
                </button>
                {hayFiltros && (
                    <button type="button" onClick={limpiar} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border flex items-center justify-center">
                        <X className="w-4 h-4 mr-2" /> Limpiar
                    </button>
                )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <select value={filtros.rh_colaborador_id || ''} onChange={(e) => aplicar({ rh_colaborador_id: e.target.value || undefined })} className={`px-4 py-3 rounded-2xl ${THEME_SELECT} text-[11px] font-bold`}>
                    <option value="">Todos los colaboradores</option>
                    {colaboradores.map((c) => (
                        <option key={c.id} value={c.id}>{c.nombre} {c.apellido_paterno || ''}</option>
                    ))}
                </select>
                <select value={filtros.departamento_id || ''} onChange={(e) => aplicar({ departamento_id: e.target.value || undefined, area_id: undefined })} className={`px-4 py-3 rounded-2xl ${THEME_SELECT} text-[11px] font-bold`}>
                    <option value="">Departamento</option>
                    {departamentos.map((d) => <option key={d.id} value={d.id}>{d.nombre}</option>)}
                </select>
                <select value={filtros.area_id || ''} onChange={(e) => aplicar({ area_id: e.target.value || undefined })} className={`px-4 py-3 rounded-2xl ${THEME_SELECT} text-[11px] font-bold`} disabled={!filtros.departamento_id}>
                    <option value="">Área</option>
                    {areasFiltradas.map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                </select>
                <input type="date" value={filtros.fecha_inicio || ''} onChange={(e) => aplicar({ fecha_inicio: e.target.value || undefined })} className={`px-4 py-3 rounded-2xl ${THEME_INPUT} text-[11px] font-bold`} />
                <input type="date" value={filtros.fecha_fin || ''} onChange={(e) => aplicar({ fecha_fin: e.target.value || undefined })} className={`px-4 py-3 rounded-2xl ${THEME_INPUT} text-[11px] font-bold`} />
            </div>
        </div>
    );
}
