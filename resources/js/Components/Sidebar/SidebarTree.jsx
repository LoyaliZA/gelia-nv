import React, { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { buildSidebarNavigation, collectOpenGroupIdsForUrl } from '../../config/sidebarNavigation';
import SidebarGroup from './SidebarGroup';
import SidebarLink from './SidebarLink';
import SidebarCollapsedFlyout from './SidebarCollapsedFlyout';
import { trackExpandScroll } from './sidebarAccordionScroll';
import {
    collectRootGroupIds,
    computeExclusiveGroupToggle,
    mergeRouteOpenGroups,
} from './sidebarAccordionToggle';
import useCollapsedFlyoutHover from './useCollapsedFlyoutHover';

const PRO_SCROLL_SELECTOR = '.gelia-pro-sidebar__nav';

function resolveHref(item) {
    if (typeof item?.href === 'function') return item.href();
    if (typeof item?.href === 'string') return item.href;
    return '#';
}

function groupHasActiveDescendant(node, url) {
    if (node.type === 'link') return node.active?.(url) ?? false;
    if (node.type === 'group') {
        return node.children?.some((child) => groupHasActiveDescendant(child, url)) ?? false;
    }
    return false;
}

function findGroupById(nodes, id) {
    for (const node of nodes || []) {
        if (!node) continue;
        if (node.type === 'group' && node.id === id) return node;
        if (node.type === 'group' && node.children?.length) {
            const found = findGroupById(node.children, id);
            if (found) return found;
        }
    }
    return null;
}

export default function SidebarTree({
    url,
    can,
    showAdminMenu,
    manualesHubVisible = false,
    geliaAiVisible = false,
    saldosFavorPendientes = 0,
    collapsed = false,
    onNavigate,
}) {
    const tree = useMemo(
        () => buildSidebarNavigation({
            can,
            showAdminMenu,
            manualesHubVisible,
            geliaAiVisible,
            saldosFavorPendientes,
        }),
        [can, showAdminMenu, manualesHubVisible, geliaAiVisible, saldosFavorPendientes]
    );

    const rootGroupIds = useMemo(() => collectRootGroupIds(tree), [tree]);

    const [openGroups, setOpenGroups] = useState(() => {
        const ids = collectOpenGroupIdsForUrl(tree, url);
        const initial = Object.fromEntries([...ids].map((id) => [id, true]));
        return mergeRouteOpenGroups(initial, ids, collectRootGroupIds(tree));
    });

    const [flyout, setFlyout] = useState(null);
    const groupTriggerRefs = useRef(new Map());
    const groupRefs = useRef(new Map());
    const pendingScrollGroupIdRef = useRef(null);
    const stopExpandScrollRef = useRef(null);

    useEffect(() => {
        const ids = collectOpenGroupIdsForUrl(tree, url);
        setOpenGroups((prev) => mergeRouteOpenGroups(prev, ids, rootGroupIds));
    }, [url, tree, rootGroupIds]);

    useEffect(() => {
        setFlyout(null);
    }, [url, collapsed]);

    useEffect(() => () => {
        stopExpandScrollRef.current?.();
        stopExpandScrollRef.current = null;
    }, []);

    useLayoutEffect(() => {
        const id = pendingScrollGroupIdRef.current;
        if (!id || !openGroups[id]) return;
        pendingScrollGroupIdRef.current = null;
        stopExpandScrollRef.current?.();
        stopExpandScrollRef.current = trackExpandScroll(
            groupRefs.current.get(id),
            PRO_SCROLL_SELECTOR
        );
    }, [openGroups]);

    const closeFlyoutWithReset = useCallback(() => {
        setFlyout(null);
        hoverRef.current?.resetClickMode();
    }, []);

    const hoverRef = useRef(null);

    const openFlyout = useCallback((id, rect, { toggle = false, viaClick = false } = {}) => {
        setFlyout((prev) => {
            if (toggle && prev?.id === id) {
                hoverRef.current?.resetClickMode();
                return null;
            }
            return { id, rect, viaClick: viaClick || toggle };
        });
    }, []);

    const hover = useCollapsedFlyoutHover({
        collapsed,
        onOpenFlyout: openFlyout,
        onCloseFlyout: () => {
            setFlyout(null);
            hoverRef.current?.resetClickMode();
        },
        onOpenNestedGroup: (groupId) => {
            setOpenGroups((prev) => ({ ...prev, [groupId]: true }));
        },
    });
    hoverRef.current = hover;

    const toggleGroup = useCallback((id, depth = 0) => {
        setOpenGroups((prev) => {
            const { next, scrollGroupId } = computeExclusiveGroupToggle(prev, id, rootGroupIds, depth);
            if (scrollGroupId) pendingScrollGroupIdRef.current = scrollGroupId;
            return next;
        });
    }, [rootGroupIds]);

    const setGroupRef = useCallback((id) => (el) => {
        if (el) groupRefs.current.set(id, el);
        else groupRefs.current.delete(id);
    }, []);

    const setGroupTriggerRef = useCallback((id) => (el) => {
        if (el) groupTriggerRefs.current.set(id, el);
        else groupTriggerRefs.current.delete(id);
    }, []);

    const renderLink = (item, depth, { inFlyout = false } = {}) => (
        <SidebarLink
            key={item.id}
            href={resolveHref(item)}
            label={item.label}
            icon={item.icon}
            active={item.active?.(url) ?? false}
            badge={item.badge}
            collapsed={collapsed && !inFlyout}
            depth={depth}
            onClick={() => {
                closeFlyoutWithReset();
                onNavigate?.();
            }}
        />
    );

    const renderGroup = (group, depth = 0, { inFlyout = false } = {}) => {
        const isOpen = !!openGroups[group.id];
        const hasActiveChild = groupHasActiveDescendant(group, url);
        const hasChildren = (group.children?.length ?? 0) > 0;

        return (
            <SidebarGroup
                key={group.id}
                id={group.id}
                label={group.label}
                icon={group.icon}
                open={isOpen}
                hasActiveChild={hasActiveChild}
                collapsed={collapsed}
                flyoutOpen={flyout?.id === group.id}
                onToggle={() => toggleGroup(group.id, depth)}
                onCollapsedClick={hover.handleCollapsedClick}
                onCollapsedHoverEnter={depth === 0 && !inFlyout ? hover.handleRootHoverEnter : undefined}
                onCollapsedHoverLeave={depth === 0 && !inFlyout ? hover.handleRootHoverLeave : undefined}
                onNestedHoverEnter={inFlyout && hasChildren ? hover.handleNestedHoverEnter : undefined}
                onNestedHoverLeave={inFlyout && hasChildren ? hover.handleNestedHoverLeave : undefined}
                triggerRef={depth === 0 && !inFlyout ? setGroupTriggerRef(group.id) : undefined}
                groupRef={setGroupRef(group.id)}
                depth={depth}
                inFlyout={inFlyout}
            >
                {group.children?.map((child) => (
                    child.type === 'group'
                        ? renderGroup(child, depth + 1, { inFlyout })
                        : renderLink(child, depth + 1, { inFlyout })
                ))}
            </SidebarGroup>
        );
    };

    const flyoutGroup = flyout ? findGroupById(tree, flyout.id) : null;

    return (
        <>
            <nav className="gelia-pro-sidebar__nav" aria-label="Navegación principal">
                {tree.map((node) => {
                    if (!node) return null;
                    if (node.type === 'header') {
                        if (collapsed) return null;
                        return (
                            <span key={node.id} className="gelia-pro-sidebar__header">
                                {node.label}
                            </span>
                        );
                    }
                    if (node.type === 'group') {
                        return renderGroup(node, 0);
                    }
                    if (node.type === 'link') {
                        return renderLink(node, 0);
                    }
                    return null;
                })}
            </nav>

            <SidebarCollapsedFlyout
                open={Boolean(flyoutGroup)}
                anchorRect={flyout?.rect}
                title={flyoutGroup?.label || ''}
                onClose={closeFlyoutWithReset}
                onMouseEnter={hover.handleFlyoutEnter}
                onMouseLeave={hover.handleFlyoutLeave}
                hoverMode={collapsed && !flyout?.viaClick}
            >
                {flyoutGroup?.children?.map((child) => (
                    child.type === 'group'
                        ? renderGroup(child, 1, { inFlyout: true })
                        : renderLink(child, 1, { inFlyout: true })
                ))}
            </SidebarCollapsedFlyout>
        </>
    );
}
