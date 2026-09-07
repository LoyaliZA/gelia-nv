import React from 'react';
import { ChevronRight } from 'lucide-react';

export default function SidebarGroup({
    id,
    label,
    icon: Icon,
    open = false,
    hasActiveChild = false,
    collapsed = false,
    flyoutOpen = false,
    onToggle,
    onCollapsedClick,
    onCollapsedHoverEnter,
    onCollapsedHoverLeave,
    onNestedHoverEnter,
    onNestedHoverLeave,
    triggerRef,
    groupRef,
    children,
    depth = 0,
    inFlyout = false,
}) {
    const isRoot = depth === 0;
    const isExpanded = inFlyout ? open : (open && !collapsed);

    const handleClick = (event) => {
        if (collapsed && !inFlyout) {
            onCollapsedClick?.(id, event);
            return;
        }
        onToggle?.();
    };

    const handleMouseEnter = (event) => {
        if (collapsed && !inFlyout) {
            onCollapsedHoverEnter?.(id, event);
            return;
        }
        if (inFlyout) {
            onNestedHoverEnter?.(id);
        }
    };

    const handleMouseLeave = () => {
        if (collapsed && !inFlyout) {
            onCollapsedHoverLeave?.();
            return;
        }
        if (inFlyout) {
            onNestedHoverLeave?.();
        }
    };

    return (
        <div
            ref={groupRef}
            className={`gelia-pro-sidebar__group ${isRoot ? 'gelia-pro-sidebar__group--root' : 'gelia-pro-sidebar__group--nested'}`}
            data-group-id={id}
            data-depth={depth}
        >
            <button
                ref={triggerRef}
                type="button"
                className={[
                    'gelia-pro-sidebar__row',
                    'gelia-pro-sidebar__tooltip',
                    isRoot ? 'gelia-pro-sidebar__row--root' : 'gelia-pro-sidebar__row--subgroup',
                    hasActiveChild && !isExpanded ? 'gelia-pro-sidebar__row--hint' : '',
                    isExpanded && isRoot && !collapsed ? 'gelia-pro-sidebar__row--root-open' : '',
                    flyoutOpen ? 'gelia-pro-sidebar__row--flyout-open' : '',
                ].filter(Boolean).join(' ')}
                aria-expanded={isExpanded || flyoutOpen}
                onClick={handleClick}
                onMouseEnter={handleMouseEnter}
                onMouseLeave={handleMouseLeave}
                data-tip={collapsed && !inFlyout ? label : ''}
            >
                {Icon ? <Icon className="gelia-pro-sidebar__row-icon" aria-hidden /> : null}
                <span className="gelia-pro-sidebar__row-label">{label}</span>
                <ChevronRight
                    className={`gelia-pro-sidebar__row-chevron ${isExpanded ? 'gelia-pro-sidebar__row-chevron--open' : ''}`}
                    aria-hidden
                />
            </button>
            <div
                className={`gelia-pro-sidebar__collapse ${isExpanded ? 'gelia-pro-sidebar__collapse--open' : ''}`}
                aria-hidden={!isExpanded}
            >
                <div className="gelia-pro-sidebar__collapse-inner">
                    <div className={`gelia-pro-sidebar__children ${inFlyout ? 'gelia-pro-sidebar__children--flyout' : ''}`} data-depth={depth}>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
