import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Filter } from 'lucide-react';
import { RhSearchField, RhSelect } from '../../Partials/rhFilterFields';
import RhFilterTabs from '../../Partials/RhFilterTabs';
import { nombreCompletoColaborador } from '../../../../utils/formatoMoneda';
import { TABS_ESTADO, TAB_ESTADO_MAP } from './prestamosStyles';

export default function FiltrosPrestamos({
    filtros,
    colaboradores,
    departamentos,
    tabActiva,
    onCambiarTab,
}) {
    const [localFiltros, setLocalFiltros] = useState({
        busqueda: filtros.busqueda || '',
        rh_colaborador_id: filtros.rh_colaborador_id || '',
        modalidad: filtros.modalidad || '',
        departamento_id: filtros.departamento_id || '',
    });

    useEffect(() => {
        setLocalFiltros({
            busqueda: filtros.busqueda || '',
            rh_colaborador_id: filtros.rh_colaborador_id || '',
            modalidad: filtros.modalidad || '',
            departamento_id: filtros.departamento_id || '',
        });
    }, [filtros]);

    const aplicar = (cambios) => {
        router.get(route('rh.prestamos.index'), { ...filtros, ...localFiltros, ...cambios, page: 1 }, { preserveState: true, preserveScroll: true });
    };

    const ejecutarBusqueda = () => {
        aplicar({ busqueda: localFiltros.busqueda.trim() || undefined });
    };

    const cambiarTab = (tab) => {
        onCambiarTab(tab);
        aplicar({ estado: TAB_ESTADO_MAP[tab] || undefined });
    };

    return (
        <div className="p-4 md:p-6 border-b theme-border space-y-4">
            <RhFilterTabs
                tabs={TABS_ESTADO}
                tabActiva={tabActiva}
                onTabChange={cambiarTab}
                formatLabel={(tab) => tab.replace('_', ' ')}
            />

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
                <div className="lg:col-span-2 flex flex-col sm:flex-row gap-3">
                    <RhSearchField
                        value={localFiltros.busqueda}
                        onChange={(e) => setLocalFiltros(prev => ({ ...prev, busqueda: e.target.value }))}
                        onSubmit={ejecutarBusqueda}
                        placeholder="Buscar folio, concepto o colaborador..."
                    />
                    <button
                        type="button"
                        onClick={ejecutarBusqueda}
                        className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white shrink-0"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Filter className="w-4 h-4 inline mr-2" /> Buscar
                    </button>
                </div>
                <RhSelect
                    value={localFiltros.rh_colaborador_id}
                    onChange={(e) => setLocalFiltros(prev => ({ ...prev, rh_colaborador_id: e.target.value }))}
                >
                    <option value="">Todos los colaboradores</option>
                    {colaboradores.map((c) => (
                        <option key={c.id} value={c.id}>{nombreCompletoColaborador(c)}</option>
                    ))}
                </RhSelect>
                <RhSelect
                    value={localFiltros.modalidad}
                    onChange={(e) => setLocalFiltros(prev => ({ ...prev, modalidad: e.target.value }))}
                >
                    <option value="">Todas las modalidades</option>
                    <option value="recurrente">Recurrente</option>
                    <option value="unica_vez">Única vez</option>
                </RhSelect>
                <RhSelect
                    value={localFiltros.departamento_id}
                    onChange={(e) => setLocalFiltros(prev => ({ ...prev, departamento_id: e.target.value }))}
                >
                    <option value="">Todos los departamentos</option>
                    {departamentos.map((d) => (
                        <option key={d.id} value={d.id}>{d.nombre}</option>
                    ))}
                </RhSelect>
            </div>
        </div>
    );
}
