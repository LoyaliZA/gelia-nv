import React from 'react';
import { Link } from '@inertiajs/react';
import useIconoEnControl from '../../hooks/useIconoEnControl';

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
    const { ref, triggerProps } = useIconoEnControl();
    const enMovil = variant === 'mobile';
    const icono = (
        <Icon
            ref={ref}
            size={enMovil ? 18 : 20}
            duration={1}
            isAnimated={false}
            color="currentColor"
            className={`${enMovil ? 'dashboard-module-card-mobile__icon' : 'dashboard-module-card__icon'} ${iconClass}`}
            style={iconStyle}
        />
    );

    if (enMovil) {
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
                    {icono}
                </div>
                <span className="dashboard-module-card-mobile__label theme-text-main">{title}</span>
            </Link>
        );
    }

    return (
        <Link
            href={href}
            data-module-title={title}
            aria-label={subtitle ? `${title}. ${subtitle}` : title}
            className={`dashboard-module-card theme-element shadow-sm outline-none group ${borderClass}`}
            style={borderStyle}
            {...triggerProps}
        >
            <span className="dashboard-module-card__tooltip" role="tooltip">
                {subtitle ? `${title} — ${subtitle}` : title}
            </span>
            <div
                className={`dashboard-module-card__icon-wrap ${iconWrapClass}`}
                style={iconWrapStyle}
            >
                    {icono}
            </div>
            <h3 className="dashboard-module-card__title theme-text-main">{title}</h3>
            {subtitle && <p className="dashboard-module-card__subtitle theme-text-muted">{subtitle}</p>}
        </Link>
    );
}
