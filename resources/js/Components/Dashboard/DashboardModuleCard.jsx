import React from 'react';
import { Link } from '@inertiajs/react';
import useIconoEnControl from '../../hooks/useIconoEnControl';
import { dashboardTonoClass } from './dashboardModulesCatalog';

export default function DashboardModuleCard({
    href,
    title,
    subtitle,
    icon: Icon,
    tono = 'primario',
    variant = 'desktop',
}) {
    const { ref, triggerProps } = useIconoEnControl();
    const enMovil = variant === 'mobile';
    const tonoClass = dashboardTonoClass(tono);
    const ariaLabel = subtitle ? `${title}. ${subtitle}` : title;
    const icono = (
        <Icon
            ref={ref}
            size={enMovil ? 18 : 20}
            duration={1}
            isAnimated={false}
            color="currentColor"
            className={enMovil ? 'dashboard-module-card-mobile__icon' : 'dashboard-module-card__icon'}
        />
    );

    if (enMovil) {
        return (
            <Link
                href={href}
                aria-label={ariaLabel}
                title={ariaLabel}
                className={`dashboard-module-card-mobile theme-element shadow-sm ${tonoClass}`}
            >
                <span className="dashboard-module-card__bar" aria-hidden />
                <div className="dashboard-module-card-mobile__icon-wrap">
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
            aria-label={ariaLabel}
            className={`dashboard-module-card theme-element shadow-sm outline-none group ${tonoClass}`}
            {...triggerProps}
        >
            <span className="dashboard-module-card__bar" aria-hidden />
            <span className="dashboard-module-card__tooltip" role="tooltip">
                {subtitle ? `${title} — ${subtitle}` : title}
            </span>
            <div className="dashboard-module-card__icon-wrap">
                {icono}
            </div>
            <h3 className="dashboard-module-card__title theme-text-main">{title}</h3>
            {subtitle && <p className="dashboard-module-card__subtitle theme-text-muted">{subtitle}</p>}
        </Link>
    );
}
