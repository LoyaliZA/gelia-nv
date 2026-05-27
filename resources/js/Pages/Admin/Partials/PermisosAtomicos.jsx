import React, { useMemo } from 'react';
import { Key, ShieldCheck, Check, ChevronRight, Lock } from 'lucide-react';
import {
    permisoDePlantilla,
    plantillasDePermiso,
    filtrarPermisosAsignables,
    usuarioPuedeAsignarPermiso,
} from '../../../utils/permisos';

export default function PermisosAtomicos({
    data,
    setData,
    roles,
    todosLosPermisos,
    permisosUsuario = [],
    esSuperAdmin = false,
    procedencia = {},
    onPlantillaPorPermisoChange,
}) {
    const permisosVisibles = useMemo(
        () => filtrarPermisosAsignables(todosLosPermisos, permisosUsuario, esSuperAdmin),
        [todosLosPermisos, permisosUsuario, esSuperAdmin]
    );

    const plantillasActivas = data.plantillas_activas || [];

    const togglePermisoIndividual = (permisoName) => {
        if (!usuarioPuedeAsignarPermiso(permisoName, permisosUsuario, esSuperAdmin)) return;

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

    const permisosAgrupados = useMemo(() => {
        return (permisosVisibles || []).reduce((acc, p) => {
            const modulo = p?.name?.split('.')[0] || 'Otros';
            if (!acc[modulo]) acc[modulo] = [];
            acc[modulo].push(p);
            return acc;
        }, {});
    }, [permisosVisibles]);

    if (!permisosVisibles || permisosVisibles.length === 0) return null;

    return (
        <div>
            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4 flex items-center gap-2 border-b theme-border pb-2">
                <Key className="w-4 h-4 text-red-500" /> Permisos Atómicos
            </h3>
            <p className="text-[10px] theme-text-muted mb-4 font-bold tracking-widest">
                INDICADORES: <span className="text-blue-500 mx-1">AZUL</span> sugerido de plantilla.{' '}
                <span className="text-orange-500 mx-1">NARANJA</span> asignado explícitamente.
                {!esSuperAdmin && (
                    <span className="block mt-1 text-amber-600 dark:text-amber-400">
                        Solo puedes asignar permisos que tú posees.
                    </span>
                )}
            </p>

            <div className="space-y-2">
                {Object.entries(permisosAgrupados).map(([modulo, permisosDeModulo]) => (
                    <details key={modulo} className="group theme-element rounded-2xl overflow-hidden border theme-border">
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
                                const isAsignado = (data.permisos_individuales || []).includes(permiso.name);
                                const sugeridoDe = plantillasDePermiso(permiso.name, plantillasActivas, roles);
                                const meta = procedencia[permiso.name];
                                const puedeAsignar = usuarioPuedeAsignarPermiso(permiso.name, permisosUsuario, esSuperAdmin);

                                return (
                                    <button
                                        key={permiso.id}
                                        type="button"
                                        disabled={!puedeAsignar}
                                        onClick={() => togglePermisoIndividual(permiso.name)}
                                        className={`flex flex-col items-start gap-1 px-4 py-3 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-all ${
                                            isAsignado
                                                ? isDePlantilla && !meta
                                                    ? 'border-blue-500/40 bg-blue-500/10 text-blue-600 dark:text-blue-400'
                                                    : 'border-orange-500 bg-orange-500/10 text-orange-600'
                                                : isDePlantilla
                                                  ? 'border-blue-500/20 bg-blue-500/5 text-blue-500/70 hover:border-blue-500/40'
                                                  : puedeAsignar
                                                    ? 'theme-border theme-text-muted hover:border-gray-400'
                                                    : 'theme-border theme-text-muted opacity-40 cursor-not-allowed'
                                        }`}
                                    >
                                        <span className="flex justify-between items-center w-full gap-2">
                                            <span>{permiso.name.split('.')[1]?.replace(/_/g, ' ') || permiso.name}</span>
                                            {isAsignado ? (
                                                <Check className="w-3 h-3 shrink-0" />
                                            ) : !puedeAsignar ? (
                                                <Lock className="w-3 h-3 shrink-0 opacity-50" />
                                            ) : null}
                                        </span>
                                        {isDePlantilla && sugeridoDe.length > 0 && !meta && (
                                            <span className="text-[8px] font-bold normal-case tracking-wide opacity-80 italic">
                                                plantilla: {sugeridoDe.join(', ')}
                                            </span>
                                        )}
                                        {meta?.asignado_por?.nombre && (
                                            <span className="text-[8px] font-bold normal-case tracking-wide opacity-80 italic">
                                                heredado_de: {meta.asignado_por.nombre}
                                            </span>
                                        )}
                                        {meta?.plantilla_origen && (
                                            <span className="text-[8px] font-bold normal-case tracking-wide opacity-70 italic text-purple-500">
                                                plantilla: {meta.plantilla_origen}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </details>
                ))}
            </div>
        </div>
    );
}
