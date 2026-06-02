import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { ChevronRight } from 'lucide-react';
import { buildSidebarNavigation, collectOpenGroupIdsForUrl } from '../config/sidebarNavigation';
import SidebarNavLeafLink from './SidebarNavLeafLink';

/** Sangría: raíz (caja), nivel 2, nivel 3 */
const PADDING_LINK = { 1: 'pl-10', 2: 'pl-14' };
const PADDING_SUBGROUP = 'pl-10';

function groupHasActiveDescendant(node, url) {
    if (node.type === 'link') return node.active?.(url) ?? false;
    if (node.type === 'group') {
        return node.children?.some((child) => groupHasActiveDescendant(child, url)) ?? false;
    }
    return false;
}

export default function SidebarNavMenu({ url, can, showAdminMenu, onNavigate }) {
    const tree = useMemo(
        () => buildSidebarNavigation({ can, showAdminMenu }),
        [can, showAdminMenu]
    );

    const [openGroups, setOpenGroups] = useState(() => {
        const ids = collectOpenGroupIdsForUrl(tree, url);
        return Object.fromEntries([...ids].map((id) => [id, true]));
    });

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

    const toggleGroup = useCallback((id) => {
        setOpenGroups((prev) => ({ ...prev, [id]: !prev[id] }));
    }, []);

    /** Nivel 1: categorías raíz — única fila con caja oscura y chevron */
    const renderRootGroup = (group) => {
        const isOpen = !!openGroups[group.id];
        const Icon = group.icon;
        const hasActiveChild = groupHasActiveDescendant(group, url);

        return (
            <div key={group.id} className="gelia-sidebar-nav-root-block flex flex-col gap-0.5 min-w-0">
                <button
                    type="button"
                    onClick={() => toggleGroup(group.id)}
                    aria-expanded={isOpen}
                    className={`gelia-sidebar-nav-root-btn flex items-center w-full gap-2.5 py-3.5 px-4 rounded-[1.25rem] transition-all outline-none border ${
                        hasActiveChild && !isOpen
                            ? 'gelia-sidebar-nav-root-btn--hint-active'
                            : 'gelia-sidebar-nav-root-btn--idle'
                    }`}
                >
                    <Icon className="w-4 h-4 shrink-0 gelia-sidebar-nav-root-icon" aria-hidden />
                    <span className="gelia-sidebar-nav-root-label flex-1 text-left">
                        {group.label}
                    </span>
                    <ChevronRight className={`w-4 h-4 shrink-0 gelia-sidebar-nav-chevron transition-transform duration-300 ${isOpen ? 'rotate-90' : ''}`} aria-hidden />
                </button>
                <div className={`sidebar-collapsible-wrapper ${isOpen ? 'sidebar-collapsible-wrapper--open' : ''}`}>
                    <div className="sidebar-collapsible-inner">
                        {group.children?.length > 0 && (
                            <div className="gelia-sidebar-nav-children flex flex-col gap-0.5 py-1 min-w-0">
                                {group.children.map((child) =>
                                    child.type === 'link'
                                        ? renderChildLink(child, 1)
                                        : renderSubGroup(child)
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        );
    };

    /** Nivel 2: sub-grupos (Comercial, Herramientas…) — texto plano, sin caja ni chevron */
    const renderSubGroup = (group) => {
        const isOpen = openGroups[group.id] ?? groupHasActiveDescendant(group, url);
        const Icon = group.icon;

        return (
            <div key={group.id} className="flex flex-col gap-0.5 min-w-0">
                <button
                    type="button"
                    onClick={() => toggleGroup(group.id)}
                    aria-expanded={isOpen}
                    className={`gelia-sidebar-nav-subgroup-btn flex items-center w-full py-2 pr-3 ${PADDING_SUBGROUP} rounded-lg transition-colors outline-none border-0 bg-transparent`}
                >
                    <Icon className="w-3.5 h-3.5 mr-2.5 shrink-0 gelia-sidebar-nav-child-icon" aria-hidden />
                    <span className="gelia-sidebar-nav-subgroup-label truncate text-left flex-1">
                        {group.label}
                    </span>
                    <ChevronRight className={`w-3.5 h-3.5 ml-auto shrink-0 gelia-sidebar-nav-chevron transition-transform duration-300 opacity-60 ${isOpen ? 'rotate-90' : ''}`} aria-hidden />
                </button>
                <div className={`sidebar-collapsible-wrapper ${isOpen ? 'sidebar-collapsible-wrapper--open' : ''}`}>
                    <div className="sidebar-collapsible-inner">
                        {group.children?.length > 0 && (
                            <div className="flex flex-col gap-0.5 min-w-0">
                                {group.children.map((child) => renderChildLink(child, 2))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        );
    };

    const renderChildLink = (item, linkDepth) => {
        const active = item.active?.(url) ?? false;
        const Icon = item.icon;
        const pad = PADDING_LINK[linkDepth] ?? 'pl-14';

        return (
            <SidebarNavLeafLink
                key={item.id}
                href={item.href()}
                active={active}
                onClick={onNavigate}
                icon={Icon}
                label={item.label}
                paddingClass={pad}
            />
        );
    };

    const renderNode = (node) => {
        if (!node) return null;
        if (node.type === 'header') {
            return (
                <span
                    key={node.id}
                    className="gelia-sidebar-nav-header px-4 mb-1 opacity-70"
                >
                    {node.label}
                </span>
            );
        }
        if (node.type === 'group') {
            return renderRootGroup(node);
        }
        return null;
    };

    return (
        <nav
            className="gelia-sidebar-nav-tree flex flex-col gap-1.5 px-2"
            aria-label="Navegación principal"
        >
            {tree.map(renderNode)}
        </nav>
    );
}
