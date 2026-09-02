import React, { useState } from 'react';
import { CheckCircle2, RefreshCw, ShieldAlert } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import FormularioDevolucionResguardo from './FormularioDevolucionResguardo';
import FormularioCorreccionResguardo from './FormularioCorreccionResguardo';
import useDevolucionCorreccionResguardo from './useDevolucionCorreccionResguardo';
import { BTN_SECONDARY } from './resguardosStyles';
import {
    puedeAlgunaExcepcion,
    puedeConfirmarDevolucion,
    puedeCorregirResguardo,
} from './devolucionCorreccionResguardoUtils';

export default function PanelExcepcionesResguardo({
    resguardo,
    timeline = [],
    catalogos = {},
    permisos = {},
}) {
    const [formularioActivo, setFormularioActivo] = useState(null);

    const {
        enviandoDevolucion,
        enviandoCorreccion,
        progreso,
        error,
        conflictoVersion,
        ultimoEvento,
        confirmarDevolucion,
        aplicarCorreccion,
        recargarDetalle,
        setUltimoEvento,
    } = useDevolucionCorreccionResguardo({
        resguardoId: resguardo.id,
        versionInicial: resguardo.version,
    });

    const puedeDevolucion = puedeConfirmarDevolucion(permisos, resguardo);
    const puedeCorreccion = puedeCorregirResguardo(permisos);

    if (!puedeAlgunaExcepcion(permisos, resguardo)) {
        return null;
    }

    const enviando = enviandoDevolucion || enviandoCorreccion;

    return (
        <div className={`${geliaCardClass()} p-5 md:p-6 space-y-4`}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ShieldAlert className="w-5 h-5 text-amber-500 shrink-0" />
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Acciones excepcionales
                    </h2>
                </div>
                {!formularioActivo && (
                    <div className="flex flex-wrap gap-2">
                        {puedeDevolucion && (
                            <button
                                type="button"
                                onClick={() => {
                                    setUltimoEvento(null);
                                    setFormularioActivo('devolucion');
                                }}
                                className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px]`}
                            >
                                Confirmar devolución
                            </button>
                        )}
                        {puedeCorreccion && (
                            <button
                                type="button"
                                onClick={() => {
                                    setUltimoEvento(null);
                                    setFormularioActivo('correccion');
                                }}
                                className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px]`}
                            >
                                Corrección administrativa
                            </button>
                        )}
                    </div>
                )}
            </div>

            <p className="text-sm theme-text-muted m-0">
                Estas acciones son compensatorias y quedan registradas en la bitácora.
                No sustituyen la edición libre de hechos históricos.
            </p>

            {ultimoEvento && (
                <div className="rounded-2xl border border-emerald-500/30 p-4 space-y-2">
                    <div className="flex items-center gap-2">
                        <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-main m-0">
                            {ultimoEvento.tipo === 'devolucion'
                                ? 'Devolución registrada'
                                : 'Corrección aplicada'}
                        </p>
                    </div>
                    <p className="text-sm theme-text-main m-0">
                        El evento compensatorio quedó registrado en la línea de tiempo.
                        {ultimoEvento.resguardo?.estado_etiqueta
                            ? ` Estado actual: ${ultimoEvento.resguardo.estado_etiqueta}.`
                            : ''}
                    </p>
                </div>
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

            {error && !formularioActivo && (
                <p className="text-sm text-red-600 dark:text-red-300 font-semibold m-0">{error}</p>
            )}

            {formularioActivo === 'devolucion' && puedeDevolucion && (
                <FormularioDevolucionResguardo
                    resguardo={resguardo}
                    catalogos={catalogos}
                    enviando={enviandoDevolucion}
                    progreso={progreso}
                    error={error}
                    onEnviar={confirmarDevolucion}
                    onCancelar={() => setFormularioActivo(null)}
                />
            )}

            {formularioActivo === 'correccion' && puedeCorreccion && (
                <FormularioCorreccionResguardo
                    resguardo={resguardo}
                    timeline={timeline}
                    catalogos={catalogos}
                    enviando={enviandoCorreccion}
                    progreso={progreso}
                    error={error}
                    onEnviar={aplicarCorreccion}
                    onCancelar={() => setFormularioActivo(null)}
                />
            )}
        </div>
    );
}
