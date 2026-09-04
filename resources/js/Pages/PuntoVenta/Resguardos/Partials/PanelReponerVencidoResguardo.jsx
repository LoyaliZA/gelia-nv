import React, { useState } from 'react';
import { CheckCircle2, RefreshCw, RotateCcw } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import FormularioReponerVencidoResguardo from './FormularioReponerVencidoResguardo';
import useReponerVencidoResguardo from './useReponerVencidoResguardo';
import { BTN_SECONDARY } from './resguardosStyles';
import { puedeReponerVencido } from './reponerVencidoResguardoUtils';

export default function PanelReponerVencidoResguardo({ resguardo, permisos = {} }) {
    const [formularioActivo, setFormularioActivo] = useState(false);

    const {
        enviando,
        error,
        conflictoVersion,
        ultimoEvento,
        reponerVencido,
        recargarDetalle,
        setUltimoEvento,
    } = useReponerVencidoResguardo({
        resguardoId: resguardo.id,
        versionInicial: resguardo.version,
    });

    if (!puedeReponerVencido(permisos, resguardo)) {
        return null;
    }

    return (
        <div className={`${geliaCardClass()} p-5 md:p-6 space-y-4`}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <RotateCcw className="w-5 h-5 text-amber-500 shrink-0" />
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Custodia vencida
                    </h2>
                </div>
                {!formularioActivo && !ultimoEvento && (
                    <button
                        type="button"
                        onClick={() => {
                            setUltimoEvento(null);
                            setFormularioActivo(true);
                        }}
                        className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px]`}
                    >
                        Reponer a bandeja principal
                    </button>
                )}
            </div>

            <p className="text-sm theme-text-muted m-0">
                Gerencia puede devolver este resguardo a la vista operativa de pendientes por entregar.
                El plazo de 15 días hábiles no se reinicia.
            </p>

            {ultimoEvento && (
                <div className="rounded-2xl border border-emerald-500/30 p-4 space-y-2">
                    <div className="flex items-center gap-2">
                        <CheckCircle2 className="w-4 h-4 text-emerald-500 shrink-0" />
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-main m-0">
                            Vencido repuesto
                        </p>
                    </div>
                    <p className="text-sm theme-text-main m-0">
                        El resguardo volvió a la bandeja principal. La clasificación vencido se mantiene
                        y la recepción física original no cambió.
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

            {error && !formularioActivo && !ultimoEvento && (
                <p className="text-sm text-red-600 dark:text-red-300 font-semibold m-0">{error}</p>
            )}

            {formularioActivo && (
                <FormularioReponerVencidoResguardo
                    resguardo={resguardo}
                    enviando={enviando}
                    error={error}
                    onEnviar={reponerVencido}
                    onCancelar={() => setFormularioActivo(false)}
                />
            )}
        </div>
    );
}
