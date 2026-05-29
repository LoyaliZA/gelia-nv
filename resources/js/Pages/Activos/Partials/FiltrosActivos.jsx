import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Filter, Search, User, UserX, Wrench, RotateCcw, ChevronDown, ChevronUp } from 'lucide-react';
import { INPUT_CLASS, SELECT_CLASS, LABEL_CLASS, getActivosCardClass } from './activosFormStyles';

const STORAGE_FILTROS_EXPANDIDOS = 'activos_filtros_expandidos';

const ESTADOS = [
    { value: '', label: 'Todos los estados' },
    { value: 'disponible', label: 'Disponible' },
    { value: 'asignado', label: 'Asignado' },
    { value: 'mantenimiento', label: 'Mantenimiento' },
    { value: 'baja', label: 'Baja' },
];

function CampoFiltro({ label, htmlFor, children, className = '' }) {
    return (
        <div className={`flex flex-col gap-2 min-w-0 ${className}`}>
            <label htmlFor={htmlFor} className={LABEL_CLASS}>
                {label}
            </label>
            {children}
        </div>
    );
}

function ChipFiltro({ active, onClick, icon: Icon, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-colors shrink-0 ${
                active
                    ? 'border-[var(--color-primario)] text-white shadow-sm'
                    : 'theme-border theme-element theme-text-main hover:border-[var(--color-primario)]'
            }`}
            style={active ? { backgroundColor: 'var(--color-primario)' } : undefined}
        >
            {Icon && <Icon className="w-3.5 h-3.5 shrink-0" aria-hidden />}
            {children}
        </button>
    );
}

function contarFiltrosActivos(filtros) {
    let n = 0;
    if (filtros.busqueda) n += 1;
    if (filtros.catalogo_tipo_activo_id) n += 1;
    if (filtros.departamento_id) n += 1;
    if (filtros.estado) n += 1;
    if (filtros.responsable_user_id) n += 1;
    if (filtros.mis_activos) n += 1;
    if (filtros.sin_asignar) n += 1;
    if (filtros.en_mantenimiento) n += 1;
    return n;
}

export default function FiltrosActivos({ filtros = {}, tipos = [], departamentos = [], usuarios = [], onAplicar }) {
    const [expandido, setExpandido] = useState(() => {
        if (typeof window === 'undefined') return true;
        const guardado = localStorage.getItem(STORAGE_FILTROS_EXPANDIDOS);
        return guardado === null ? true : guardado === 'true';
    });

    const toggleExpandido = () => {
        setExpandido((prev) => {
            const next = !prev;
            localStorage.setItem(STORAGE_FILTROS_EXPANDIDOS, String(next));
            return next;
        });
    };

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

    const limpiar = () => router.get(route('activos.index'));

    const numActivos = contarFiltrosActivos(filtros);
    const hayFiltrosActivos = numActivos > 0;

    return (
        <section className={getActivosCardClass('overflow-hidden')} aria-label="Filtros del listado">
            <div className="flex items-stretch border-b theme-border">
                <button
                    type="button"
                    onClick={toggleExpandido}
                    className="flex-1 flex items-center gap-3 p-5 md:p-6 text-left min-w-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02] transition-colors outline-none"
                    aria-expanded={expandido}
                    aria-controls="activos-filtros-panel"
                >
                    <div className="p-2.5 rounded-xl theme-element border theme-border shrink-0">
                        <Filter className="w-4 h-4" style={{ color: 'var(--color-primario)' }} aria-hidden />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2 flex-wrap">
                            <h2 className="text-sm md:text-base font-black italic uppercase tracking-tight theme-text-main m-0 leading-tight">
                                Filtros de búsqueda
                            </h2>
                            {hayFiltrosActivos && (
                                <span
                                    className="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full shrink-0"
                                    style={{
                                        backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)',
                                        color: 'var(--color-primario)',
                                    }}
                                >
                                    {numActivos} activo{numActivos !== 1 ? 's' : ''}
                                </span>
                            )}
                        </div>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">
                            {expandido
                                ? 'Ocultar opciones de filtrado'
                                : 'Mostrar búsqueda, criterios y vista rápida'}
                        </p>
                    </div>
                    <span className="p-2 rounded-xl theme-element border theme-border shrink-0" aria-hidden>
                        {expandido ? <ChevronUp className="w-4 h-4 theme-text-muted" /> : <ChevronDown className="w-4 h-4 theme-text-muted" />}
                    </span>
                </button>
                {hayFiltrosActivos && (
                    <button
                        type="button"
                        onClick={limpiar}
                        className="hidden sm:inline-flex items-center justify-center gap-2 px-4 m-4 md:m-5 rounded-xl text-[10px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main theme-element border theme-border shrink-0 self-center"
                    >
                        <RotateCcw className="w-3.5 h-3.5 shrink-0" aria-hidden />
                        Limpiar
                    </button>
                )}
            </div>

            {!expandido && (
                <div className="px-5 md:px-6 py-4 flex flex-wrap gap-3 items-center border-b theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted shrink-0">Vista rápida</span>
                    <ChipFiltro active={!!filtros.mis_activos} onClick={() => aplicar({ mis_activos: '1' })} icon={User}>
                        Mis activos
                    </ChipFiltro>
                    <ChipFiltro active={!!filtros.sin_asignar} onClick={() => aplicar({ sin_asignar: '1' })} icon={UserX}>
                        Sin asignar
                    </ChipFiltro>
                    <ChipFiltro active={!!filtros.en_mantenimiento} onClick={() => aplicar({ en_mantenimiento: '1' })} icon={Wrench}>
                        En mantenimiento
                    </ChipFiltro>
                    {hayFiltrosActivos && (
                        <button
                            type="button"
                            onClick={limpiar}
                            className="sm:hidden text-[10px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main ml-auto"
                        >
                            Limpiar
                        </button>
                    )}
                </div>
            )}

            <div
                id="activos-filtros-panel"
                className={expandido ? 'block' : 'hidden'}
                hidden={!expandido}
            >
                <div className="theme-form-zone p-5 md:p-8 space-y-6 md:space-y-8">
                    <CampoFiltro label="Buscar" htmlFor="activos-filtro-busqueda" className="w-full">
                        <div className="theme-field-with-icon">
                            <Search className="theme-field-icon" aria-hidden />
                            <input
                                id="activos-filtro-busqueda"
                                type="search"
                                placeholder="Folio o nombre del activo..."
                                defaultValue={filtros.busqueda || ''}
                                onKeyDown={(e) => e.key === 'Enter' && aplicar({ busqueda: e.target.value })}
                                className={`${INPUT_CLASS} w-full pr-4`}
                            />
                        </div>
                    </CampoFiltro>

                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 md:gap-6">
                        <CampoFiltro label="Tipo de activo" htmlFor="activos-filtro-tipo">
                            <select
                                id="activos-filtro-tipo"
                                value={filtros.catalogo_tipo_activo_id || ''}
                                onChange={(e) => aplicar({ catalogo_tipo_activo_id: e.target.value })}
                                className={`${SELECT_CLASS} w-full`}
                            >
                                <option value="">Todos los tipos</option>
                                {tipos.map((t) => (
                                    <option key={t.id} value={t.id}>{t.nombre}</option>
                                ))}
                            </select>
                        </CampoFiltro>

                        <CampoFiltro label="Departamento" htmlFor="activos-filtro-depto">
                            <select
                                id="activos-filtro-depto"
                                value={filtros.departamento_id || ''}
                                onChange={(e) => aplicar({ departamento_id: e.target.value })}
                                className={`${SELECT_CLASS} w-full`}
                            >
                                <option value="">Todos los departamentos</option>
                                {departamentos.map((d) => (
                                    <option key={d.id} value={d.id}>{d.nombre}</option>
                                ))}
                            </select>
                        </CampoFiltro>

                        <CampoFiltro label="Estado" htmlFor="activos-filtro-estado" className="md:col-span-2 xl:col-span-1">
                            <select
                                id="activos-filtro-estado"
                                value={filtros.estado || ''}
                                onChange={(e) => aplicar({ estado: e.target.value })}
                                className={`${SELECT_CLASS} w-full`}
                            >
                                {ESTADOS.map((e) => (
                                    <option key={e.value || 'all'} value={e.value}>{e.label}</option>
                                ))}
                            </select>
                        </CampoFiltro>

                        <CampoFiltro label="Pertenece a" htmlFor="activos-filtro-responsable" className="md:col-span-2 xl:col-span-3">
                            <select
                                id="activos-filtro-responsable"
                                value={filtros.responsable_user_id || ''}
                                onChange={(e) => aplicar({ responsable_user_id: e.target.value })}
                                className={`${SELECT_CLASS} w-full max-w-full`}
                            >
                                <option value="">Todos los colaboradores</option>
                                {usuarios.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name} — {u.email}</option>
                                ))}
                            </select>
                        </CampoFiltro>
                    </div>
                </div>

                <div className="px-5 md:px-8 py-5 md:py-6 border-t theme-border bg-black/[0.02] dark:bg-white/[0.02]">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-4 m-0">
                        Vista rápida
                    </p>
                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div className="flex flex-wrap gap-3">
                            <ChipFiltro active={!!filtros.mis_activos} onClick={() => aplicar({ mis_activos: '1' })} icon={User}>
                                Mis activos
                            </ChipFiltro>
                            <ChipFiltro active={!!filtros.sin_asignar} onClick={() => aplicar({ sin_asignar: '1' })} icon={UserX}>
                                Sin asignar
                            </ChipFiltro>
                            <ChipFiltro active={!!filtros.en_mantenimiento} onClick={() => aplicar({ en_mantenimiento: '1' })} icon={Wrench}>
                                En mantenimiento
                            </ChipFiltro>
                        </div>
                        {hayFiltrosActivos && (
                            <button
                                type="button"
                                onClick={limpiar}
                                className="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main shrink-0"
                            >
                                <RotateCcw className="w-3.5 h-3.5" aria-hidden />
                                Limpiar todo
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
