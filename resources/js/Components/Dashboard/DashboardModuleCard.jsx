import React from 'react';
import { Link } from '@inertiajs/react';

export default function DashboardModuleCard({
    href,
    title,
    subtitle,
    icon: Icon,
    borderClass = 'theme-border',
    iconWrapClass = 'theme-element theme-border',
    iconClass = 'theme-text-main',
    iconWrapStyle,
    iconStyle,
    borderStyle,
    variant = 'desktop',
}) {
    if (variant === 'mobile') {
        return (
            <Link
                href={href}
                aria-label={title}
                className={`dashboard-module-card-mobile theme-element shadow-sm ${borderClass}`}
                style={borderStyle}
            >
                <div
                    className={`dashboard-module-card-mobile__icon-wrap ${iconWrapClass}`}
                    style={iconWrapStyle}
                >
                    <Icon className={`dashboard-module-card-mobile__icon ${iconClass}`} style={iconStyle} />
                </div>
                <h3 className="dashboard-module-card-mobile__title theme-text-main">{title}</h3>
            </Link>
        );
    }

    return (
        <Link
            href={href}
            data-module-title={title}
            aria-label={title}
            className={`dashboard-module-card theme-element shadow-sm outline-none group ${borderClass}`}
            style={borderStyle}
        >
            <span className="dashboard-module-card__tooltip" role="tooltip">
                {title}
            </span>
            <div
                className={`dashboard-module-card__icon-wrap ${iconWrapClass}`}
                style={iconWrapStyle}
            >
                <Icon className={`dashboard-module-card__icon ${iconClass}`} style={iconStyle} />
            </div>
            <h3 className="dashboard-module-card__title theme-text-main">{title}</h3>
            {subtitle && (
                <p className="dashboard-module-card__subtitle theme-text-muted">{subtitle}</p>
            )}
        </Link>
    );
}
