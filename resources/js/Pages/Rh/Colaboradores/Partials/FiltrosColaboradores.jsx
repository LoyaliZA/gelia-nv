import React, { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Filter, X } from 'lucide-react';
import { RhSearchField, RhSelect } from '../../Partials/rhFilterFields';

export default function FiltrosColaboradores({ filtros = {}, departamentos = [], puestos = [] }) {
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
        router.get(route('rh.colaboradores.index'), { ...filtros, ...cambios, page: 1 }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const ejecutarBusqueda = () => {
        aplicar({ busqueda: busqueda.trim() || undefined });
    };

    const limpiar = () => {
        setBusqueda('');
        router.get(route('rh.colaboradores.index'), {}, { preserveState: true, preserveScroll: true });
    };

    const hayFiltros = filtros.busqueda || filtros.departamento_id || filtros.area_id || filtros.catalogo_puesto_id || filtros.activo || filtros.vinculo;

    return (
        <div className="p-4 md:p-6 border-b theme-border space-y-4">
            <div className="flex flex-col lg:flex-row gap-3">
                <RhSearchField
                    id="rh-colaboradores-busqueda"
                    value={busqueda}
                    onChange={(e) => setBusqueda(e.target.value)}
                    onSubmit={ejecutarBusqueda}
                    placeholder="Buscar por folio, UUID o nombre..."
                />
                <button
                    type="button"
                    onClick={ejecutarBusqueda}
                    className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase text-white shrink-0"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    <Filter className="w-4 h-4 inline mr-2" /> Buscar
                </button>
                {hayFiltros && (
                    <button type="button" onClick={limpiar} className="px-5 py-3 rounded-2xl text-[10px] font-black uppercase theme-element theme-border border shrink-0">
                        <X className="w-4 h-4 inline mr-2" /> Limpiar
                    </button>
                )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <RhSelect
                    value={filtros.departamento_id || ''}
                    onChange={(e) => aplicar({ departamento_id: e.target.value || undefined, area_id: undefined })}
                >
                    <option value="">Todos los departamentos</option>
                    {departamentos.map((d) => (
                        <option key={d.id} value={d.id}>{d.nombre}</option>
                    ))}
                </RhSelect>

                <RhSelect
                    value={filtros.area_id || ''}
                    onChange={(e) => aplicar({ area_id: e.target.value || undefined })}
                    disabled={!filtros.departamento_id}
                >
                    <option value="">Todas las áreas</option>
                    {areasFiltradas.map((a) => (
                        <option key={a.id} value={a.id}>{a.nombre}</option>
                    ))}
                </RhSelect>

                <RhSelect
                    value={filtros.catalogo_puesto_id || ''}
                    onChange={(e) => aplicar({ catalogo_puesto_id: e.target.value || undefined })}
                >
                    <option value="">Todos los puestos</option>
                    {puestos.map((p) => (
                        <option key={p.id} value={p.id}>{p.nombre}</option>
                    ))}
                </RhSelect>

                <RhSelect
                    value={filtros.vinculo || ''}
                    onChange={(e) => aplicar({ vinculo: e.target.value || undefined })}
                >
                    <option value="">Vínculo de cuenta</option>
                    <option value="con_cuenta">Con cuenta</option>
                    <option value="sin_cuenta">Sin cuenta</option>
                </RhSelect>

                <RhSelect
                    value={filtros.activo ?? ''}
                    onChange={(e) => aplicar({ activo: e.target.value === '' ? undefined : e.target.value })}
                >
                    <option value="">Todos los estados</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </RhSelect>
            </div>
        </div>
    );
}
