import React, { useId } from 'react';
import { createPortal } from 'react-dom';
import { MapPin, Package, Store, Truck, X } from 'lucide-react';
import usePedidoDialog from './usePedidoDialog';
import {
    GELIA_BTN_OUTLINE, GELIA_ICON_BOX, GELIA_MODAL_TITLE,
    THEME_MODAL_OVERLAY, THEME_MODAL_SHELL,
} from '../../../utils/geliaTheme';

const MODALIDADES = [
    { valor: 'tienda', titulo: 'Tienda', detalle: 'Entrega o recolección en sucursal.', Icono: Store },
    { valor: 'municipal', titulo: 'Envío municipal', detalle: 'Entrega en terminal de transporte del municipio destino.', Icono: MapPin },
    { valor: 'nacional', titulo: 'Envío nacional', detalle: 'Envío a otra ciudad.', Icono: Package },
];

export default function ModalSeleccionarModalidad({ abierto, onClose, onSeleccionar }) {
    const tituloId = useId();
    const descripcionId = useId();
    const dialog = usePedidoDialog({ abierto, onClose });
    if (!abierto) return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-center py-4`} onClick={onClose}>
            <section
                {...dialog}
                aria-labelledby={tituloId}
                aria-describedby={descripcionId}
                className={`${THEME_MODAL_SHELL} w-full max-w-3xl`}
                onClick={(evento) => evento.stopPropagation()}
            >
                <header className="flex shrink-0 items-start gap-4 p-5 sm:p-6 border-b theme-border">
                    <span className={`${GELIA_ICON_BOX} shrink-0`} aria-hidden="true"><Truck size={24} /></span>
                    <div className="min-w-0 flex-1">
                        <p className="text-xs font-bold uppercase tracking-wide theme-text-muted m-0 mb-1">Gestión de pedidos</p>
                        <h2 id={tituloId} className={`${GELIA_MODAL_TITLE} m-0`}>Seleccionar modalidad</h2>
                        <p id={descripcionId} className="text-sm theme-text-muted m-0 mt-2">Elige la modalidad del pedido para continuar.</p>
                    </div>
                    <button type="button" className={`${GELIA_BTN_OUTLINE} shrink-0 min-w-[44px] min-h-[44px] p-2`} aria-label="Cerrar selección de modalidad" onClick={onClose}>
                        <X size={18} aria-hidden="true" />
                    </button>
                </header>
                <div className="gelia-modal-body grid grid-cols-1 sm:grid-cols-3 gap-4 p-5 sm:p-6">
                    {MODALIDADES.map(({ valor, titulo, detalle, Icono }) => (
                        <button
                            key={valor}
                            type="button"
                            className="flex items-center sm:flex-col sm:justify-start gap-4 sm:gap-0 min-w-0 border theme-border theme-surface rounded-2xl p-4 sm:px-4 sm:py-6 text-left sm:text-center cursor-pointer hover:bg-black/5 dark:hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-offset-2"
                            onClick={() => onSeleccionar(valor)}
                        >
                            <span className={`${GELIA_ICON_BOX} sm:mb-4 text-[var(--color-primario)]`} aria-hidden="true"><Icono size={30} /></span>
                            <span className="min-w-0">
                                <span className="block text-lg font-bold theme-text-main">{titulo}</span>
                                <span className="block text-sm font-normal theme-text-muted leading-relaxed mt-2">{detalle}</span>
                            </span>
                        </button>
                    ))}
                </div>
            </section>
        </div>,
        document.body,
    );
}
