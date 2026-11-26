import React, { memo } from 'react';
import { Link } from '@inertiajs/react';
import useIconoHover from '@/hooks/useIconoHover';

function SidebarLink({
    href,
    label,
    icon: Icon,
    active = false,
    onClick,
    badge,
    collapsed = false,
    depth = 1,
}) {
    const icono = useIconoHover(Icon, { size: 18, className: 'gelia-pro-sidebar__row-icon' });
    const depthClass = depth <= 1
        ? 'gelia-pro-sidebar__link--l1'
        : depth === 2
            ? 'gelia-pro-sidebar__link--l2'
            : 'gelia-pro-sidebar__link--l3';

    return (
        <Link
            href={href}
            {...icono.triggerProps}
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={[
                'gelia-pro-sidebar__row',
                'gelia-pro-sidebar__link',
                depthClass,
                active ? 'gelia-pro-sidebar__row--active' : '',
            ].filter(Boolean).join(' ')}
            data-tip={collapsed ? label : ''}
            data-depth={depth}
        >
            {icono.nodo}
            <span className="gelia-pro-sidebar__row-label">{label}</span>
            {badge ? <span className="gelia-pro-sidebar__badge">{badge}</span> : null}
        </Link>
    );
}

export default memo(SidebarLink);
