import React from 'react';
import { Link } from '@inertiajs/react';

export default function SidebarLink({
    href,
    label,
    icon: Icon,
    active = false,
    onClick,
    badge,
    collapsed = false,
    depth = 1,
}) {
    const depthClass = depth <= 1
        ? 'gelia-pro-sidebar__link--l1'
        : depth === 2
            ? 'gelia-pro-sidebar__link--l2'
            : 'gelia-pro-sidebar__link--l3';

    return (
        <Link
            href={href}
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={[
                'gelia-pro-sidebar__row',
                'gelia-pro-sidebar__link',
                'gelia-pro-sidebar__tooltip',
                depthClass,
                active ? 'gelia-pro-sidebar__row--active' : '',
            ].filter(Boolean).join(' ')}
            data-tip={collapsed ? label : ''}
            data-depth={depth}
        >
            {Icon ? <Icon className="gelia-pro-sidebar__row-icon" aria-hidden /> : null}
            <span className="gelia-pro-sidebar__row-label">{label}</span>
            {badge ? <span className="gelia-pro-sidebar__badge">{badge}</span> : null}
        </Link>
    );
}
