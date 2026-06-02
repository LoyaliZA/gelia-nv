import React, { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Filter } from 'lucide-react';
import { RhSearchField, RhSelect } from '../../Partials/rhFilterFields';
import RhFilterTabs from '../../Partials/RhFilterTabs';
import { nombreCompletoColaborador } from '../../../../utils/formatoMoneda';
import { TABS_ESTADO, TAB_ESTADO_MAP } from './bancoTiempoStyles';

export default function FiltrosBancoTiempo({
    filtros,
    colaboradores,
    departamentos,
    tabActiva,
    onCambiarTab,
}) {
    const [busqueda, setBusqueda] = useState(filtros.busqueda || '');

    useEffect(() => {
        setBusqueda(filtros.busqueda || '');
    }, [filtros.busqueda]);

    const aplicar = (cambios) => {
        router.get(route('rh.banco_tiempo.index'), { ...filtros, ...cambios, page: 1 }, { preserveState: true });
    };

    const ejecutarBusqueda = () => {
        aplicar({ busqueda: busqueda.trim() || undefined });
    };

    const cambiarTab = (tab) => {
        onCambiarTab(tab);
        const estado = TAB_ESTADO_MAP[tab];
        aplicar({ estado: estado !== '' ? estado : undefined });
    };

    return (
        <div className="p-4 md:p-6 border-b theme-border space-y-4">
            <RhFilterTabs
                tabs={TABS_ESTADO}
                tabActiva={tabActiva}
                onTabChange={cambiarTab}
                formatLabel={(tab) => tab.replace('_', ' ')}
            />

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <div className="lg:col-span-2 flex flex-col sm:flex-row gap-3">
                    <RhSearchField
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                        onSubmit={ejecutarBusqueda}
                        placeholder="Buscar folio, origen o colaborador..."
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
                    value={filtros.rh_colaborador_id || ''}
                    onChange={(e) => aplicar({ rh_colaborador_id: e.target.value || undefined })}
                >
                    <option value="">Todos los colaboradores</option>
                    {colaboradores.map((c) => (
                        <option key={c.id} value={c.id}>{nombreCompletoColaborador(c)}</option>
                    ))}
                </RhSelect>
                <RhSelect
                    value={filtros.departamento_id || ''}
                    onChange={(e) => aplicar({ departamento_id: e.target.value || undefined })}
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
