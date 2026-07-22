import React, { useMemo, useState, useEffect } from 'react';
import {
    Key, ShieldCheck, ChevronRight, Lock, ShieldAlert, Search, Layers, UserPen,
} from 'lucide-react';
import {
    permisoDePlantilla,
    plantillasDePermiso,
    filtrarPermisosAsignables,
    usuarioPuedeAsignarPermiso,
    descripcionPermiso,
    etiquetaPermisoEnMatriz,
    esPermisoExcepcion,
    permisoProtegidoParaEditor,
    permisoNoDelegablePorGerente,
    gerentePuedeMostrarPermisoInactivo,
    agruparPermisosPorSubmodulo,
    agruparModulosPorSeccionSidebar,
    etiquetaModuloUi,
    filtrarPermisosPorBusqueda,
    calcularDiffPlantilla,
    permisoCoincideBusqueda,
} from '../../../utils/permisos';
import PermisoOrigenIndicador, { LeyendaOrigenPermisos } from './PermisoOrigenIndicador';

function MatrizPermisos({
    permisosDeModulo,
    activos,
    plantillasActivas,
    roles,
    procedencia,
    plantillaActiva,
    usuarioActualId,
    esSuperAdmin,
    permisosUsuario,
    onToggle,
    onAsignarLote,
    soloLectura = false,
    removidosSet = null,
}) {
    const moduloRoot = permisosDeModulo?.[0]?.name?.split('.')[0] || '';
    const submodulos = useMemo(
        () => agruparPermisosPorSubmodulo(moduloRoot, permisosDeModulo),
        [moduloRoot, permisosDeModulo],
    );

    if (!permisosDeModulo?.length) return null;

    const permisoEsToggleable = (permiso) => {
        const meta = procedencia[permiso.name];
        const protegido = permisoProtegidoParaEditor(meta, usuarioActualId, esSuperAdmin);
        const noDelegable = permisoNoDelegablePorGerente(permiso.name, esSuperAdmin);
        const puedeAsignar = usuarioPuedeAsignarPermiso(permiso.name, permisosUsuario, esSuperAdmin);
        return !soloLectura && !protegido && !noDelegable && puedeAsignar;
    };

    const togglePermisos = (permisos) => {
        const toggleables = permisos.filter(permisoEsToggleable);
        if (toggleables.length === 0) return;
        const nombres = toggleables.map((p) => p.name);
        const todosActivos = nombres.every((name) => activos.includes(name));
        if (onAsignarLote) {
            onAsignarLote(nombres, !todosActivos);
            return;
        }
        nombres.forEach((name) => {
            const isAsignado = activos.includes(name);
            if (todosActivos ? isAsignado : !isAsignado) onToggle(name);
        });
    };

    const renderCheckbox = (permiso, { conEtiqueta = false } = {}) => {
        const isAsignado = activos.includes(permiso.name);
        const isRemovido = removidosSet?.has(permiso.name);
        const isDePlantilla = permisoDePlantilla(permiso.name, plantillasActivas, roles);
        const meta = procedencia[permiso.name];
        const protegido = permisoProtegidoParaEditor(meta, usuarioActualId, esSuperAdmin);
        const noDelegable = permisoNoDelegablePorGerente(permiso.name, esSuperAdmin);
        const puedeAsignar = usuarioPuedeAsignarPermiso(permiso.name, permisosUsuario, esSuperAdmin);
        const deshabilitado = soloLectura || protegido || noDelegable || !puedeAsignar;
        const ayuda = descripcionPermiso(permiso.name);
        const etiqueta = etiquetaPermisoEnMatriz(permiso.name);
        const esExcepcion = esPermisoExcepcion(permiso.name);

        return (
            <label
                className={`flex items-start gap-2 min-h-8 cursor-pointer ${deshabilitado ? 'opacity-50 cursor-not-allowed' : ''}`}
                title={ayuda || etiqueta || permiso.name}
            >
                <span className="inline-flex items-center gap-1.5 shrink-0 pt-0.5">
                    <input
                        type="checkbox"
                        checked={isAsignado}
                        disabled={deshabilitado}
                        onChange={() => !deshabilitado && onToggle(permiso.name)}
                        className="w-4 h-4 rounded accent-[var(--color-primario)] cursor-pointer disabled:cursor-not-allowed"
                        aria-label={`${etiqueta}${ayuda ? `. ${ayuda}` : ''}${isAsignado ? ' activo' : ' inactivo'}`}
                    />
                    {isAsignado && (
                        <PermisoOrigenIndicador
                            meta={meta}
                            isDePlantilla={isDePlantilla}
                            plantillaNombre={plantillaActiva}
                            usuarioActualId={usuarioActualId}
                        />
                    )}
                </span>
                {conEtiqueta && (
                    <span className="flex flex-col min-w-0 gap-0.5">
                        <span
                            className={`text-xs font-semibold leading-snug ${
                                isRemovido
                                    ? 'line-through opacity-60 theme-text-muted'
                                    : esExcepcion
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : 'theme-text-main'
                            }`}
                        >
                            {etiqueta}
                        </span>
                        {ayuda && (
                            <span className="text-[11px] theme-text-muted leading-snug font-normal">
                                {ayuda}
                            </span>
                        )}
                    </span>
                )}
            </label>
        );
    };

    const renderListaPermisos = (permisos) => {
        const normales = permisos.filter((p) => !esPermisoExcepcion(p.name));
        const excepciones = permisos.filter((p) => esPermisoExcepcion(p.name));

        return (
            <div className="space-y-2">
                {normales.length > 0 && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-x-4 gap-y-2">
                        {normales.map((p) => (
                            <div key={p.id ?? p.name}>{renderCheckbox(p, { conEtiqueta: true })}</div>
                        ))}
                    </div>
                )}
                {excepciones.length > 0 && (
                    <div className="pt-1 space-y-1.5">
                        <p className="text-[10px] font-black uppercase tracking-widest text-amber-600/90 dark:text-amber-400/90">
                            Excepción · estados avanzados
                        </p>
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-x-4 gap-y-2">
                            {excepciones.map((p) => (
                                <div key={p.id ?? p.name}>{renderCheckbox(p, { conEtiqueta: true })}</div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        );
    };

    const renderBloqueSubmodulo = (grupo) => {
        const toggleables = grupo.permisos.filter(permisoEsToggleable);
        const todosActivos = toggleables.length > 0
            && toggleables.every((p) => activos.includes(p.name));
        const puedeAlternar = toggleables.length > 0 && !soloLectura;

        return (
            <section key={grupo.id} className="border theme-border rounded-xl p-3 space-y-2">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <h4 className="text-[11px] font-black uppercase tracking-widest theme-text-muted">
                            {grupo.label}
                        </h4>
                        {grupo.descripcion && (
                            <p className="text-[11px] theme-text-muted mt-0.5 leading-snug">
                                {grupo.descripcion}
                            </p>
                        )}
                    </div>
                    {puedeAlternar && (
                        <button
                            type="button"
                            onClick={() => togglePermisos(grupo.permisos)}
                            className="shrink-0 text-[10px] font-bold uppercase tracking-wide theme-text-muted hover:text-[var(--color-primario)] transition-colors"
                            aria-pressed={todosActivos}
                        >
                            {todosActivos ? 'Quitar todos' : 'Seleccionar todos'}
                        </button>
                    )}
                </div>
                {renderListaPermisos(grupo.permisos)}
            </section>
        );
    };

    if (!submodulos?.length) return null;

    return (
        <div className="space-y-2">
            {submodulos.map(renderBloqueSubmodulo)}
        </div>
    );
}

function AcordeonAnimado({
    abierto,
    onToggle,
    summary,
    children,
    className = '',
    summaryClassName = '',
    contentClassName = '',
}) {
    return (
        <div className={`rounded-2xl overflow-hidden border theme-border ${className}`}>
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={abierto}
                className={`w-full p-4 flex justify-between items-center gap-3 cursor-pointer select-none outline-none text-left transition-colors hover:bg-black/[0.02] dark:hover:bg-white/[0.02] focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] focus-visible:ring-inset ${summaryClassName}`}
            >
                {summary}
            </button>
            <div
                className={`grid transition-[grid-template-rows] duration-300 ease-out ${abierto ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}
                aria-hidden={!abierto}
            >
                <div className="overflow-hidden min-h-0">
                    <div className={`border-t theme-border ${contentClassName}`}>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}

function AcordeonModulo({
    modulo,
    permisosDeModulo,
    activos,
    expandido,
    onToggleExpandido,
    children,
    variant = 'default',
}) {
    const activosEnModulo = permisosDeModulo.filter((p) => activos.includes(p.name)).length;
    const total = permisosDeModulo.length;
    const titulo = etiquetaModuloUi(modulo);

    const variantClasses = {
        default: 'theme-element',
        plantilla: 'theme-element border-blue-500/20',
        soloLectura: 'theme-element border-amber-500/30',
    };

    return (
        <AcordeonAnimado
            abierto={expandido}
            onToggle={() => onToggleExpandido(modulo, !expandido)}
            className={variantClasses[variant] || variantClasses.default}
            contentClassName="p-4"
            summary={(
                <>
                    <div className="flex items-center gap-2.5 min-w-0">
                        <ShieldCheck className="w-4 h-4 theme-text-muted shrink-0" />
                        <span className="text-xs font-black uppercase tracking-widest theme-text-main italic truncate">
                            {titulo}
                            <span className="ml-2 not-italic font-bold theme-text-muted">
                                ({activosEnModulo}/{total} activos)
                            </span>
                        </span>
                    </div>
                    <ChevronRight
                        className={`w-4 h-4 theme-text-muted shrink-0 transition-transform duration-300 ease-out ${expandido ? 'rotate-90' : ''}`}
                        aria-hidden
                    />
                </>
            )}
        >
            {children}
        </AcordeonAnimado>
    );
}

function SeccionesSidebarPermisos({
    permisosAgrupados,
    keyPrefix = '',
    activos,
    plantillasActivas,
    roles,
    procedencia,
    plantillaActiva,
    usuarioActualId,
    esSuperAdmin,
    permisosUsuario,
    onToggle,
    onAsignarLote,
    soloLectura = false,
    removidosSet = null,
    modulosExpandidos,
    onToggleModulo,
    variant = 'default',
}) {
    const secciones = useMemo(
        () => agruparModulosPorSeccionSidebar(permisosAgrupados),
        [permisosAgrupados],
    );

    if (secciones.length === 0) return null;

    return (
        <div className="space-y-5">
            {secciones.map((seccion) => (
                <section key={`${keyPrefix}${seccion.id}`} className="space-y-2">
                    <header className="px-0.5 border-b theme-border/60 pb-1.5">
                        <h4 className="text-[11px] font-black uppercase tracking-widest theme-text-muted">
                            {seccion.label}
                        </h4>
                        {seccion.descripcion && (
                            <p className="text-[11px] theme-text-muted mt-0.5 leading-snug">
                                {seccion.descripcion}
                            </p>
                        )}
                    </header>
                    <div className="space-y-2">
                        {seccion.modulos.map(({ modulo, permisos }) => {
                            const expandKey = `${keyPrefix}${modulo}`;
                            return (
                                <AcordeonModulo
                                    key={expandKey}
                                    modulo={modulo}
                                    permisosDeModulo={permisos}
                                    activos={activos}
                                    expandido={modulosExpandidos[expandKey] ?? false}
                                    onToggleExpandido={(_, abierto) => onToggleModulo(expandKey, abierto)}
                                    variant={variant}
                                >
                                    <MatrizPermisos
                                        permisosDeModulo={permisos}
                                        activos={activos}
                                        plantillasActivas={plantillasActivas}
                                        roles={roles}
                                        procedencia={procedencia}
                                        plantillaActiva={plantillaActiva}
                                        usuarioActualId={usuarioActualId}
                                        esSuperAdmin={esSuperAdmin}
                                        permisosUsuario={permisosUsuario}
                                        onToggle={onToggle}
                                        onAsignarLote={onAsignarLote}
                                        soloLectura={soloLectura}
                                        removidosSet={removidosSet}
                                    />
                                </AcordeonModulo>
                            );
                        })}
                    </div>
                </section>
            ))}
        </div>
    );
}

export default function PermisosAtomicos({
    data,
    setData,
    roles,
    todosLosPermisos,
    catalogoPermisos = [],
    permisosUsuario = [],
    esSuperAdmin = false,
    usuarioActualId = null,
    procedencia = {},
    onPlantillaPorPermisoChange,
    plantillaActiva = '',
}) {
    const [busquedaPermisos, setBusquedaPermisos] = useState('');
    const [modulosExpandidos, setModulosExpandidos] = useState({});
    const [plantillaExpandida, setPlantillaExpandida] = useState(false);

    const permisosGestionables = useMemo(
        () => filtrarPermisosAsignables(todosLosPermisos, permisosUsuario, esSuperAdmin),
        [todosLosPermisos, permisosUsuario, esSuperAdmin],
    );

    const catalogo = catalogoPermisos?.length ? catalogoPermisos : todosLosPermisos;
    const activos = data.permisos_individuales || [];
    const plantillasActivas = data.plantillas_activas?.length
        ? data.plantillas_activas
        : (plantillaActiva ? [plantillaActiva] : []);

    const diffPlantilla = useMemo(
        () => calcularDiffPlantilla(activos, plantillaActiva, roles),
        [activos, plantillaActiva, roles],
    );

    const removidosSet = useMemo(
        () => new Set(diffPlantilla.removidos),
        [diffPlantilla.removidos],
    );

    const permisosSoloLecturaActivos = useMemo(() => {
        if (esSuperAdmin) return [];
        return activos
            .filter((name) => {
                const meta = procedencia[name];
                const enCatalogoGestionable = permisosGestionables.some((p) => p.name === name);
                if (!enCatalogoGestionable) return true;
                return permisoProtegidoParaEditor(meta, usuarioActualId, esSuperAdmin);
            })
            .map((name) => {
                const permiso = (catalogo || []).find((p) => p.name === name);
                if (!permiso) return { name, id: name, meta: procedencia[name] };
                return { ...permiso, meta: procedencia[name] };
            });
    }, [esSuperAdmin, activos, procedencia, permisosGestionables, catalogo, usuarioActualId]);

    const nombresSoloLectura = useMemo(
        () => new Set(permisosSoloLecturaActivos.map((p) => p.name)),
        [permisosSoloLecturaActivos],
    );

    const asignarPermisosLote = (nombres, activar) => {
        const candidatos = (nombres || []).filter((permisoName) => {
            const meta = procedencia[permisoName];
            if (permisoProtegidoParaEditor(meta, usuarioActualId, esSuperAdmin)) return false;
            if (!usuarioPuedeAsignarPermiso(permisoName, permisosUsuario, esSuperAdmin)) return false;
            if (permisoNoDelegablePorGerente(permisoName, esSuperAdmin)) return false;
            return true;
        });
        if (candidatos.length === 0) return;

        const actuales = data.permisos_individuales || [];
        const set = new Set(actuales);
        candidatos.forEach((name) => {
            if (activar) set.add(name);
            else set.delete(name);
        });
        const nuevos = [...set];
        setData('permisos_individuales', nuevos);

        if (onPlantillaPorPermisoChange) {
            onPlantillaPorPermisoChange((prev) => {
                const next = { ...prev };
                candidatos.forEach((permisoName) => {
                    if (nuevos.includes(permisoName)) {
                        const sugerido = plantillasDePermiso(permisoName, plantillasActivas, roles)[0];
                        if (sugerido) next[permisoName] = sugerido;
                    } else {
                        delete next[permisoName];
                    }
                });
                return next;
            });
        }
    };

    const togglePermisoIndividual = (permisoName) => {
        asignarPermisosLote([permisoName], !activos.includes(permisoName));
    };

    const permisoVisibleEnRejilla = (permiso) => {
        if (esSuperAdmin) return true;
        const isAsignado = activos.includes(permiso.name);
        if (nombresSoloLectura.has(permiso.name)) return false;
        if (isAsignado) return true;
        return gerentePuedeMostrarPermisoInactivo(permiso.name, permisosUsuario, esSuperAdmin);
    };

    const permisosVisibles = useMemo(() => {
        let lista = (permisosGestionables || []).filter(permisoVisibleEnRejilla);
        const hayBusqueda = busquedaPermisos.trim().length > 0;

        if (diffPlantilla.tienePlantilla && !hayBusqueda) {
            const personalizadosSet = new Set(diffPlantilla.personalizados);
            lista = lista.filter((p) => personalizadosSet.has(p.name));

            diffPlantilla.removidos.forEach((name) => {
                if (!lista.some((p) => p.name === name)) {
                    const permiso = (catalogo || []).find((p) => p.name === name)
                        || (permisosGestionables || []).find((p) => p.name === name);
                    if (permiso) lista.push(permiso);
                }
            });
        }

        return filtrarPermisosPorBusqueda(lista, busquedaPermisos);
    }, [
        permisosGestionables,
        activos,
        nombresSoloLectura,
        esSuperAdmin,
        permisosUsuario,
        diffPlantilla,
        busquedaPermisos,
        catalogo,
    ]);

    const permisosAgrupados = useMemo(() => {
        return permisosVisibles.reduce((acc, p) => {
            const modulo = p?.name?.split('.')[0] || 'Otros';
            if (!acc[modulo]) acc[modulo] = [];
            acc[modulo].push(p);
            return acc;
        }, {});
    }, [permisosVisibles]);

    const permisosPlantillaAgrupados = useMemo(() => {
        if (!diffPlantilla.tienePlantilla) return {};
        const nombresHeredados = new Set(diffPlantilla.heredados);
        return (catalogo || [])
            .filter((p) => nombresHeredados.has(p.name))
            .reduce((acc, p) => {
                const modulo = p?.name?.split('.')[0] || 'Otros';
                if (!acc[modulo]) acc[modulo] = [];
                acc[modulo].push(p);
                return acc;
            }, {});
    }, [diffPlantilla, catalogo]);

    const soloLecturaAgrupados = useMemo(() => {
        return permisosSoloLecturaActivos.reduce((acc, p) => {
            const modulo = p?.name?.split('.')[0] || 'Otros';
            if (!acc[modulo]) acc[modulo] = [];
            acc[modulo].push(p);
            return acc;
        }, {});
    }, [permisosSoloLecturaActivos]);

    useEffect(() => {
        if (!busquedaPermisos.trim()) return;

        const modulosConCoincidencia = {};
        Object.entries(permisosAgrupados).forEach(([modulo, permisos]) => {
            if (permisos.some((p) => permisoCoincideBusqueda(p.name, busquedaPermisos))) {
                modulosConCoincidencia[modulo] = true;
            }
        });
        setModulosExpandidos((prev) => ({ ...prev, ...modulosConCoincidencia }));
    }, [busquedaPermisos, permisosAgrupados]);

    const hayRejilla = Object.keys(permisosAgrupados).length > 0;
    const haySoloLectura = permisosSoloLecturaActivos.length > 0;
    const hayPlantilla = diffPlantilla.tienePlantilla && diffPlantilla.heredados.length > 0;

    if (!hayRejilla && !haySoloLectura && !hayPlantilla) return null;

    const toggleModulo = (modulo, abierto) => {
        setModulosExpandidos((prev) => ({ ...prev, [modulo]: abierto }));
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b theme-border pb-3">
                <h3 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2">
                    <Key className="w-4 h-4 text-red-500 shrink-0" />
                    Permisos Atómicos
                </h3>
                <LeyendaOrigenPermisos />
            </div>

            {!esSuperAdmin && (
                <p className="text-[11px] font-bold theme-text-muted uppercase tracking-widest leading-relaxed">
                    Solo puedes modificar permisos que tú asignaste. Los de administración aparecen bloqueados abajo.
                </p>
            )}

            <div className="theme-field-with-icon">
                <Search className="theme-field-icon" aria-hidden />
                <input
                    type="search"
                    value={busquedaPermisos}
                    onChange={(e) => setBusquedaPermisos(e.target.value)}
                    placeholder="Buscar permiso por nombre o descripción..."
                    className="theme-input w-full pr-4 py-3 text-xs font-bold normal-case tracking-normal placeholder:uppercase placeholder:tracking-wider placeholder:text-[10px]"
                />
            </div>

            {hayPlantilla && (
                <AcordeonAnimado
                    abierto={plantillaExpandida}
                    onToggle={() => setPlantillaExpandida((prev) => !prev)}
                    className="theme-element border-blue-500/30"
                    summaryClassName="bg-blue-500/5"
                    contentClassName="p-4 border-blue-500/20 space-y-2"
                    summary={(
                        <>
                            <div className="flex items-center gap-2.5 min-w-0">
                                <Layers className="w-4 h-4 text-blue-500 shrink-0" />
                                <span className="text-xs font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 italic truncate">
                                    Permisos de la plantilla {plantillaActiva} ({diffPlantilla.heredados.length})
                                </span>
                            </div>
                            <ChevronRight
                                className={`w-4 h-4 text-blue-500 shrink-0 transition-transform duration-300 ease-out ${plantillaExpandida ? 'rotate-90' : ''}`}
                                aria-hidden
                            />
                        </>
                    )}
                >
                        <SeccionesSidebarPermisos
                            permisosAgrupados={permisosPlantillaAgrupados}
                            keyPrefix="tpl-"
                            activos={activos}
                            plantillasActivas={plantillasActivas}
                            roles={roles}
                            procedencia={procedencia}
                            plantillaActiva={plantillaActiva}
                            usuarioActualId={usuarioActualId}
                            esSuperAdmin={esSuperAdmin}
                            permisosUsuario={permisosUsuario}
                            onToggle={togglePermisoIndividual}
                            soloLectura
                            modulosExpandidos={modulosExpandidos}
                            onToggleModulo={toggleModulo}
                            variant="plantilla"
                        />
                </AcordeonAnimado>
            )}

            {diffPlantilla.tienePlantilla && (
                <div className="flex items-center gap-2 text-xs font-black uppercase tracking-widest theme-text-muted">
                    <UserPen className="w-4 h-4 text-orange-500 shrink-0" />
                    Permisos personalizados ({diffPlantilla.personalizados.length})
                </div>
            )}

            {hayRejilla && (
                <SeccionesSidebarPermisos
                    permisosAgrupados={permisosAgrupados}
                    activos={activos}
                    plantillasActivas={plantillasActivas}
                    roles={roles}
                    procedencia={procedencia}
                    plantillaActiva={plantillaActiva}
                    usuarioActualId={usuarioActualId}
                    esSuperAdmin={esSuperAdmin}
                    permisosUsuario={permisosUsuario}
                    onToggle={togglePermisoIndividual}
                    onAsignarLote={asignarPermisosLote}
                    removidosSet={removidosSet}
                    modulosExpandidos={modulosExpandidos}
                    onToggleModulo={toggleModulo}
                />
            )}

            {diffPlantilla.tienePlantilla && !hayRejilla && diffPlantilla.personalizados.length === 0 && !busquedaPermisos.trim() && (
                <p className="text-xs font-bold theme-text-muted italic px-2 leading-relaxed">
                    Sin personalizaciones respecto a la plantilla. Usa el buscador para agregar permisos adicionales o expande la plantilla arriba.
                </p>
            )}

            {busquedaPermisos.trim() && !hayRejilla && !hayPlantilla && (
                <p className="text-xs font-bold theme-text-muted italic px-2">
                    Sin resultados para &quot;{busquedaPermisos}&quot;
                </p>
            )}

            {haySoloLectura && (
                <div className={`space-y-2 ${hayRejilla || hayPlantilla ? 'mt-4 pt-4 border-t theme-border' : ''}`}>
                    <h4 className="text-xs font-black uppercase tracking-widest theme-text-muted flex items-center gap-2">
                        <ShieldAlert className="w-4 h-4 text-amber-500 shrink-0" />
                        Permisos asignados por administración (solo lectura)
                    </h4>
                    <SeccionesSidebarPermisos
                        permisosAgrupados={soloLecturaAgrupados}
                        keyPrefix="sl-"
                        activos={activos}
                        plantillasActivas={plantillasActivas}
                        roles={roles}
                        procedencia={procedencia}
                        plantillaActiva={plantillaActiva}
                        usuarioActualId={usuarioActualId}
                        esSuperAdmin={esSuperAdmin}
                        permisosUsuario={permisosUsuario}
                        onToggle={() => {}}
                        soloLectura
                        modulosExpandidos={modulosExpandidos}
                        onToggleModulo={toggleModulo}
                        variant="soloLectura"
                    />
                </div>
            )}
        </div>
    );
}
