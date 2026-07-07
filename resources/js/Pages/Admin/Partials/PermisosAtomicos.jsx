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
    etiquetaPermiso,
    permisoProtegidoParaEditor,
    permisoNoDelegablePorGerente,
    gerentePuedeMostrarPermisoInactivo,
    agruparPermisosEnMatriz,
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
    soloLectura = false,
    removidosSet = null,
}) {
    const { columnas, filas } = useMemo(
        () => agruparPermisosEnMatriz(permisosDeModulo),
        [permisosDeModulo],
    );

    const columnasVisibles = columnas.filter((col) => {
        if (col.key === 'otros') {
            return filas.some((f) => f.celdas.otros.length > 0);
        }
        return filas.some((f) => f.celdas[col.key]);
    });

    if (filas.length === 0) return null;

    const permisoEsToggleable = (permiso) => {
        const meta = procedencia[permiso.name];
        const protegido = permisoProtegidoParaEditor(meta, usuarioActualId, esSuperAdmin);
        const noDelegable = permisoNoDelegablePorGerente(permiso.name, esSuperAdmin);
        const puedeAsignar = usuarioPuedeAsignarPermiso(permiso.name, permisosUsuario, esSuperAdmin);
        return !soloLectura && !protegido && !noDelegable && puedeAsignar;
    };

    const obtenerPermisosDeFila = (fila) => {
        const permisos = [];
        columnasVisibles.forEach((col) => {
            if (col.key === 'otros') {
                permisos.push(...fila.celdas.otros);
            } else if (fila.celdas[col.key]) {
                permisos.push(fila.celdas[col.key]);
            }
        });
        return permisos;
    };

    const toggleFila = (fila) => {
        const permisos = obtenerPermisosDeFila(fila).filter(permisoEsToggleable);
        if (permisos.length === 0) return;

        const todosActivos = permisos.every((p) => activos.includes(p.name));
        permisos.forEach((p) => {
            const isAsignado = activos.includes(p.name);
            if (todosActivos ? isAsignado : !isAsignado) {
                onToggle(p.name);
            }
        });
    };

    const renderCeldaContenido = (permiso, esOtros = false) => {
        const isAsignado = activos.includes(permiso.name);
        const isRemovido = removidosSet?.has(permiso.name);
        const isDePlantilla = permisoDePlantilla(permiso.name, plantillasActivas, roles);
        const meta = procedencia[permiso.name];
        const protegido = permisoProtegidoParaEditor(meta, usuarioActualId, esSuperAdmin);
        const noDelegable = permisoNoDelegablePorGerente(permiso.name, esSuperAdmin);
        const puedeAsignar = usuarioPuedeAsignarPermiso(permiso.name, permisosUsuario, esSuperAdmin);
        const deshabilitado = soloLectura || protegido || noDelegable || !puedeAsignar;
        const ayuda = descripcionPermiso(permiso.name);
        const etiqueta = esOtros ? etiquetaPermiso(permiso.name) : null;

        return (
            <label
                className={`inline-flex flex-col items-center gap-0.5 cursor-pointer ${deshabilitado ? 'opacity-50 cursor-not-allowed' : ''}`}
                title={ayuda || etiqueta || permiso.name}
            >
                <span className="inline-flex items-center gap-1.5">
                    <input
                        type="checkbox"
                        checked={isAsignado}
                        disabled={deshabilitado}
                        onChange={() => !deshabilitado && onToggle(permiso.name)}
                        className="w-4 h-4 rounded accent-[var(--color-primario)] cursor-pointer disabled:cursor-not-allowed"
                        aria-label={`${etiquetaPermiso(permiso.name)}${isAsignado ? ' activo' : ' inactivo'}`}
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
                {esOtros && (
                    <span className={`text-[10px] font-bold normal-case tracking-normal leading-tight max-w-[72px] text-center ${isRemovido ? 'line-through opacity-60' : 'theme-text-muted'}`}>
                        {etiqueta}
                    </span>
                )}
            </label>
        );
    };

    const renderCelda = (permiso, esOtros = false) => {
        if (!permiso) {
            return <td className="px-2 py-1.5 text-center text-xs theme-text-muted opacity-30">—</td>;
        }

        return (
            <td className="px-2 py-1.5 text-center">
                {renderCeldaContenido(permiso, esOtros)}
            </td>
        );
    };

    const thSticky = 'sticky top-0 z-20 theme-element';
    const thEntidad = 'sticky left-0 top-0 z-30 theme-element';
    const tdEntidad = 'sticky left-0 z-10 theme-element group-hover:bg-black/[0.02] dark:group-hover:bg-white/[0.02]';

    return (
        <div className="overflow-auto max-h-[min(60vh,480px)] border theme-border rounded-xl">
            <table className="w-full text-left border-collapse min-w-[320px]">
                <thead>
                    <tr className="border-b theme-border">
                        <th className={`px-2 py-2 text-[10px] font-black uppercase tracking-widest theme-text-muted text-left ${thEntidad}`}>
                            Entidad
                        </th>
                        {columnasVisibles.map((col) => (
                            <th
                                key={col.key}
                                className={`px-2 py-2 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center whitespace-nowrap ${thSticky}`}
                            >
                                {col.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {filas.map((fila) => {
                        const permisosToggleables = obtenerPermisosDeFila(fila).filter(permisoEsToggleable);
                        const todosActivosFila = permisosToggleables.length > 0
                            && permisosToggleables.every((p) => activos.includes(p.name));
                        const puedeAlternarFila = permisosToggleables.length > 0 && !soloLectura;

                        return (
                            <tr key={fila.entidad} className="group border-b theme-border/50 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                <td className={`px-2 py-1.5 text-xs font-bold uppercase tracking-wide theme-text-main whitespace-nowrap ${tdEntidad}`}>
                                    {puedeAlternarFila ? (
                                        <button
                                            type="button"
                                            onClick={() => toggleFila(fila)}
                                            className="text-left hover:text-[var(--color-primario)] transition-colors underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] rounded"
                                            title={todosActivosFila
                                                ? 'Quitar todos los permisos de esta entidad'
                                                : 'Seleccionar todos los permisos de esta entidad'}
                                            aria-pressed={todosActivosFila}
                                        >
                                            {fila.entidadLabel}
                                        </button>
                                    ) : (
                                        fila.entidadLabel
                                    )}
                                </td>
                                {columnasVisibles.map((col) => {
                                    if (col.key === 'otros') {
                                        const otros = fila.celdas.otros;
                                        if (otros.length === 0) {
                                            return <td key={col.key} className="px-2 py-1.5 text-center opacity-30">—</td>;
                                        }
                                        if (otros.length === 1) {
                                            return <React.Fragment key={col.key}>{renderCelda(otros[0], true)}</React.Fragment>;
                                        }
                                        return (
                                            <td key={col.key} className="px-2 py-1.5">
                                                <div className="flex flex-wrap gap-1 justify-center items-start">
                                                    {otros.map((p) => (
                                                        <div key={p.id ?? p.name} className="inline-flex">
                                                            {renderCeldaContenido(p, true)}
                                                        </div>
                                                    ))}
                                                </div>
                                            </td>
                                        );
                                    }
                                    return <React.Fragment key={col.key}>{renderCelda(fila.celdas[col.key])}</React.Fragment>;
                                })}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
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
                            Módulo: {modulo}
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

    const togglePermisoIndividual = (permisoName) => {
        const meta = procedencia[permisoName];
        if (permisoProtegidoParaEditor(meta, usuarioActualId, esSuperAdmin)) return;
        if (!usuarioPuedeAsignarPermiso(permisoName, permisosUsuario, esSuperAdmin)) return;
        if (permisoNoDelegablePorGerente(permisoName, esSuperAdmin)) return;

        const actuales = data.permisos_individuales || [];
        const nuevos = actuales.includes(permisoName)
            ? actuales.filter((item) => item !== permisoName)
            : [...actuales, permisoName];

        setData('permisos_individuales', nuevos);

        if (onPlantillaPorPermisoChange) {
            onPlantillaPorPermisoChange((prev) => {
                const next = { ...prev };
                if (nuevos.includes(permisoName)) {
                    const sugerido = plantillasDePermiso(permisoName, plantillasActivas, roles)[0];
                    if (sugerido) next[permisoName] = sugerido;
                } else {
                    delete next[permisoName];
                }
                return next;
            });
        }
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
                        {Object.entries(permisosPlantillaAgrupados).map(([modulo, permisosDeModulo]) => (
                            <AcordeonModulo
                                key={`tpl-${modulo}`}
                                modulo={modulo}
                                permisosDeModulo={permisosDeModulo}
                                activos={activos}
                                expandido={modulosExpandidos[`tpl-${modulo}`] ?? false}
                                onToggleExpandido={(_, abierto) => toggleModulo(`tpl-${modulo}`, abierto)}
                                variant="plantilla"
                            >
                                <MatrizPermisos
                                    permisosDeModulo={permisosDeModulo}
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
                                />
                            </AcordeonModulo>
                        ))}
                </AcordeonAnimado>
            )}

            {diffPlantilla.tienePlantilla && (
                <div className="flex items-center gap-2 text-xs font-black uppercase tracking-widest theme-text-muted">
                    <UserPen className="w-4 h-4 text-orange-500 shrink-0" />
                    Permisos personalizados ({diffPlantilla.personalizados.length})
                </div>
            )}

            {hayRejilla && (
                <div className="space-y-3">
                    {Object.entries(permisosAgrupados).map(([modulo, permisosDeModulo]) => (
                        <AcordeonModulo
                            key={modulo}
                            modulo={modulo}
                            permisosDeModulo={permisosDeModulo}
                            activos={activos}
                            expandido={modulosExpandidos[modulo] ?? false}
                            onToggleExpandido={toggleModulo}
                        >
                            <MatrizPermisos
                                permisosDeModulo={permisosDeModulo}
                                activos={activos}
                                plantillasActivas={plantillasActivas}
                                roles={roles}
                                procedencia={procedencia}
                                plantillaActiva={plantillaActiva}
                                usuarioActualId={usuarioActualId}
                                esSuperAdmin={esSuperAdmin}
                                permisosUsuario={permisosUsuario}
                                onToggle={togglePermisoIndividual}
                                removidosSet={removidosSet}
                            />
                        </AcordeonModulo>
                    ))}
                </div>
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
                    {Object.entries(soloLecturaAgrupados).map(([modulo, permisosDeModulo]) => (
                        <AcordeonModulo
                            key={`sl-${modulo}`}
                            modulo={modulo}
                            permisosDeModulo={permisosDeModulo}
                            activos={activos}
                            expandido={modulosExpandidos[`sl-${modulo}`] ?? false}
                            onToggleExpandido={(_, abierto) => toggleModulo(`sl-${modulo}`, abierto)}
                            variant="soloLectura"
                        >
                            <MatrizPermisos
                                permisosDeModulo={permisosDeModulo}
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
                            />
                        </AcordeonModulo>
                    ))}
                </div>
            )}
        </div>
    );
}
