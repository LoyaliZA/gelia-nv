import React from 'react';
import NotificationBell from '../NotificationBell';
import MensajeriaWidget from '../Mensajeria/MensajeriaWidget';

export default function SidebarUtilities({ collapsed = false }) {
    return (
        <div className="gelia-pro-sidebar__utilities" aria-label="Utilidades">
            <div className="gelia-pro-sidebar__tooltip gelia-pro-sidebar__utility-slot" data-tip={collapsed ? 'Alertas' : ''}>
                <NotificationBell iconButtonClassName="gelia-pro-sidebar__utility-btn" />
            </div>
            <div className="gelia-pro-sidebar__tooltip gelia-pro-sidebar__utility-slot" data-tip={collapsed ? 'Mensajería' : ''}>
                <MensajeriaWidget iconButtonClassName="gelia-pro-sidebar__utility-btn" />
            </div>
        </div>
    );
}
