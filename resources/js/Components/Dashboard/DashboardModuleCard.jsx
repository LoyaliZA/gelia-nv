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
        const ariaLabel = subtitle ? `${title}. ${subtitle}` : title;

        return (
            <Link
                href={href}
                aria-label={ariaLabel}
                title={ariaLabel}
                className={`dashboard-module-card-mobile theme-element shadow-sm ${borderClass}`}
                style={borderStyle}
            >
                <div
                    className={`dashboard-module-card-mobile__icon-wrap ${iconWrapClass}`}
                    style={iconWrapStyle}
                >
                    <Icon className={`dashboard-module-card-mobile__icon ${iconClass}`} style={iconStyle} />
                </div>
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
                {subtitle ? `${title} — ${subtitle}` : title}
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
