import React from 'react';
import { router } from '@inertiajs/react';
import { Filter, User, UserX, Wrench } from 'lucide-react';
import { INPUT_CLASS, SELECT_CLASS, getActivosCardClass } from './activosFormStyles';

const ESTADOS = [
    { value: '', label: 'Todos los estados' },
    { value: 'disponible', label: 'Disponible' },
    { value: 'asignado', label: 'Asignado' },
    { value: 'mantenimiento', label: 'Mantenimiento' },
    { value: 'baja', label: 'Baja' },
];

export default function FiltrosActivos({ filtros = {}, tipos = [], departamentos = [], usuarios = [], onAplicar }) {
    const aplicar = (overrides = {}) => {
        const merged = {
            busqueda: filtros.busqueda || '',
            catalogo_tipo_activo_id: filtros.catalogo_tipo_activo_id || '',
            departamento_id: filtros.departamento_id || '',
            estado: filtros.estado || '',
            responsable_user_id: filtros.responsable_user_id || '',
            mis_activos: filtros.mis_activos || '',
            sin_asignar: filtros.sin_asignar || '',
            en_mantenimiento: filtros.en_mantenimiento || '',
            ...overrides,
        };

        if (overrides.mis_activos) {
            merged.responsable_user_id = '';
            merged.sin_asignar = '';
            merged.en_mantenimiento = '';
        }
        if (overrides.sin_asignar) {
            merged.responsable_user_id = '';
            merged.mis_activos = '';
            merged.en_mantenimiento = '';
        }
        if (overrides.en_mantenimiento) {
            merged.responsable_user_id = '';
            merged.mis_activos = '';
            merged.sin_asignar = '';
            merged.estado = '';
        }
        if (overrides.responsable_user_id) {
            merged.mis_activos = '';
            merged.sin_asignar = '';
            merged.en_mantenimiento = '';
        }
        if (overrides.estado) {
            merged.en_mantenimiento = '';
        }

        const params = Object.fromEntries(
            Object.entries(merged).filter(([, v]) => v !== '' && v !== false && v !== null && v !== undefined)
        );

        router.get(route('activos.index'), params, { preserveState: true, preserveScroll: true });
        onAplicar?.(merged);
    };

    return (
        <div className={getActivosCardClass({ extra: 'p-4 md:p-6 space-y-4' })} style={{ animationDelay: '100ms' }}>
            <div className="flex items-center gap-2 mb-2">
                <Filter className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Filtros</span>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                <input
                    type="text"
                    placeholder="Buscar folio o nombre..."
                    defaultValue={filtros.busqueda || ''}
                    onKeyDown={(e) => e.key === 'Enter' && aplicar({ busqueda: e.target.value })}
                    className={INPUT_CLASS}
                />

                <select value={filtros.catalogo_tipo_activo_id || ''} onChange={(e) => aplicar({ catalogo_tipo_activo_id: e.target.value })} className={SELECT_CLASS}>
                    <option value="">Todos los tipos</option>
                    {tipos.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                </select>

                <select value={filtros.departamento_id || ''} onChange={(e) => aplicar({ departamento_id: e.target.value })} className={SELECT_CLASS}>
                    <option value="">Todos los departamentos</option>
                    {departamentos.map((d) => <option key={d.id} value={d.id}>{d.nombre}</option>)}
                </select>

                <select value={filtros.estado || ''} onChange={(e) => aplicar({ estado: e.target.value })} className={SELECT_CLASS}>
                    {ESTADOS.map((e) => <option key={e.value} value={e.value}>{e.label}</option>)}
                </select>

                <select value={filtros.responsable_user_id || ''} onChange={(e) => aplicar({ responsable_user_id: e.target.value })} className={`${SELECT_CLASS} md:col-span-2`}>
                    <option value="">Pertenece a (todos)</option>
                    {usuarios.map((u) => <option key={u.id} value={u.id}>{u.name} — {u.email}</option>)}
                </select>
            </div>

            <div className="flex flex-wrap gap-2">
                <button type="button" onClick={() => aplicar({ mis_activos: '1' })} className={`flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border theme-border ${filtros.mis_activos ? 'text-white' : 'theme-text-main theme-element'}`} style={filtros.mis_activos ? { backgroundColor: 'var(--color-primario)' } : {}}>
                    <User className="w-3 h-3" /> Mis activos
                </button>
                <button type="button" onClick={() => aplicar({ sin_asignar: '1' })} className={`flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border theme-border ${filtros.sin_asignar ? 'text-white' : 'theme-text-main theme-element'}`} style={filtros.sin_asignar ? { backgroundColor: 'var(--color-primario)' } : {}}>
                    <UserX className="w-3 h-3" /> Sin asignar
                </button>
                <button type="button" onClick={() => aplicar({ en_mantenimiento: '1' })} className={`flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border theme-border ${filtros.en_mantenimiento ? 'text-white' : 'theme-text-main theme-element'}`} style={filtros.en_mantenimiento ? { backgroundColor: 'var(--color-primario)' } : {}}>
                    <Wrench className="w-3 h-3" /> En mantenimiento
                </button>
                <button type="button" onClick={() => router.get(route('activos.index'))} className="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main">
                    Limpiar filtros
                </button>
            </div>
        </div>
    );
}
