import React from 'react';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { THEME_INPUT, THEME_SELECT } from '../../../../utils/geliaTheme';
import { nombreCompletoColaborador } from '../../../../utils/formatoMoneda';
import { TABS_ESTADO, TAB_ESTADO_MAP } from './bancoTiempoStyles';

export default function FiltrosBancoTiempo({
    filtros,
    colaboradores,
    departamentos,
    tabActiva,
    onCambiarTab,
}) {
    const aplicar = (cambios) => {
        router.get(route('rh.banco_tiempo.index'), { ...filtros, ...cambios, page: 1 }, { preserveState: true });
    };

    const cambiarTab = (tab) => {
        onCambiarTab(tab);
        const estado = TAB_ESTADO_MAP[tab];
        aplicar({ estado: estado !== '' ? estado : undefined });
    };

    return (
        <div className="p-4 md:p-6 border-b theme-border space-y-4">
            <div className="flex flex-wrap gap-2">
                {TABS_ESTADO.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        onClick={() => cambiarTab(tab)}
                        className={`px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all ${
                            tabActiva === tab ? 'text-white border-transparent' : 'theme-border theme-text-muted hover:theme-text-main'
                        }`}
                        style={tabActiva === tab ? { backgroundColor: 'var(--color-primario)' } : {}}
                    >
                        {tab.replace('_', ' ')}
                    </button>
                ))}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <div className="relative lg:col-span-2">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                    <input
                        type="search"
                        placeholder="Buscar folio, origen o colaborador..."
                        defaultValue={filtros.busqueda || ''}
                        onKeyDown={(e) => e.key === 'Enter' && aplicar({ busqueda: e.target.value || undefined })}
                        className={`${THEME_INPUT} w-full pl-10 pr-4 py-3 rounded-2xl text-[11px] font-bold`}
                    />
                </div>
                <select
                    value={filtros.rh_colaborador_id || ''}
                    onChange={(e) => aplicar({ rh_colaborador_id: e.target.value || undefined })}
                    className={`${THEME_SELECT} w-full px-4 py-3 rounded-2xl text-[11px] font-bold`}
                >
                    <option value="">Todos los colaboradores</option>
                    {colaboradores.map((c) => (
                        <option key={c.id} value={c.id}>{nombreCompletoColaborador(c)}</option>
                    ))}
                </select>
                <select
                    value={filtros.departamento_id || ''}
                    onChange={(e) => aplicar({ departamento_id: e.target.value || undefined })}
                    className={`${THEME_SELECT} w-full px-4 py-3 rounded-2xl text-[11px] font-bold`}
                >
                    <option value="">Todos los departamentos</option>
                    {departamentos.map((d) => (
                        <option key={d.id} value={d.id}>{d.nombre}</option>
                    ))}
                </select>
            </div>
        </div>
    );
}
