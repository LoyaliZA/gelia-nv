import React from 'react';
import { Link } from '@inertiajs/react';
import { dashboardTonoClass } from './dashboardModulesCatalog';

export default function DashboardAdaptiveWidget({
    title,
    icon: Icon,
    tono = 'primario',
    badge = null,
    summary = null,
    href,
    ctaLabel = 'Explorar',
    minimalCount = null,
    minimalCountLabel = '',
    variant = 'desktop',
    children,
}) {
    const tonoClass = dashboardTonoClass(tono);
    const ctaClass = 'dashboard-widget__cta';
    const iconoCabecera = Icon ? (
        <span className="dashboard-widget__icon-wrap" aria-hidden>
            <Icon className="dashboard-widget__icon" />
        </span>
    ) : null;

    const cta = href ? (
        <Link href={href} className={ctaClass}>
            {Icon && <Icon className="dashboard-widget__cta-icon" aria-hidden />}
            <span className="truncate">{ctaLabel}</span>
        </Link>
    ) : null;

    if (variant === 'mobile') {
        return (
            <div className={`dashboard-widget-mobile theme-surface theme-border shadow-sm ${tonoClass}`}>
                <span className="dashboard-widget__bar" aria-hidden />
                <div className="dashboard-widget-mobile__header">
                    <div className="flex items-center gap-2 min-w-0 flex-1">
                        {iconoCabecera}
                        <h2 className="dashboard-widget-mobile__title theme-text-main">{title}</h2>
                    </div>
                    {badge}
                </div>

                {summary && <div className="dashboard-widget__summary">{summary}</div>}

                <div className="dashboard-widget-mobile__body custom-scrollbar">{children}</div>

                {cta && (
                    <div className="dashboard-widget-mobile__footer theme-border">
                        {cta}
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className={`dashboard-adaptive-widget theme-surface theme-border shadow-sm group ${tonoClass}`}>
            <span className="dashboard-widget__bar" aria-hidden />
            <div className="dashboard-widget__header min-w-0">
                <div className="flex items-center gap-2 min-w-0 flex-1">
                    {iconoCabecera}
                    <h2 className="dashboard-widget__header-title theme-text-main truncate">{title}</h2>
                </div>
                {badge}
            </div>

            {summary && <div className="dashboard-widget__summary">{summary}</div>}

            <div className="dashboard-widget__body custom-scrollbar">{children}</div>

            {href && (
                <>
                    <Link
                        href={href}
                        className="dashboard-widget__minimal-link theme-text-main outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] rounded-xl"
                        title={ctaLabel}
                    >
                        {Icon && <Icon className="dashboard-widget__minimal-icon" />}
                        {minimalCount != null && (
                            <span className="dashboard-widget__minimal-count text-lg font-semibold tabular-nums">
                                {minimalCount}
                            </span>
                        )}
                        {minimalCountLabel && (
                            <span className="text-[10px] font-medium theme-text-muted">{minimalCountLabel}</span>
                        )}
                    </Link>

                    <div className="dashboard-widget__footer theme-border">
                        {cta}
                    </div>
                </>
            )}
        </div>
    );
}
