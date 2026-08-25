import React from 'react';
import NotificationBell from '../NotificationBell';
import MensajeriaWidget from '../Mensajeria/MensajeriaWidget';

export default function SidebarUtilities({ collapsed = false }) {
    return (
        <div className="gelia-pro-sidebar__utilities" aria-label="Utilidades">
            <div className="gelia-pro-sidebar__tooltip" data-tip={collapsed ? 'Alertas' : ''}>
                <NotificationBell />
            </div>
            <div className="gelia-pro-sidebar__tooltip" data-tip={collapsed ? 'Mensajería' : ''}>
                <MensajeriaWidget />
            </div>
        </div>
    );
}
