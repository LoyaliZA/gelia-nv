import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Loader2, PackageCheck, X } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import FormularioCustodiaBultos from './FormularioCustodiaBultos';
import useConfirmacionCustodia from './useConfirmacionCustodia';
import useFormularioCustodiaResguardo from './useFormularioCustodiaResguardo';
import { BTN_SECONDARY, badgeEstadoResguardo } from './resguardosStyles';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';
import { abrirDetalleResguardoModal } from './resguardoDetalleModalBridge';

export default function ModalCustodiaResguardo({
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
        almacenes,
        catalogos,
        admiteConfirmacion,
        motivoNoConfirmacion,
    } = useFormularioCustodiaResguardo(resguardoId);

    const {
        enviar,
        enviando,
        error,
        exito,
        reiniciarFlujo,
    } = useConfirmacionCustodia({
        resguardoId,
        versionInicial: resguardo?.version,
        modoModal: true,
        onExitoModal: (resultado) => {
            onExito?.(resultado);
            if (resultado?.fase === 'exito') {
                onClose?.();
            }
        },
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
                            <PackageCheck className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            <h2 className="text-base sm:text-lg font-black italic uppercase theme-text-main m-0 truncate">
                                Confirmar custodia
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
                                Cargando revisión
                            </p>
                        </div>
                    ) : errorCarga ? (
                        <div className={`${geliaCardClass()} p-5 space-y-3 text-center`}>
                            <p className="text-sm theme-text-muted m-0">No se pudo cargar el formulario de custodia.</p>
                            <button type="button" onClick={cargar} className={`${BTN_SECONDARY} w-full min-h-[44px]`}>
                                Reintentar
                            </button>
                        </div>
                    ) : exito ? (
                        <ResultadoExito onDetalle={() => abrirDetalleResguardoModal(resguardoId)} />
                    ) : !admiteConfirmacion ? (
                        <EstadoNoDisponible
                            motivo={motivoNoConfirmacion}
                            onDetalle={() => abrirDetalleResguardoModal(resguardoId)}
                        />
                    ) : resguardo ? (
                        almacenes.length === 0 ? (
                            <div className={`${geliaCardClass()} p-5`}>
                                <p className="text-sm theme-text-main m-0">
                                    No hay almacenes activos en esta sucursal para registrar custodia.
                                </p>
                            </div>
                        ) : (
                            <FormularioCustodiaBultos
                                resguardo={resguardo}
                                almacenes={almacenes}
                                catalogos={catalogos}
                                enviando={enviando}
                                error={error}
                                onEnviar={enviar}
                            />
                        )
                    ) : null}
                </div>
            </div>
        </div>,
        document.body,
    );
}

function ResultadoExito({ onDetalle }) {
    return (
        <div className={`${geliaCardClass()} p-8 text-center space-y-4`}>
            <CheckCircle2 className="w-12 h-12 mx-auto text-emerald-500" />
            <div className="space-y-2">
                <h3 className="text-lg font-black uppercase theme-text-main m-0">Custodia registrada</h3>
                <p className="text-sm theme-text-muted m-0">
                    La revisión quedó registrada. El resguardo pasará a custodia y podrá entregarse cuando corresponda.
                </p>
            </div>
            <button type="button" onClick={onDetalle} className={`${THEME_BTN_PRIMARY} min-h-[48px] px-6`}>
                Ver detalle actualizado
            </button>
        </div>
    );
}

function EstadoNoDisponible({ motivo, onDetalle }) {
    return (
        <div className={`${geliaCardClass()} p-5 space-y-3 border border-amber-500/30`}>
            <div className="flex items-start gap-3">
                <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0" />
                <div className="space-y-2">
                    <p className="text-sm font-black theme-text-main m-0">
                        {motivo || 'Este resguardo no admite confirmación de custodia en su estado actual.'}
                    </p>
                    <button type="button" onClick={onDetalle} className={BTN_SECONDARY}>
                        Ver detalle
                    </button>
                </div>
            </div>
        </div>
    );
}

export function AccionConfirmarCustodiaResguardo({
    resguardo,
    className = '',
    onExito,
}) {
    const [modalAbierto, setModalAbierto] = React.useState(false);

    return (
        <>
            <button
                type="button"
                onClick={() => setModalAbierto(true)}
                className={className}
            >
                <PackageCheck className="w-4 h-4 shrink-0" aria-hidden />
                <span>Confirmar custodia</span>
            </button>
            <ModalCustodiaResguardo
                abierto={modalAbierto}
                resguardoId={resguardo.id}
                resguardoResumen={resguardo}
                onClose={() => setModalAbierto(false)}
                onExito={(resultado) => {
                    if (resultado?.fase === 'exito') {
                        onExito?.(resultado);
                        return;
                    }
                    setModalAbierto(false);
                    onExito?.(resultado);
                }}
            />
        </>
    );
}
