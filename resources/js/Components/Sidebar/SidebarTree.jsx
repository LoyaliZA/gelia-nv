import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { buildSidebarNavigation, collectOpenGroupIdsForUrl } from '../../config/sidebarNavigation';
import SidebarGroup from './SidebarGroup';
import SidebarLink from './SidebarLink';
import SidebarCollapsedFlyout from './SidebarCollapsedFlyout';

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

    const [openGroups, setOpenGroups] = useState(() => {
        const ids = collectOpenGroupIdsForUrl(tree, url);
        return Object.fromEntries([...ids].map((id) => [id, true]));
    });

    const [flyout, setFlyout] = useState(null);
    const groupTriggerRefs = useRef(new Map());

    useEffect(() => {
        const ids = collectOpenGroupIdsForUrl(tree, url);
        setOpenGroups((prev) => {
            const next = { ...prev };
            ids.forEach((id) => {
                next[id] = true;
            });
            return next;
        });
    }, [url, tree]);

    useEffect(() => {
        setFlyout(null);
    }, [url, collapsed]);

    const closeFlyout = useCallback(() => setFlyout(null), []);

    const toggleGroup = useCallback((id) => {
        setOpenGroups((prev) => ({ ...prev, [id]: !prev[id] }));
    }, []);

    const handleCollapsedGroupClick = useCallback((id, event) => {
        const rect = event.currentTarget.getBoundingClientRect();
        setFlyout((prev) => (
            prev?.id === id ? null : { id, rect }
        ));
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
                closeFlyout();
                onNavigate?.();
            }}
        />
    );

    const renderGroup = (group, depth = 0, { inFlyout = false } = {}) => {
        const isOpen = !!openGroups[group.id];
        const hasActiveChild = groupHasActiveDescendant(group, url);

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
                onToggle={toggleGroup}
                onCollapsedClick={handleCollapsedGroupClick}
                triggerRef={depth === 0 && !inFlyout ? setGroupTriggerRef(group.id) : undefined}
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
                onClose={closeFlyout}
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
