import React, { useState } from 'react';
import { AlertTriangle, Plus, RefreshCw } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import FormularioIncidenciaResguardo from './FormularioIncidenciaResguardo';
import DetalleIncidenciaResguardo from './DetalleIncidenciaResguardo';
import useIncidenciasResguardo from './useIncidenciasResguardo';
import { BTN_SECONDARY } from './resguardosStyles';
import {
    incidenciasOrdenadasCronologicamente,
    puedeRegistrarAlgunaIncidencia,
} from './incidenciasResguardoUtils';

export default function PanelIncidenciasResguardo({
    resguardo,
    timeline: timelineInicial = [],
    catalogos = {},
    permisos = {},
    almacenes = [],
}) {
    const [mostrarFormulario, setMostrarFormulario] = useState(false);

    const {
        incidencias,
        timeline,
        enviandoRegistro,
        enviandoResolucion,
        progreso,
        error,
        errorResolucionId,
        conflictoVersion,
        registrar,
        resolver,
        recargarDetalle,
    } = useIncidenciasResguardo({
        resguardoId: resguardo.id,
        versionInicial: resguardo.version,
        incidenciasIniciales: resguardo.incidencias || [],
        timelineInicial,
    });

    const puedeRegistrar = puedeRegistrarAlgunaIncidencia(permisos, resguardo);
    const lista = incidenciasOrdenadasCronologicamente(incidencias);
    const sinIncidencias = lista.length === 0;

    return (
        <div className="space-y-4">
            <div className={`${geliaCardClass()} p-5 md:p-6 space-y-4`}>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <AlertTriangle className="w-5 h-5 text-purple-500 shrink-0" />
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                            Incidencias
                        </h2>
                    </div>
                    {puedeRegistrar && !mostrarFormulario && (
                        <button
                            type="button"
                            onClick={() => setMostrarFormulario(true)}
                            className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px]`}
                        >
                            <Plus className="w-4 h-4" />
                            Reportar incidencia
                        </button>
                    )}
                </div>

                {mostrarFormulario && puedeRegistrar && (
                    <FormularioIncidenciaResguardo
                        catalogos={catalogos}
                        permisos={permisos}
                        almacenes={almacenes}
                        enviando={enviandoRegistro}
                        progreso={progreso}
                        error={error}
                        onEnviar={registrar}
                        onCancelar={() => {
                            setMostrarFormulario(false);
                        }}
                    />
                )}

                {error && !mostrarFormulario && (
                    <p className="text-sm text-red-600 dark:text-red-300 font-semibold m-0">{error}</p>
                )}

                {conflictoVersion && (
                    <div className="rounded-2xl border border-amber-500/30 p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <p className="text-sm theme-text-main m-0 flex-1">
                            Otro usuario modificó este resguardo. Actualiza los datos antes de continuar.
                        </p>
                        <button
                            type="button"
                            onClick={recargarDetalle}
                            className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px] shrink-0`}
                        >
                            <RefreshCw className="w-4 h-4" />
                            Actualizar
                        </button>
                    </div>
                )}

                {sinIncidencias && !mostrarFormulario && (
                    <p className="text-sm theme-text-muted font-bold m-0">
                        {puedeRegistrar
                            ? 'No hay incidencias registradas. Usa «Reportar incidencia» si detectas un problema.'
                            : 'No hay incidencias registradas en este resguardo.'}
                    </p>
                )}

                {lista.length > 0 && (
                    <div className="space-y-3">
                        {lista.map((incidencia) => (
                            <DetalleIncidenciaResguardo
                                key={incidencia.id}
                                incidencia={incidencia}
                                catalogos={catalogos}
                                permisos={permisos}
                                enviandoResolucion={enviandoResolucion}
                                errorResolucion={errorResolucionId === incidencia.id ? error : null}
                                onResolver={resolver}
                            />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
