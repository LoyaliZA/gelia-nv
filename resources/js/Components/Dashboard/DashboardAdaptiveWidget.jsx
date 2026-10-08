import React from 'react';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { GELIA_BTN_OUTLINE, GELIA_ICON_BOX } from '../../utils/geliaTheme';

export default function DashboardAdaptiveWidget({
    title,
    icon: Icon,
    iconClassName = '',
    badge = null,
    summary = null,
    href,
    ctaLabel = 'Explorar',
    minimalCount = null,
    minimalCountLabel = '',
    variant = 'desktop',
    children,
}) {
    const footerLinkClass = `${GELIA_BTN_OUTLINE} w-full min-h-[44px] py-2.5 sm:py-3 text-[10px] font-semibold normal-case tracking-normal`;

    if (variant === 'mobile') {
        return (
            <div className="dashboard-widget-mobile theme-surface theme-border shadow-sm">
                <div className="dashboard-widget-mobile__header">
                    <div className="flex items-center gap-2 min-w-0 flex-1">
                        {Icon && (
                            <span className={`${GELIA_ICON_BOX} !p-2 shrink-0`} aria-hidden>
                                <Icon className={`w-4 h-4 ${iconClassName}`} style={iconClassName ? undefined : { color: 'var(--color-primario)' }} />
                            </span>
                        )}
                        <h2 className="dashboard-widget-mobile__title theme-text-main">{title}</h2>
                    </div>
                    {badge}
                </div>

                {summary && <div className="dashboard-widget__summary">{summary}</div>}

                <div className="dashboard-widget-mobile__body custom-scrollbar">{children}</div>

                {href && (
                    <div className="dashboard-widget-mobile__footer theme-border">
                        <Link href={href} className={footerLinkClass}>
                            <span className="truncate">{ctaLabel}</span>
                            <ArrowRight className="w-4 h-4 shrink-0" aria-hidden />
                        </Link>
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="dashboard-adaptive-widget theme-surface theme-border shadow-sm group">
            <div className="dashboard-widget__header relative z-10 min-w-0">
                <div className="flex items-center gap-2 min-w-0 flex-1">
                    {Icon && (
                        <span className={`${GELIA_ICON_BOX} !p-2 shrink-0`} aria-hidden>
                            <Icon
                                className={`w-4 h-4 ${iconClassName}`}
                                style={iconClassName ? undefined : { color: 'var(--color-primario)' }}
                            />
                        </span>
                    )}
                    <h2 className="dashboard-widget__header-title theme-text-main truncate">{title}</h2>
                </div>
                {badge}
            </div>

            {summary && <div className="dashboard-widget__summary relative z-10">{summary}</div>}

            <div className="dashboard-widget__body relative z-10 custom-scrollbar">{children}</div>

            {href && (
                <>
                    <Link
                        href={href}
                        className="dashboard-widget__minimal-link relative z-10 theme-text-main outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] rounded-xl"
                        title={ctaLabel}
                    >
                        {Icon && (
                            <Icon
                                className={`w-8 h-8 ${iconClassName}`}
                                style={iconClassName ? undefined : { color: 'var(--color-primario)' }}
                            />
                        )}
                        {minimalCount != null && (
                            <span className="text-lg font-semibold tabular-nums" style={{ color: 'var(--color-primario)' }}>
                                {minimalCount}
                            </span>
                        )}
                        {minimalCountLabel && (
                            <span className="text-[10px] font-medium theme-text-muted">{minimalCountLabel}</span>
                        )}
                    </Link>

                    <div className="dashboard-widget__footer relative z-10 theme-border">
                        <Link href={href} className={footerLinkClass}>
                            <span className="truncate">{ctaLabel}</span>
                            <ArrowRight className="w-4 h-4 shrink-0" aria-hidden />
                        </Link>
                    </div>
                </>
            )}
        </div>
    );
}
