import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Loader2, X } from 'lucide-react';
import { THEME_BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import ContenidoDetalleResguardo from './ContenidoDetalleResguardo';
import useDetalleResguardoModal from './useDetalleResguardoModal';
import useToastAlCambiar from '../../../../hooks/useToastAlCambiar';

export default function ModalDetalleResguardo({
    abierto,
    resguardoId,
    resguardoResumen = null,
    onClose,
    onAccionExito,
    modoAuditoria = false,
}) {
    const {
        cargar,
        reiniciar,
        recargar,
        cargando,
        error,
        resguardo,
        timeline,
        catalogos,
        permisos,
        almacenes,
    } = useDetalleResguardoModal(resguardoId);

    useEffect(() => {
        if (!abierto || !resguardoId) {
            return undefined;
        }

        cargar();

        return undefined;
    }, [abierto, resguardoId, cargar]);

    useEffect(() => {
        if (abierto) {
            return undefined;
        }

        reiniciar();

        return undefined;
    }, [abierto, reiniciar]);

    useToastAlCambiar(error, 'error');

    if (!abierto) {
        return null;
    }

    const titulo = resguardo?.snapshot_folio
        || resguardoResumen?.snapshot_folio
        || `Resguardo #${resguardoId}`;

    const cerrar = () => {
        if (cargando) {
            return;
        }
        onClose?.();
    };

    const onExitoAccion = async (resultado) => {
        await recargar();
        onAccionExito?.(resultado);
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-end sm:items-center p-0 sm:p-4 overflow-hidden`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 10)' }}
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} w-full sm:max-w-4xl max-h-[92dvh] min-h-0 flex flex-col rounded-t-3xl sm:rounded-3xl overflow-hidden`}
                onClick={(event) => event.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="titulo-detalle-resguardo"
            >
                <div className="p-5 md:p-6 border-b theme-border flex items-start justify-between gap-3 shrink-0">
                    <div className="min-w-0">
                        <h2 id="titulo-detalle-resguardo" className="text-base font-black uppercase theme-text-main m-0 truncate">
                            {titulo}
                        </h2>
                        <p className="text-xs theme-text-muted m-0 mt-1">
                            Detalle operativo del resguardo en sucursal.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={cerrar}
                        disabled={cargando}
                        className={`${THEME_BTN_SECONDARY} p-2 min-h-[44px] min-w-[44px] shrink-0`}
                        aria-label="Cerrar detalle"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <div className="p-5 md:p-6 overflow-y-auto flex-1 min-h-0 overscroll-contain pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                    {cargando && !resguardo ? (
                        <div className="flex flex-col items-center justify-center gap-3 py-16">
                            <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} aria-hidden />
                            <p className="text-sm font-bold theme-text-muted uppercase tracking-widest m-0">Cargando detalle</p>
                        </div>
                    ) : resguardo ? (
                        <ContenidoDetalleResguardo
                            resguardo={resguardo}
                            timeline={timeline}
                            catalogos={catalogos}
                            permisos={permisos}
                            almacenes={almacenes}
                            onAccionExito={onExitoAccion}
                            modoAuditoria={modoAuditoria}
                        />
                    ) : (
                        <p className="text-sm font-semibold theme-text-muted text-center m-0 py-12">
                            {error || 'No hay información para mostrar.'}
                        </p>
                    )}
                </div>
            </div>
        </div>,
        document.body,
    );
}
