import React, { useMemo } from 'react';
import { Key, ShieldCheck, Check, ChevronRight, Lock, ShieldAlert } from 'lucide-react';
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
} from '../../../utils/permisos';

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
}) {
    const permisosGestionables = useMemo(
        () => filtrarPermisosAsignables(todosLosPermisos, permisosUsuario, esSuperAdmin),
        [todosLosPermisos, permisosUsuario, esSuperAdmin]
    );

    const catalogo = catalogoPermisos?.length ? catalogoPermisos : todosLosPermisos;
    const activos = data.permisos_individuales || [];

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
        [permisosSoloLecturaActivos]
    );

    const plantillasActivas = data.plantillas_activas || [];

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

    const permisosAgrupados = useMemo(() => {
        return (permisosGestionables || [])
            .filter(permisoVisibleEnRejilla)
            .reduce((acc, p) => {
                const modulo = p?.name?.split('.')[0] || 'Otros';
                if (!acc[modulo]) acc[modulo] = [];
                acc[modulo].push(p);
                return acc;
            }, {});
    }, [permisosGestionables, activos, nombresSoloLectura, esSuperAdmin, permisosUsuario]);

    const soloLecturaAgrupados = useMemo(() => {
        return permisosSoloLecturaActivos.reduce((acc, p) => {
            const modulo = p?.name?.split('.')[0] || 'Otros';
            if (!acc[modulo]) acc[modulo] = [];
            acc[modulo].push(p);
            return acc;
        }, {});
    }, [permisosSoloLecturaActivos]);

    const hayRejilla = Object.keys(permisosAgrupados).length > 0;
    const haySoloLectura = permisosSoloLecturaActivos.length > 0;

    if (!hayRejilla && !haySoloLectura) return null;

    const esAsignadoPorMi = (meta) =>
        meta?.asignado_por?.id != null
        && usuarioActualId != null
        && Number(meta.asignado_por.id) === Number(usuarioActualId);

    const esOrigenSistema = (meta) =>
        !meta || meta?.plantilla_origen === 'sistema:migracion';

    const clasePermisoActivo = (meta, isDePlantilla) => {
        if (esOrigenSistema(meta)) {
            return 'border-teal-500/40 bg-teal-500/10 text-teal-600 dark:text-teal-400';
        }
        if (isDePlantilla && !meta?.asignado_por) {
            return 'border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400';
        }
        if (esAsignadoPorMi(meta)) {
            return 'border-orange-500 bg-orange-500/10 text-orange-600';
        }
        if (meta?.asignado_por) {
            return 'border-violet-500/40 bg-violet-500/10 text-violet-600 dark:text-violet-400';
        }
        return 'border-orange-500/40 bg-orange-500/5 text-orange-500/80';
    };

    const renderEtiquetaAsignador = (meta) => {
        if (meta?.plantilla_origen === 'sistema:migracion') {
            return (
                <span className="text-[8px] font-bold normal-case tracking-wide opacity-80 italic text-teal-500">
                    origen: actualización del sistema
                </span>
            );
        }
        if (meta?.asignado_por?.nombre) {
            return (
                <span className="text-[8px] font-bold normal-case tracking-wide opacity-80 italic text-violet-500">
                    asignado por: {meta.asignado_por.nombre}
                </span>
            );
        }
        if (meta?.plantilla_origen) {
            return (
                <span className="text-[8px] font-bold normal-case tracking-wide opacity-70 italic text-purple-500">
                    plantilla: {meta.plantilla_origen}
                </span>
            );
        }
        return null;
    };

    return (
        <div>
            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                <Key className="w-4 h-4 text-red-500" /> Permisos Atómicos
            </h3>
            <p className="text-[10px] theme-text-muted mb-4 font-bold tracking-widest">
                INDICADORES: <span className="text-blue-500 mx-1">AZUL</span> sugerido de plantilla.{' '}
                <span className="text-orange-500 mx-1">NARANJA</span> asignado por ti.{' '}
                <span className="text-violet-500 mx-1">VIOLETA</span> asignado por otro.{' '}
                <span className="text-teal-500 mx-1">VERDE</span> heredado por actualización del sistema.
                {!esSuperAdmin && (
                    <span className="block mt-1 text-amber-600 dark:text-amber-400">
                        Solo puedes modificar permisos que tú asignaste. Los de administración aparecen bloqueados abajo.
                    </span>
                )}
            </p>

            {hayRejilla && (
                <div className="space-y-2">
                    {Object.entries(permisosAgrupados).map(([modulo, permisosDeModulo]) => (
                        <details key={modulo} open className="group theme-element rounded-2xl overflow-hidden border theme-border">
                            <summary className="p-4 cursor-pointer flex justify-between items-center select-none outline-none">
                                <div className="flex items-center gap-3">
                                    <ShieldCheck className="w-4 h-4 theme-text-muted" />
                                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-main italic">
                                        Módulo: {modulo}
                                    </span>
                                </div>
                                <ChevronRight className="w-4 h-4 group-open:rotate-90 transition-transform duration-300 theme-text-muted" />
                            </summary>
                            <div className="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 border-t theme-border">
                                {(permisosDeModulo || []).map((permiso) => {
                                    const isDePlantilla = permisoDePlantilla(permiso.name, plantillasActivas, roles);
                                    const isAsignado = activos.includes(permiso.name);
                                    const sugeridoDe = plantillasDePermiso(permiso.name, plantillasActivas, roles);
                                    const meta = procedencia[permiso.name];
                                    const ayuda = descripcionPermiso(permiso.name);

                                    return (
                                        <button
                                            key={permiso.id}
                                            type="button"
                                            onClick={() => togglePermisoIndividual(permiso.name)}
                                            className={`flex flex-col items-start gap-1 px-4 py-3 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all ${
                                                isAsignado
                                                    ? clasePermisoActivo(meta, isDePlantilla)
                                                    : isDePlantilla
                                                      ? 'border-blue-500/20 bg-blue-500/5 text-blue-500/70 hover:border-blue-500/40'
                                                      : 'theme-border theme-text-muted hover:border-gray-400'
                                            }`}
                                        >
                                            <span className="flex justify-between items-center w-full gap-2">
                                                <span>{etiquetaPermiso(permiso.name)}</span>
                                                {isAsignado ? (
                                                    <Check className="w-3 h-3 shrink-0" />
                                                ) : null}
                                            </span>
                                            {ayuda && (
                                                <span className="text-[8px] font-medium normal-case tracking-normal opacity-80 leading-snug">
                                                    {ayuda}
                                                </span>
                                            )}
                                            {isAsignado && isDePlantilla && sugeridoDe.length > 0 && !meta?.asignado_por && (
                                                <span className="text-[8px] font-bold normal-case tracking-wide opacity-80 italic">
                                                    plantilla: {sugeridoDe.join(', ')}
                                                </span>
                                            )}
                                            {isAsignado && renderEtiquetaAsignador(meta)}
                                        </button>
                                    );
                                })}
                            </div>
                        </details>
                    ))}
                </div>
            )}

            {haySoloLectura && (
                <div className={`space-y-2 ${hayRejilla ? 'mt-6' : ''}`}>
                    <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2">
                        <ShieldAlert className="w-4 h-4 text-amber-500" />
                        Permisos asignados por administración (solo lectura)
                    </h4>
                    {Object.entries(soloLecturaAgrupados).map(([modulo, permisosDeModulo]) => (
                        <details key={`sl-${modulo}`} open className="group theme-element rounded-2xl overflow-hidden border border-amber-500/30">
                            <summary className="p-4 cursor-pointer flex justify-between items-center select-none outline-none">
                                <span className="text-[10px] font-black uppercase tracking-widest theme-text-main italic">
                                    {modulo}
                                </span>
                                <Lock className="w-3.5 h-3.5 text-amber-500" />
                            </summary>
                            <div className="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 border-t theme-border">
                                {permisosDeModulo.map((permiso) => {
                                    const ayuda = descripcionPermiso(permiso.name);
                                    return (
                                        <div
                                            key={`sl-${permiso.id ?? permiso.name}`}
                                            className="flex flex-col items-start gap-1 px-4 py-3 rounded-xl text-[9px] font-black uppercase tracking-widest border border-amber-500/40 bg-amber-500/5 text-amber-700 dark:text-amber-400"
                                        >
                                            <span className="flex justify-between items-center w-full gap-2">
                                                <span>{etiquetaPermiso(permiso.name)}</span>
                                                <Lock className="w-3 h-3 shrink-0 opacity-70" />
                                            </span>
                                            {ayuda && (
                                                <span className="text-[8px] font-medium normal-case tracking-normal opacity-80 leading-snug">
                                                    {ayuda}
                                                </span>
                                            )}
                                            {renderEtiquetaAsignador(permiso.meta)}
                                        </div>
                                    );
                                })}
                            </div>
                        </details>
                    ))}
                </div>
            )}
        </div>
    );
}
