import React from 'react';
import { Settings } from 'lucide-react';
import NotificationBell from '../NotificationBell';
import MensajeriaWidget from '../Mensajeria/MensajeriaWidget';
import useIconoHover from '@/hooks/useIconoHover';
import { abrirAjustes } from '../Ajustes/ajustesEvents';

export default function SidebarUtilities({ collapsed = false }) {
    const ajustes = useIconoHover(Settings, { size: 20, className: 'gelia-pro-sidebar__utility-icon' });

    return (
        <div className="gelia-pro-sidebar__utilities" role="toolbar" aria-label="Utilidades">
            <div className="gelia-pro-sidebar__utility-slot" data-tip={collapsed ? 'Alertas' : ''}>
                <NotificationBell iconButtonClassName="gelia-pro-sidebar__utility-btn" />
            </div>
            <div className="gelia-pro-sidebar__utility-slot" data-tip={collapsed ? 'Mensajería' : ''}>
                <MensajeriaWidget iconButtonClassName="gelia-pro-sidebar__utility-btn" />
            </div>
            <div className="gelia-pro-sidebar__utility-slot" data-tip={collapsed ? 'Ajustes' : ''}>
                <button
                    type="button"
                    className="gelia-pro-sidebar__utility-btn"
                    aria-label="Ajustes"
                    aria-haspopup="dialog"
                    title={collapsed ? undefined : 'Ajustes'}
                    onClick={() => abrirAjustes()}
                    {...ajustes.triggerProps}
                >
                    {ajustes.nodo}
                </button>
            </div>
        </div>
    );
}
