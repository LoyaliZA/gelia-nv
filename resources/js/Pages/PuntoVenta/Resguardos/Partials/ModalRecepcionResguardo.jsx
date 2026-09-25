import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Loader2, Package, X } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import FormularioConfirmarRecepcionResguardo from './FormularioConfirmarRecepcionResguardo';
import useRecepcionFisica from './useRecepcionFisica';
import useFormularioRecepcionResguardo from './useFormularioRecepcionResguardo';
import { BTN_SECONDARY, badgeEstadoResguardo } from './resguardosStyles';
import {
    mensajeEstadoNoRecepcion,
    resguardoAdmiteRecepcion,
} from './recepcionFisicaUtils';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';

export default function ModalRecepcionResguardo({
    abierto,
    resguardoId,
    resguardoResumen = null,
    onClose,
    onExito,
}) {
    const {
        cargar,
        reiniciar,
        cargando,
        error: errorCarga,
        resguardo,
        catalogos,
        admiteRecepcion,
        motivoNoRecepcion,
    } = useFormularioRecepcionResguardo(resguardoId);

    const {
        enviar,
        enviando,
        progreso,
        error,
        exito,
        irADetalle,
        irABandeja,
        recargarFormulario,
        reiniciarFlujo,
    } = useRecepcionFisica({
        resguardoId,
        versionInicial: resguardo?.version,
        modoModal: true,
        onExitoModal: (resultado) => {
            onExito?.(resultado);
            if (resultado?.fase === 'exito') {
                onClose?.();
            }
        },
        onRecargarFormulario: cargar,
    });

    useEffect(() => {
        if (!abierto || !resguardoId) return undefined;

        reiniciarFlujo?.();
        cargar();

        return () => {
            reiniciar();
        };
    }, [abierto, resguardoId, cargar, reiniciar, reiniciarFlujo]);

    useToastAlCambiar(errorCarga, 'error');

    if (!abierto) return null;

    const titulo = resguardo?.snapshot_folio
        || resguardoResumen?.snapshot_folio
        || `Resguardo #${resguardoId}`;
    const admiteRecepcionFormulario = resguardoAdmiteRecepcion(resguardo, admiteRecepcion);

    const cerrar = () => {
        if (enviando) return;
        onClose?.();
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-end sm:items-center p-0 sm:p-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 10)' }}
            onClick={cerrar}
            data-recepcion-movil-root
        >
            <div
                className={`${THEME_MODAL_SHELL} w-full sm:max-w-[720px] max-h-[92dvh] flex flex-col rounded-t-3xl sm:rounded-3xl overflow-hidden`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-4 md:p-5 border-b theme-border flex items-start justify-between gap-3 shrink-0">
                    <div className="min-w-0 space-y-2">
                        <div className="flex items-center gap-2 min-w-0">
                            <Package className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            <h2 className="text-base sm:text-lg font-black italic uppercase theme-text-main m-0 truncate">
                                Confirmar recepción
                            </h2>
                        </div>
                        <p className="text-sm font-bold theme-text-muted m-0 truncate">{titulo}</p>
                        {resguardo?.estado && (
                            <span className={`inline-flex px-3 py-1.5 rounded-xl text-[10px] font-black uppercase ${badgeEstadoResguardo(resguardo.estado)}`}>
                                {resguardo.estado_etiqueta || catalogos.estados?.[resguardo.estado]}
                            </span>
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={cerrar}
                        disabled={enviando}
                        className={`${BTN_SECONDARY} p-2 min-h-[44px] min-w-[44px] shrink-0`}
                        aria-label="Cerrar"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <div className="p-4 md:p-5 space-y-4 overflow-y-auto flex-1 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    {cargando && !resguardo ? (
                        <div className={`${geliaCardClass()} p-10 flex flex-col items-center gap-3`}>
                            <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} />
                            <p className="text-sm theme-text-muted font-bold uppercase tracking-widest m-0">
                                Cargando recepción
                            </p>
                        </div>
                    ) : errorCarga ? (
                        <div className={`${geliaCardClass()} p-5 space-y-3 text-center`}>
                            <p className="text-sm theme-text-muted m-0">No se pudo cargar el formulario de recepción.</p>
                            <button type="button" onClick={cargar} className={`${BTN_SECONDARY} w-full min-h-[44px]`}>
                                Reintentar
                            </button>
                        </div>
                    ) : exito ? (
                        <ResultadoExito onDetalle={irADetalle} onBandeja={irABandeja} />
                    ) : !admiteRecepcionFormulario ? (
                        <EstadoNoDisponible
                            resguardo={resguardo}
                            catalogos={catalogos}
                            motivo={motivoNoRecepcion}
                            onDetalle={irADetalle}
                        />
                    ) : resguardo ? (
                        <FormularioConfirmarRecepcionResguardo
                            resguardo={resguardo}
                            enviando={enviando}
                            progreso={progreso}
                            error={error}
                            onEnviar={enviar}
                        />
                    ) : null}

                    {error && error.includes('modificó este resguardo') && admiteRecepcionFormulario && !exito && (
                        <button
                            type="button"
                            onClick={recargarFormulario}
                            className={`${BTN_SECONDARY} w-full min-h-[44px]`}
                        >
                            Actualizar datos y reintentar
                        </button>
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}

function ResultadoExito({ onDetalle, onBandeja }) {
    return (
        <div className={`${geliaCardClass()} p-8 text-center space-y-4`}>
            <CheckCircle2 className="w-12 h-12 mx-auto text-[var(--color-exito)]" />
            <div className="space-y-2">
                <h3 className="text-lg font-black uppercase theme-text-main m-0">Recepción confirmada</h3>
                <p className="text-sm theme-text-muted m-0">
                    El resguardo quedó en estado Recibido. Pásalo a recepción cuando esté listo para revisión.
                </p>
            </div>
            <div className="flex flex-col sm:flex-row gap-2 justify-center">
                <button type="button" onClick={onDetalle} className={`${THEME_BTN_PRIMARY} min-h-[48px] px-6`}>
                    Ver detalle actualizado
                </button>
                <button type="button" onClick={onBandeja} className={`${BTN_SECONDARY} min-h-[48px] px-6`}>
                    Volver a bandeja
                </button>
            </div>
        </div>
    );
}

function EstadoNoDisponible({ resguardo, catalogos, motivo, onDetalle }) {
    const { titulo, detalle } = mensajeEstadoNoRecepcion({
        motivo,
        resguardo,
        catalogos,
    });

    return (
        <div className={`${geliaCardClass()} p-5 space-y-3 border border-[color-mix(in_srgb,var(--color-aviso)_35%,transparent)]`}>
            <div className="flex items-start gap-3">
                <AlertTriangle className="w-5 h-5 text-[var(--color-aviso)] shrink-0" />
                <div className="space-y-2">
                    <p className="text-sm font-black theme-text-main m-0">{titulo}</p>
                    {detalle && (
                        <p className="text-sm theme-text-muted m-0">{detalle}</p>
                    )}
                    <button type="button" onClick={onDetalle} className={BTN_SECONDARY}>
                        Ver detalle
                    </button>
                </div>
            </div>
        </div>
    );
}

export { default as AccionRecepcionResguardo } from './BotonConfirmarRecepcionResguardo';
export { BotonPasarARecepcionResguardo as AccionPasarARecepcionResguardo } from './BotonConfirmarRecepcionResguardo';
