import React, { useMemo, useState } from 'react';
import { Loader2, RotateCcw } from 'lucide-react';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY, THEME_INPUT } from './resguardosStyles';
import {
    resumenImpactoReponerVencido,
    validarFormularioReponerVencido,
} from './reponerVencidoResguardoUtils';
import { formatearFechaOperativa } from './resguardosStyles';

export default function FormularioReponerVencidoResguardo({
    resguardo,
    enviando = false,
    error = null,
    onEnviar,
    onCancelar,
    compacto = false,
}) {
    const [motivo, setMotivo] = useState('');
    const [confirmar, setConfirmar] = useState(false);
    const [erroresLocales, setErroresLocales] = useState({});

    const impacto = useMemo(() => resumenImpactoReponerVencido(resguardo), [resguardo]);

    const solicitarConfirmacion = (e) => {
        e.preventDefault();
        const errores = validarFormularioReponerVencido({ motivo });
        if (Object.keys(errores).length > 0) {
            setErroresLocales(errores);
            return;
        }
        setErroresLocales({});
        setConfirmar(true);
    };

    const confirmarEnvio = async () => {
        setConfirmar(false);
        const resultado = await onEnviar({ motivo });

        if (resultado?.validacion) {
            setErroresLocales(resultado.validacion);
            return;
        }

        if (resultado?.ok) {
            setMotivo('');
            setErroresLocales({});
            onCancelar?.();
        }
    };

    const contenedorClass = compacto ? 'space-y-3' : `${geliaCardClass()} p-4 md:p-5 space-y-4 border border-amber-500/20`;

    return (
        <>
            <form onSubmit={solicitarConfirmacion} className={contenedorClass}>
                {!compacto && (
                    <div className="flex items-start gap-3">
                        <RotateCcw className="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                        <div className="min-w-0 space-y-1">
                            <p className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                                Reponer a bandeja principal
                            </p>
                            <p className="text-xs theme-text-muted m-0">
                                El resguardo seguirá clasificado como vencido, pero volverá a la vista operativa
                                de pendientes por entregar. El plazo de custodia no se reinicia.
                            </p>
                        </div>
                    </div>
                )}

                <div className="rounded-2xl border theme-border p-3 space-y-1">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                        Resguardo {impacto.folio}
                    </p>
                    <p className="text-xs theme-text-main m-0">
                        Recepción física: {formatearFechaOperativa(impacto.recepcionFisicaAt)} (se conserva)
                    </p>
                </div>

                <div className="space-y-2">
                    <label htmlFor={`motivo-reponer-${resguardo.id}`} className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                        Motivo de reposición
                    </label>
                    <textarea
                        id={`motivo-reponer-${resguardo.id}`}
                        value={motivo}
                        onChange={(e) => {
                            setMotivo(e.target.value);
                            setErroresLocales((prev) => ({ ...prev, motivo: undefined }));
                        }}
                        rows={compacto ? 3 : 4}
                        className={`${THEME_INPUT} w-full min-h-[96px] resize-y`}
                        placeholder="Describa por qué gerencia repone este vencido a la bandeja principal"
                        disabled={enviando}
                    />
                    {(erroresLocales.motivo || error) && (
                        <p className="text-sm text-red-600 dark:text-red-300 font-semibold m-0">
                            {erroresLocales.motivo || error}
                        </p>
                    )}
                </div>

                <div className="flex flex-col sm:flex-row gap-2 sm:justify-end">
                    {onCancelar && (
                        <button
                            type="button"
                            onClick={onCancelar}
                            disabled={enviando}
                            className={`${BTN_SECONDARY} min-h-[44px] w-full sm:w-auto`}
                        >
                            Cancelar
                        </button>
                    )}
                    <button
                        type="submit"
                        disabled={enviando}
                        className={`${THEME_BTN_PRIMARY} min-h-[44px] w-full sm:w-auto inline-flex items-center justify-center gap-2`}
                    >
                        {enviando ? <Loader2 className="w-4 h-4 animate-spin" /> : <RotateCcw className="w-4 h-4" />}
                        Reponer vencido
                    </button>
                </div>
            </form>

            <ModalConfirmarAccion
                abierto={confirmar}
                titulo="Confirmar reposición de vencido"
                mensaje="El resguardo volverá a la bandeja principal sin reiniciar el plazo de custodia. La acción quedará registrada en auditoría."
                confirmarTexto="Confirmar reposición"
                cancelarTexto="Revisar"
                onConfirmar={confirmarEnvio}
                onCancelar={() => setConfirmar(false)}
                procesando={enviando}
            />
        </>
    );
}
