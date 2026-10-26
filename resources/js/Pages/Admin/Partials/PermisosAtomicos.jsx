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
    seccionDeModulo,
    etiquetaModuloUi,
    filtrarPermisosPorBusqueda,
    calcularDiffPlantilla,
    permisoCoincideBusqueda,
} from '../../../utils/permisos';
import PermisoOrigenIndicador, { IndicadorPermisoProtegido, LeyendaOrigenPermisos } from './PermisoOrigenIndicador';
import ResumenCapacidadesPdv from './ResumenCapacidadesPdv';

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
    busqueda = '',
    submodsControl = null,
    onSubmodsControl = null,
}) {
    const moduloRoot = permisosDeModulo?.[0]?.name?.split('.')[0] || '';
    const submodulos = useMemo(
        () => agruparPermisosPorSubmodulo(moduloRoot, permisosDeModulo),
        [moduloRoot, permisosDeModulo],
    );
    const usarAcordeonSubmodulo = submodulos.length > 4;
    const [submodsLocales, setSubmodsLocales] = useState({});
    const submodsExpandidos = submodsControl ?? submodsLocales;
    const setSubmodsExpandidos = (updater) => {
        const aplicar = (prev) => (typeof updater === 'function' ? updater(prev) : updater);
        if (onSubmodsControl) onSubmodsControl(aplicar);
        else setSubmodsLocales(aplicar);
    };

    useEffect(() => {
        if (!usarAcordeonSubmodulo || submodulos.length === 0) return;
        const consulta = busqueda.trim();
        const prefijo = `${moduloRoot}:`;
        setSubmodsExpandidos((prev) => {
            if (consulta) {
                let cambio = false;
                const next = { ...prev };
                submodulos.forEach((grupo) => {
                    const clave = `${prefijo}${grupo.id}`;
                    const coincide = grupo.permisos.some((permiso) => permisoCoincideBusqueda(permiso.name, consulta));
                    if (coincide && !next[clave]) {
                        next[clave] = true;
                        cambio = true;
                    }
                });
                return cambio ? next : prev;
            }
            if (Object.keys(prev).some((clave) => clave.startsWith(prefijo))) return prev;
            const inicial = { ...prev };
            submodulos.slice(0, 2).forEach((grupo) => { inicial[`${prefijo}${grupo.id}`] = true; });
            return inicial;
        });
    }, [usarAcordeonSubmodulo, submodulos, busqueda]);

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

        const motivoBloqueo = protegido
            ? 'Protegido contra modificaciones'
            : noDelegable
                ? 'No se puede delegar este permiso'
                : '';

        return (
            <label
                className={`usuarios-permiso-fila ${deshabilitado ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'}`}
            >
                <input
                    type="checkbox"
                    checked={isAsignado}
                    disabled={deshabilitado}
                    onChange={() => !deshabilitado && onToggle(permiso.name)}
                    className="rounded accent-[var(--color-primario)] cursor-pointer disabled:cursor-not-allowed"
                    aria-label={`${etiqueta}${ayuda ? `. ${ayuda}` : ''}. ${permiso.name}${isAsignado ? '. Activo' : '. Inactivo'}`}
                />
                {conEtiqueta && (
                    <span className="usuarios-permiso-texto">
                        <span className="flex flex-wrap items-center gap-2">
                            <span
                                className={`usuarios-permiso-nombre ${
                                    isRemovido
                                        ? 'line-through theme-text-muted'
                                        : esExcepcion
                                            ? 'theme-text-aviso'
                                            : 'theme-text-main'
                                }`}
                                title={permiso.name}
                            >
                                {etiqueta}
                            </span>
                            {motivoBloqueo && <IndicadorPermisoProtegido motivo={motivoBloqueo} />}
                        </span>
                        {ayuda ? (
                            <span className="usuarios-permiso-desc theme-text-muted" title={ayuda}>{ayuda}</span>
                        ) : (
                            <span className="usuarios-permiso-desc theme-text-muted">Descripción pendiente de validación</span>
                        )}
                        {isAsignado && (
                            <span className="usuarios-permiso-meta">
                                <PermisoOrigenIndicador
                                    meta={meta}
                                    isDePlantilla={isDePlantilla}
                                    plantillaNombre={plantillaActiva}
                                    usuarioActualId={usuarioActualId}
                                />
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
                    <div className="grid grid-cols-1 gap-x-4 gap-y-1">
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
                        <div className="grid grid-cols-1 gap-x-4 gap-y-1">
                            {excepciones.map((p) => (
                                <div key={p.id ?? p.name}>{renderCheckbox(p, { conEtiqueta: true })}</div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        );
    };

    const renderBotonAlternarGrupo = (grupo) => {
        const toggleables = grupo.permisos.filter(permisoEsToggleable);
        const todosActivos = toggleables.length > 0
            && toggleables.every((p) => activos.includes(p.name));
        const puedeAlternar = toggleables.length > 0 && !soloLectura;

        if (!puedeAlternar) {
            return null;
        }

        return (
            <button
                type="button"
                onClick={() => togglePermisos(grupo.permisos)}
                className="shrink-0 text-[10px] font-bold uppercase tracking-wide theme-text-muted hover:text-[var(--color-primario)] transition-colors"
                aria-pressed={todosActivos}
            >
                {todosActivos ? 'Quitar todos' : 'Seleccionar todos'}
            </button>
        );
    };

    const renderEncabezadoSubmodulo = (grupo, { compacto = false, omitirAccionMasiva = false } = {}) => {
        const activosEnGrupo = grupo.permisos.filter((p) => activos.includes(p.name)).length;

        return (
            <div className={`flex items-start justify-between gap-2 min-w-0 w-full ${compacto ? '' : ''}`}>
                <div className="min-w-0">
                    <h4 className="usuarios-permiso-nombre text-sm font-semibold theme-text-main m-0">
                        {grupo.label}
                        <span className="usuarios-permiso-cuenta ml-2">
                            {activosEnGrupo}/{grupo.permisos.length}
                        </span>
                    </h4>
                    {grupo.descripcion && (
                        <p className="text-[11px] theme-text-muted mt-0.5 leading-snug font-normal normal-case tracking-normal">
                            {grupo.descripcion}
                        </p>
                    )}
                </div>
                {!omitirAccionMasiva && renderBotonAlternarGrupo(grupo)}
            </div>
        );
    };

    const renderBloqueSubmodulo = (grupo) => {
        if (usarAcordeonSubmodulo) {
            const clave = `${moduloRoot}:${grupo.id}`;
            const abierto = submodsExpandidos[clave] ?? false;
            return (
                <AcordeonAnimado
                    key={grupo.id}
                    abierto={abierto}
                    onToggle={() => setSubmodsExpandidos((prev) => ({ ...prev, [clave]: !abierto }))}
                    className="theme-element"
                    contentClassName="p-3 pt-0 space-y-2"
                    summary={(
                        <>
                            {renderEncabezadoSubmodulo(grupo, { compacto: true, omitirAccionMasiva: true })}
                            <ChevronRight
                                className={`w-4 h-4 theme-text-muted shrink-0 ${abierto ? 'rotate-90' : ''}`}
                                aria-hidden
                            />
                        </>
                    )}
                    headerActions={renderBotonAlternarGrupo(grupo)}
                    summaryClassName="p-3 items-start"
                >
                    {renderListaPermisos(grupo.permisos)}
                </AcordeonAnimado>
            );
        }

        return (
            <section key={grupo.id} className="border theme-border rounded-xl p-3 space-y-2">
                {renderEncabezadoSubmodulo(grupo)}
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
    headerActions = null,
    children,
    className = '',
    summaryClassName = '',
    contentClassName = '',
}) {
    return (
        <div className={`rounded-2xl overflow-hidden border theme-border ${className}`}>
            <div
                className={`w-full p-4 flex justify-between items-start gap-3 ${summaryClassName}`}
            >
                <button
                    type="button"
                    onClick={onToggle}
                    aria-expanded={abierto}
                    className="flex-1 min-w-0 flex justify-between items-center gap-3 cursor-pointer select-none outline-none text-left transition-colors hover:bg-black/[0.02] dark:hover:bg-white/[0.02] focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] focus-visible:ring-inset rounded-lg -m-1 p-1"
                >
                    {summary}
                </button>
                {headerActions}
            </div>
            {abierto && (
                <div className={`border-t theme-border ${contentClassName}`}>
                    {children}
                </div>
            )}
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
        plantilla: 'theme-element',
        soloLectura: 'theme-element',
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
                        <span className="usuarios-permiso-modulo-texto text-sm font-semibold theme-text-main">
                            <span className="usuarios-permiso-nombre">{titulo}</span>
                            <span className="usuarios-permiso-cuenta">
                                {activosEnModulo}/{total}
                            </span>
                        </span>
                    </div>
                    <ChevronRight
                        className={`w-4 h-4 theme-text-muted shrink-0 ${expandido ? 'rotate-90' : ''}`}
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
    sucursalesCatalogo = [],
    sucursalIds = [],
    sucursalPrincipalId = null,
    busqueda = '',
    colapsarSecciones = false,
    seccionesExpandidas = {},
    onToggleSeccion = null,
    submodsExpandidos = null,
    onSubmodsExpandidos = null,
}) {
    const secciones = useMemo(
        () => agruparModulosPorSeccionSidebar(permisosAgrupados),
        [permisosAgrupados],
    );

    if (secciones.length === 0) return null;

    return (
        <div className="space-y-5">
            {secciones.map((seccion) => {
                const total = seccion.modulos.reduce((suma, item) => suma + item.permisos.length, 0);
                const activosSeccion = seccion.modulos.reduce(
                    (suma, item) => suma + item.permisos.filter((permiso) => activos.includes(permiso.name)).length,
                    0,
                );
                const abierta = !colapsarSecciones || Boolean(seccionesExpandidas[`${keyPrefix}${seccion.id}`]);
                const encabezado = (
                    <>
                        <span className="usuarios-permiso-seccion-texto">
                            <strong className="usuarios-permiso-nombre">{seccion.label}</strong>
                            {seccion.descripcion && (
                                <span className="block text-sm theme-text-muted mt-0.5">{seccion.descripcion}</span>
                            )}
                        </span>
                        <span className="usuarios-permiso-cuenta">{activosSeccion}/{total}</span>
                        {colapsarSecciones && (
                            <ChevronRight className={`w-4 h-4 theme-text-muted shrink-0 ${abierta ? 'rotate-90' : ''}`} aria-hidden />
                        )}
                    </>
                );
                return (
                <section key={`${keyPrefix}${seccion.id}`} className="space-y-2">
                    {colapsarSecciones ? (
                        <button
                            type="button"
                            className="usuarios-permiso-seccion"
                            aria-expanded={abierta}
                            onClick={() => onToggleSeccion?.(`${keyPrefix}${seccion.id}`, !abierta)}
                        >
                            {encabezado}
                        </button>
                    ) : (
                        <header className="usuarios-permiso-seccion">{encabezado}</header>
                    )}
                    {abierta && !keyPrefix && seccion.id === 'punto_venta' && (
                        <ResumenCapacidadesPdv
                            permisos={activos}
                            sucursalesCatalogo={sucursalesCatalogo}
                            sucursalIds={sucursalIds}
                            sucursalPrincipalId={sucursalPrincipalId}
                        />
                    )}
                    {abierta && (
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
                                        busqueda={busqueda}
                                        submodsControl={submodsExpandidos}
                                        onSubmodsControl={onSubmodsExpandidos}
                                    />
                                </AcordeonModulo>
                            );
                        })}
                    </div>
                    )}
                </section>
                );
            })}
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
    sucursalesCatalogo = [],
    sucursalIds = [],
    sucursalPrincipalId = null,
    modulosInicialesExpandidos = [],
    colapsarSecciones = false,
    expansion = null,
    onExpansion = null,
}) {
    const controlado = typeof onExpansion === 'function';
    const [expansionLocal, setExpansionLocal] = useState(() => {
        const modulos = {};
        const secciones = {};
        (modulosInicialesExpandidos || []).forEach((modulo) => {
            modulos[modulo] = true;
            secciones[seccionDeModulo(modulo)] = true;
        });
        return { busqueda: '', modulos, secciones, submodulos: {} };
    });
    const estadoExpansion = controlado ? expansion : expansionLocal;
    const actualizarExpansion = (parcial) => {
        const aplicar = (prev) => ({ ...prev, ...(typeof parcial === 'function' ? parcial(prev) : parcial) });
        if (controlado) onExpansion(aplicar);
        else setExpansionLocal(aplicar);
    };
    const busquedaPermisos = estadoExpansion?.busqueda || '';
    const setBusquedaPermisos = (valor) => actualizarExpansion((prev) => ({
        busqueda: typeof valor === 'function' ? valor(prev.busqueda) : valor,
    }));
    const modulosExpandidos = estadoExpansion?.modulos || {};
    const setModulosExpandidos = (updater) => actualizarExpansion((prev) => ({
        modulos: typeof updater === 'function' ? updater(prev.modulos) : updater,
    }));
    const seccionesExpandidas = estadoExpansion?.secciones || {};
    const setSeccionesExpandidas = (updater) => actualizarExpansion((prev) => ({
        secciones: typeof updater === 'function' ? updater(prev.secciones) : updater,
    }));
    const submodsExpandidos = estadoExpansion?.submodulos || {};
    const setSubmodsExpandidos = (updater) => actualizarExpansion((prev) => ({
        submodulos: typeof updater === 'function' ? updater(prev.submodulos) : updater,
    }));
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
        const seccionesConCoincidencia = {};
        Object.entries(permisosAgrupados).forEach(([modulo, permisos]) => {
            if (permisos.some((permiso) => permisoCoincideBusqueda(permiso.name, busquedaPermisos))) {
                modulosConCoincidencia[modulo] = true;
                seccionesConCoincidencia[seccionDeModulo(modulo)] = true;
            }
        });
        setModulosExpandidos((prev) => {
            const faltan = Object.keys(modulosConCoincidencia).some((clave) => !prev[clave]);
            return faltan ? { ...prev, ...modulosConCoincidencia } : prev;
        });
        if (colapsarSecciones) {
            setSeccionesExpandidas((prev) => {
                const faltan = Object.keys(seccionesConCoincidencia).some((clave) => !prev[clave]);
                return faltan ? { ...prev, ...seccionesConCoincidencia } : prev;
            });
        }
    }, [busquedaPermisos, permisosAgrupados, colapsarSecciones]);

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
                <h3 className="text-sm font-semibold theme-text-main flex items-center gap-2 m-0">
                    <Key className="w-4 h-4 theme-text-muted shrink-0" />
                    Permisos
                </h3>
                <LeyendaOrigenPermisos />
            </div>

            {!esSuperAdmin && (
                <p className="text-sm theme-text-muted leading-relaxed">
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
                    className="theme-input w-full pr-4 py-3"
                />
            </div>

            {hayPlantilla && (
                <AcordeonAnimado
                    abierto={plantillaExpandida}
                    onToggle={() => setPlantillaExpandida((prev) => !prev)}
                    className="theme-element"
                    contentClassName="p-4 space-y-2"
                    summary={(
                        <>
                            <div className="flex items-center gap-2.5 min-w-0">
                                <Layers className="w-4 h-4 theme-text-primario shrink-0" />
                                <span className="usuarios-permiso-nombre text-sm font-semibold theme-text-main">
                                    Permisos de la plantilla {plantillaActiva}
                                    <span className="usuarios-permiso-cuenta ml-2">{diffPlantilla.heredados.length}</span>
                                </span>
                            </div>
                            <ChevronRight
                                className={`w-4 h-4 theme-text-muted shrink-0 ${plantillaExpandida ? 'rotate-90' : ''}`}
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
                            busqueda={busquedaPermisos}
                            colapsarSecciones={colapsarSecciones}
                            seccionesExpandidas={seccionesExpandidas}
                            onToggleSeccion={(id, abierto) => setSeccionesExpandidas((prev) => ({ ...prev, [id]: abierto }))}
                            submodsExpandidos={submodsExpandidos}
                            onSubmodsExpandidos={setSubmodsExpandidos}
                            variant="plantilla"
                        />
                </AcordeonAnimado>
            )}

            {diffPlantilla.tienePlantilla && (
                <div className="flex items-center gap-2 text-sm theme-text-muted">
                    <UserPen className="w-4 h-4 shrink-0" />
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
                    busqueda={busquedaPermisos}
                    colapsarSecciones={colapsarSecciones}
                    seccionesExpandidas={seccionesExpandidas}
                    onToggleSeccion={(id, abierto) => setSeccionesExpandidas((prev) => ({ ...prev, [id]: abierto }))}
                    submodsExpandidos={submodsExpandidos}
                    onSubmodsExpandidos={setSubmodsExpandidos}
                    sucursalesCatalogo={sucursalesCatalogo}
                    sucursalIds={sucursalIds}
                    sucursalPrincipalId={sucursalPrincipalId}
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
                    <h4 className="text-sm font-semibold theme-text-main flex items-center gap-2">
                        <ShieldAlert className="w-4 h-4 theme-text-aviso shrink-0" />
                        Permisos asignados por administración
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
                        busqueda={busquedaPermisos}
                        colapsarSecciones={colapsarSecciones}
                        seccionesExpandidas={seccionesExpandidas}
                        onToggleSeccion={(id, abierto) => setSeccionesExpandidas((prev) => ({ ...prev, [id]: abierto }))}
                        submodsExpandidos={submodsExpandidos}
                        onSubmodsExpandidos={setSubmodsExpandidos}
                        variant="soloLectura"
                    />
                </div>
            )}
        </div>
    );
}
