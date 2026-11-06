import { Menu } from 'lucide-react';
import BotonIconoMensajeria from './BotonIconoMensajeria';

/** Abre o cierra el menú de navegación global desde la mensajería en móvil. */
export default function MensajeriaNavMenuButton({ className = '', plano = false }) {
    return (
        <BotonIconoMensajeria
            icon={Menu}
            label="Abrir menú de navegación"
            onClick={() => window.dispatchEvent(new CustomEvent('gelia-sidebar-toggle-menu'))}
            plano={plano}
            className={`${plano ? '' : 'rounded-full theme-element theme-text-main border theme-border'} md:hidden ${className}`}
            size={18}
        />
    );
}
