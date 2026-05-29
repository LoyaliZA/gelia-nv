import React, { useMemo, useState, useEffect } from 'react';
import { useForm, router } from '@inertiajs/react';
import {
    Settings2, ShieldCheck, Check, ChevronRight, ChevronDown, Key,
    Layers, Plus, Save, Pencil, X
} from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';

const STORAGE_PLANTILLAS_ABIERTO = 'gelia_usuarios_plantillas_abierto';

export default function ConfiguracionHerencia({
    rolesConfig = [],
    todosLosPermisos = [],
    esSuperAdmin = false,
}) {
    const rolesJerarquia = useMemo(
        () => (rolesConfig || []).filter((r) => r?.name && !r.name.includes('Grupo:')),
        [rolesConfig]
    );

    const rolesGrupos = useMemo(
        () => (rolesConfig || []).filter((r) => r?.name?.includes('Grupo:')),
        [rolesConfig]
    );

    const [tabActiva, setTabActiva] = useState('jerarquia');
    const [modoGrupo, setModoGrupo] = useState('editar');
    const [rolSeleccionado, setRolSeleccionado] = useState(rolesJerarquia[0]?.id ?? null);
    const [expandido, setExpandido] = useState(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem(STORAGE_PLANTILLAS_ABIERTO) === 'true';
    });

    const toggleExpandido = () => {
        setExpandido((prev) => {
            const next = !prev;
            localStorage.setItem(STORAGE_PLANTILLAS_ABIERTO, String(next));
            return next;
        });
    };

    const rolesVisibles = tabActiva === 'jerarquia' ? rolesJerarquia : rolesGrupos;

    const rolActual = useMemo(
        () => (rolesConfig || []).find((r) => r.id === rolSeleccionado),
        [rolesConfig, rolSeleccionado]
    );

    const { data, setData, put, processing } = useForm({
        permisos_heredados: [],
    });

    const formGrupo = useForm({
        nombre: '',
        permisos_heredados: [],
    });

    useEffect(() => {
        const lista = tabActiva === 'jerarquia' ? rolesJerarquia : rolesGrupos;
        if (modoGrupo === 'editar' && lista.length > 0 && !lista.some((r) => r.id === rolSeleccionado)) {
            setRolSeleccionado(lista[0].id);
        }
    }, [tabActiva, rolesJerarquia, rolesGrupos, rolSeleccionado, modoGrupo]);

    useEffect(() => {
        if (rolActual && modoGrupo === 'editar') {
            setData('permisos_heredados', (rolActual.permissions || []).map((p) => p.name));
        }
    }, [rolActual?.id, modoGrupo]);

    const permisosAgrupados = useMemo(() => {
        return (todosLosPermisos || []).reduce((acc, p) => {
            const modulo = p?.name?.split('.')[0] || 'Otros';
            if (!acc[modulo]) acc[modulo] = [];
            acc[modulo].push(p);
            return acc;
        }, {});
    }, [todosLosPermisos]);

    const togglePermiso = (permisoName, formType = 'edit') => {
        if (formType === 'create') {
            const actuales = formGrupo.data.permisos_heredados || [];
            formGrupo.setData(
                'permisos_heredados',
                actuales.includes(permisoName)
                    ? actuales.filter((p) => p !== permisoName)
                    : [...actuales, permisoName]
            );
            return;
        }

        const actuales = data.permisos_heredados || [];
        setData(
            'permisos_heredados',
            actuales.includes(permisoName)
                ? actuales.filter((p) => p !== permisoName)
                : [...actuales, permisoName]
        );
    };

    const guardarHerencia = () => {
        if (!rolActual) return;
        put(route('admin.roles.permisos.update', rolActual.id), {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['roles', 'rolesConfig'] }),
        });
    };

    const iniciarCreacionGrupo = () => {
        setModoGrupo('crear');
        formGrupo.reset();
        setRolSeleccionado(null);
    };

    const iniciarEdicionGrupo = (rolId) => {
        setModoGrupo('editar');
        setRolSeleccionado(rolId);
    };

    const crearGrupo = (e) => {
        e.preventDefault();
        formGrupo.post(route('admin.roles.grupos.store'), {
            preserveScroll: true,
            onSuccess: () => {
                formGrupo.reset();
                setModoGrupo('editar');
                setTabActiva('grupos');
                router.reload({ only: ['roles', 'rolesConfig'] });
            },
        });
    };

    const permisosActivos = modoGrupo === 'crear'
        ? formGrupo.data.permisos_heredados || []
        : data.permisos_heredados || [];

    if (!esSuperAdmin) return null;

    const renderMatrizPermisos = (formType = 'edit') => (
        <div className="space-y-2 max-h-[420px] overflow-y-auto custom-scrollbar pr-1">
            {Object.entries(permisosAgrupados).map(([modulo, permisosDeModulo]) => (
                <details key={modulo} open className="group theme-element rounded-2xl overflow-hidden border theme-border">
                    <summary className="p-3 cursor-pointer flex justify-between items-center select-none outline-none">
                        <div className="flex items-center gap-2">
                            <Key className="w-3.5 h-3.5 theme-text-muted" />
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-main italic">{modulo}</span>
                        </div>
                        <ChevronRight className="w-4 h-4 group-open:rotate-90 transition-transform theme-text-muted" />
                    </summary>
                    <div className="p-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 border-t theme-border">
                        {permisosDeModulo.map((permiso) => {
                            const activo = permisosActivos.includes(permiso.name);
                            return (
                                <button
                                    key={permiso.id}
                                    type="button"
                                    onClick={() => togglePermiso(permiso.name, formType)}
                                    className={`flex justify-between items-center px-3 py-2.5 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all hover:border-blue-400 ${
                                        activo
                                            ? 'border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                            : 'theme-border theme-text-muted opacity-60'
                                    }`}
                                >
                                    <span>{permiso.name.split('.')[1]?.replace(/_/g, ' ') || permiso.name}</span>
                                    {activo && <ShieldCheck className="w-3 h-3 shrink-0" />}
                                </button>
                            );
                        })}
                    </div>
                </details>
            ))}
        </div>
    );

    return (
        <section className={geliaCardClass('overflow-hidden')}>
            <button
                type="button"
                onClick={toggleExpandido}
                aria-expanded={expandido}
                aria-controls="config-plantillas-panel"
                className="w-full p-5 md:p-6 flex items-start sm:items-center justify-between gap-4 text-left outline-none transition-colors hover:bg-black/[0.02] dark:hover:bg-white/[0.02] focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] focus-visible:ring-offset-2 focus-visible:ring-offset-transparent rounded-t-[inherit]"
            >
                <div className="min-w-0 flex-1">
                    <h2 className="text-base sm:text-lg font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-2 flex-wrap">
                        <Settings2 className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        Configuración de Plantillas
                        {!expandido && (
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted not-italic px-2 py-0.5 rounded-lg theme-element border theme-border">
                                {rolesJerarquia.length} roles · {rolesGrupos.length} grupos
                            </span>
                        )}
                    </h2>
                    <p className="text-[10px] font-bold uppercase tracking-widest theme-text-muted mt-1.5 leading-relaxed">
                        {expandido
                            ? 'Define permisos sugeridos por rol/grupo. Las plantillas no otorgan acceso automático.'
                            : 'Toca para editar plantillas de roles y grupos predefinidos'}
                    </p>
                </div>
                <span
                    className={`shrink-0 p-2 rounded-xl theme-element border theme-border transition-transform duration-300 ${expandido ? 'rotate-180' : ''}`}
                    aria-hidden
                >
                    <ChevronDown className="w-5 h-5 theme-text-muted" />
                </span>
            </button>

            <div
                id="config-plantillas-panel"
                aria-hidden={!expandido}
                className={`grid transition-[grid-template-rows] duration-300 ease-out ${expandido ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}
            >
                <div className="overflow-hidden min-h-0">
                    <div className="px-5 md:px-8 pb-6 md:pb-8 pt-2 space-y-6 border-t theme-border">
            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={() => { setTabActiva('jerarquia'); setModoGrupo('editar'); }}
                    className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all flex items-center gap-2 ${
                        tabActiva === 'jerarquia' ? 'text-white shadow-sm' : 'theme-element theme-border theme-text-muted'
                    }`}
                    style={tabActiva === 'jerarquia' ? { backgroundColor: 'var(--color-primario)', borderColor: 'var(--color-primario)' } : {}}
                >
                    <ShieldCheck className="w-3.5 h-3.5" /> Roles Jerárquicos
                </button>
                <button
                    type="button"
                    onClick={() => { setTabActiva('grupos'); setModoGrupo('editar'); }}
                    className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all flex items-center gap-2 ${
                        tabActiva === 'grupos' ? 'text-white shadow-sm' : 'theme-element theme-border theme-text-muted'
                    }`}
                    style={tabActiva === 'grupos' ? { backgroundColor: 'var(--color-primario)', borderColor: 'var(--color-primario)' } : {}}
                >
                    <Layers className="w-3.5 h-3.5" /> Grupos Predefinidos
                </button>
            </div>

            {tabActiva === 'jerarquia' && (
                <div className="space-y-4">
                    <div className="flex flex-wrap gap-2">
                        {rolesJerarquia.map((rol) => (
                            <button
                                key={rol.id}
                                type="button"
                                onClick={() => setRolSeleccionado(rol.id)}
                                className={`px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all flex items-center gap-1.5 ${
                                    rolSeleccionado === rol.id ? 'text-white shadow-sm' : 'theme-element theme-border theme-text-muted'
                                }`}
                                style={rolSeleccionado === rol.id ? { backgroundColor: 'var(--color-primario)', borderColor: 'var(--color-primario)' } : {}}
                            >
                                {rolSeleccionado === rol.id && <Check className="w-3 h-3" />}
                                {rol.name}
                            </button>
                        ))}
                    </div>
                    {rolActual && (
                        <>
                            <p className="text-[10px] theme-text-muted font-bold tracking-widest">
                                PERMISOS DE PLANTILLA — <span className="theme-text-main">{rolActual.name}</span>
                            </p>
                            {renderMatrizPermisos('edit')}
                            <button
                                type="button"
                                onClick={guardarHerencia}
                                disabled={processing}
                                className="px-8 py-3 rounded-2xl text-white font-black uppercase tracking-widest text-[10px] flex items-center gap-2 shadow-lg disabled:opacity-50"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Save className="w-4 h-4" />
                                {processing ? 'Guardando...' : `Guardar plantilla de ${rolActual.name}`}
                            </button>
                        </>
                    )}
                </div>
            )}

            {tabActiva === 'grupos' && (
                <div className="space-y-4">
                    <div className="flex flex-wrap gap-2 items-center">
                        {rolesGrupos.map((rol) => (
                            <button
                                key={rol.id}
                                type="button"
                                onClick={() => iniciarEdicionGrupo(rol.id)}
                                className={`px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all flex items-center gap-1.5 ${
                                    modoGrupo === 'editar' && rolSeleccionado === rol.id ? 'text-white shadow-sm' : 'theme-element theme-border theme-text-muted'
                                }`}
                                style={modoGrupo === 'editar' && rolSeleccionado === rol.id ? { backgroundColor: 'var(--color-primario)', borderColor: 'var(--color-primario)' } : {}}
                            >
                                {modoGrupo === 'editar' && rolSeleccionado === rol.id && <Pencil className="w-3 h-3" />}
                                {rol.name}
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={iniciarCreacionGrupo}
                            className={`px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border-2 border-dashed transition-all flex items-center gap-1.5 ${
                                modoGrupo === 'crear' ? 'text-white' : 'theme-border theme-text-muted hover:border-[var(--color-primario)]'
                            }`}
                            style={modoGrupo === 'crear' ? { backgroundColor: 'var(--color-primario)', borderColor: 'var(--color-primario)' } : {}}
                        >
                            <Plus className="w-3 h-3" /> Nuevo Grupo
                        </button>
                    </div>

                    {modoGrupo === 'editar' && rolActual && (
                        <>
                            <p className="text-[10px] theme-text-muted font-bold tracking-widest">
                                EDITANDO GRUPO — <span className="theme-text-main">{rolActual.name}</span>
                            </p>
                            {renderMatrizPermisos('edit')}
                            <button
                                type="button"
                                onClick={guardarHerencia}
                                disabled={processing}
                                className="px-8 py-3 rounded-2xl text-white font-black uppercase tracking-widest text-[10px] flex items-center gap-2 shadow-lg disabled:opacity-50"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Save className="w-4 h-4" />
                                {processing ? 'Guardando...' : 'Guardar plantilla del grupo'}
                            </button>
                        </>
                    )}

                    {modoGrupo === 'crear' && (
                        <form onSubmit={crearGrupo} className="space-y-4 pt-2 border-t theme-border">
                            <div className="flex items-center justify-between">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-main">Crear nuevo grupo predefinido</p>
                                <button type="button" onClick={() => setModoGrupo('editar')} className="p-1.5 rounded-lg theme-text-muted hover:theme-text-main">
                                    <X className="w-4 h-4" />
                                </button>
                            </div>
                            <input
                                type="text"
                                value={formGrupo.data.nombre}
                                onChange={(e) => formGrupo.setData('nombre', e.target.value)}
                                placeholder="Nombre: Vendedor, Verificador..."
                                required
                                className="w-full px-4 py-3 rounded-2xl theme-element border theme-border text-[11px] font-bold theme-text-main outline-none"
                            />
                            {formGrupo.errors.nombre && (
                                <p className="text-[10px] text-red-500 font-bold">{formGrupo.errors.nombre}</p>
                            )}
                            <p className="text-[9px] font-bold theme-text-muted uppercase tracking-widest">Permisos atómicos del grupo</p>
                            {renderMatrizPermisos('create')}
                            <button
                                type="submit"
                                disabled={formGrupo.processing}
                                className="px-6 py-3 rounded-2xl text-white font-black uppercase tracking-widest text-[10px] flex items-center gap-2 shadow-lg disabled:opacity-50"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Plus className="w-4 h-4" />
                                {formGrupo.processing ? 'Creando...' : 'Crear Grupo Predefinido'}
                            </button>
                        </form>
                    )}

                    {modoGrupo === 'editar' && rolesGrupos.length === 0 && (
                        <p className="text-[10px] font-bold theme-text-muted italic">
                            No hay grupos aún. Usa «Nuevo Grupo» para crear plantillas de permisos atómicos.
                        </p>
                    )}
                </div>
            )}
                    </div>
                </div>
            </div>
        </section>
    );
}
