import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Loader2, Truck, X } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import FormularioEntregaResguardo from './FormularioEntregaResguardo';
import useEntregaResguardo from './useEntregaResguardo';
import useFormularioEntregaResguardo from './useFormularioEntregaResguardo';
import { BTN_ACCION_RECEPCION_TARJETA, BTN_SECONDARY, badgeEstadoResguardo } from './resguardosStyles';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';
import { abrirDetalleResguardoModal } from './resguardoDetalleModalBridge';

export default function ModalEntregaResguardo({
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
        puedeEntregar,
        motivoNoEntregable,
    } = useFormularioEntregaResguardo(resguardoId);

    const {
        enviar,
        enviando,
        progreso,
        error,
        exito,
        resguardoResultado,
        irADetalle,
        irABandeja,
        recargarFormulario,
    } = useEntregaResguardo({
        resguardoId,
        versionInicial: resguardo?.version,
        metodoValidacion: catalogos.metodo_validacion || 'firma',
        modoModal: true,
        onExitoModal: (resultado) => {
            if (resultado?.fase === 'exito') {
                onExito?.(resultado);
                return;
            }
            onExito?.(resultado);
            onClose?.();
        },
        onRecargarFormulario: cargar,
    });

    useEffect(() => {
        if (!abierto || !resguardoId) return undefined;

        cargar();

        return () => {
            reiniciar();
        };
    }, [abierto, resguardoId, cargar, reiniciar]);

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
        >
            <div
                className={`${THEME_MODAL_SHELL} w-full sm:max-w-[720px] max-h-[92dvh] flex flex-col rounded-t-3xl sm:rounded-3xl overflow-hidden`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-4 md:p-5 border-b theme-border flex items-start justify-between gap-3 shrink-0">
                    <div className="min-w-0 space-y-2">
                        <div className="flex items-center gap-2 min-w-0">
                            <Truck className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            <h2 className="text-base sm:text-lg font-black italic uppercase theme-text-main m-0 truncate">
                                Entrega física
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
                                Cargando entrega
                            </p>
                        </div>
                    ) : errorCarga ? (
                        <div className={`${geliaCardClass()} p-5 space-y-3 text-center`}>
                            <p className="text-sm theme-text-muted m-0">No se pudo cargar el formulario de entrega.</p>
                            <button type="button" onClick={cargar} className={`${BTN_SECONDARY} w-full min-h-[44px]`}>
                                Reintentar
                            </button>
                        </div>
                    ) : exito ? (
                        <ResultadoExito
                            onDetalle={irADetalle}
                            onBandeja={irABandeja}
                            parcial={resguardoResultado?.estado === 'en_custodia' || resguardoResultado?.estado === 'pendiente_recepcion'}
                        />
                    ) : !puedeEntregar ? (
                        <EstadoNoDisponible
                            resguardo={resguardo}
                            catalogos={catalogos}
                            motivo={motivoNoEntregable}
                            onDetalle={irADetalle}
                        />
                    ) : resguardo ? (
                        <FormularioEntregaResguardo
                            resguardo={resguardo}
                            catalogos={catalogos}
                            enviando={enviando}
                            progreso={progreso}
                            error={error}
                            onEnviar={enviar}
                            onCancelar={cerrar}
                        />
                    ) : null}

                    {error && error.includes('modificó este resguardo') && puedeEntregar && !exito && resguardo && (
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

function ResultadoExito({ onDetalle, onBandeja, parcial = false }) {
    return (
        <div className={`${geliaCardClass()} p-8 text-center space-y-4`}>
            <CheckCircle2 className="w-12 h-12 mx-auto text-[var(--color-exito)]" />
            <div className="space-y-2">
                <h3 className="text-lg font-black uppercase theme-text-main m-0">
                    {parcial ? 'Entrega parcial registrada' : 'Entrega registrada'}
                </h3>
                <p className="text-sm theme-text-muted m-0">
                    {parcial
                        ? 'Los bultos restantes permanecen en custodia. El pedido no pasa a Entregado hasta completar el resto.'
                        : 'El resguardo quedó en estado Entregado.'}
                </p>
            </div>
            <div className="flex flex-col sm:flex-row gap-2 justify-center">
                <button type="button" onClick={onDetalle} className={`${THEME_BTN_PRIMARY} min-h-[48px] px-6`}>
                    Ver detalle actualizado
                </button>
                <button type="button" onClick={onBandeja} className={`${BTN_SECONDARY} min-h-[48px] px-6`}>
                    Ir a en custodia
                </button>
            </div>
        </div>
    );
}

function EstadoNoDisponible({ resguardo, catalogos, motivo, onDetalle }) {
    const etiqueta = resguardo?.estado_etiqueta || catalogos.estados?.[resguardo?.estado] || resguardo?.estado;
    const yaEntregado = resguardo?.estado === 'entregado';

    return (
        <div className={`${geliaCardClass()} p-5 space-y-3 border border-[color-mix(in_srgb,var(--color-aviso)_35%,transparent)]`}>
            <div className="flex items-start gap-3">
                <AlertTriangle className="w-5 h-5 text-[var(--color-aviso)] shrink-0" />
                <div className="space-y-2">
                    <p className="text-sm font-black theme-text-main m-0">
                        {yaEntregado
                            ? 'Este resguardo ya fue entregado.'
                            : motivo || `No se puede entregar en estado «${etiqueta}».`}
                    </p>
                    <button type="button" onClick={onDetalle} className={BTN_SECONDARY}>
                        Ver detalle
                    </button>
                </div>
            </div>
        </div>
    );
}

export function AccionEntregaResguardo({
    resguardo,
    className = '',
    onExito,
    variant = 'primary',
}) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const btnClass = variant === 'pie'
        ? BTN_ACCION_RECEPCION_TARJETA
        : variant === 'primary'
            ? `${THEME_BTN_PRIMARY} w-full inline-flex items-center justify-center gap-2 min-h-[44px] text-[10px] font-black uppercase tracking-widest`
            : `${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px] text-[10px] font-black uppercase tracking-widest`;

    return (
        <>
            <button
                type="button"
                onClick={() => setModalAbierto(true)}
                className={`${btnClass} ${className}`}
            >
                <Truck className="w-4 h-4" /> Entregar
            </button>
            <ModalEntregaResguardo
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

export function irADetalleResguardo(resguardoId) {
    abrirDetalleResguardoModal(resguardoId);
}

export function irABandejaCustodiaResguardo() {
    router.visit(route('punto_venta.resguardos.index', { bandeja: 'en_custodia' }));
}
