import React from 'react';
import SidebarBrand from './SidebarBrand';
import SidebarTree from './SidebarTree';
import SidebarUtilities from './SidebarUtilities';
import SidebarUserPanel from './SidebarUserPanel';

/**
 * Panel de navegación profesional (desktop fijo o drawer móvil).
 */
export default function MobileSidebarDrawer({
    asDrawer = false,
    open = true,
    panelRef,
    onClose,
    collapsed = false,
    structuralCollapsed = collapsed,
    anim = null,
    onToggleCollapse,
    url,
    can,
    showAdminMenu,
    manualesHubVisible,
    geliaAiVisible,
    saldosFavorPendientes,
    onNavigate,
    user,
    userMenuOpen,
    userButtonRef,
    userMenuRef,
    onToggleUserMenu,
    onCloseUserMenu,
    isDarkMode,
    toggleTheme,
}) {
    const className = [
        'gelia-pro-sidebar',
        asDrawer ? 'gelia-pro-sidebar--drawer' : '',
    ].filter(Boolean).join(' ');

    return (
        <>
            {asDrawer && (
                <div
                    className={`gelia-pro-sidebar-backdrop ${open ? 'gelia-pro-sidebar-backdrop--open' : ''}`}
                    onClick={onClose}
                    aria-hidden={!open}
                />
            )}
            <aside
                ref={panelRef}
                className={className}
                data-collapsed={collapsed ? 'true' : 'false'}
                data-anim={anim || undefined}
                data-open={asDrawer ? (open ? 'true' : 'false') : undefined}
                aria-label="Navegación"
                aria-hidden={asDrawer && !open ? true : undefined}
            >
                <div className="gelia-pro-sidebar__track">
                    <SidebarBrand
                        collapsed={collapsed}
                        onToggleCollapse={onToggleCollapse}
                        showToggle={!asDrawer}
                    />
                    <SidebarTree
                        url={url}
                        can={can}
                        showAdminMenu={showAdminMenu}
                        manualesHubVisible={manualesHubVisible}
                        geliaAiVisible={geliaAiVisible}
                        saldosFavorPendientes={saldosFavorPendientes}
                        collapsed={structuralCollapsed}
                        onNavigate={onNavigate}
                    />
                    {!asDrawer && <SidebarUtilities collapsed={structuralCollapsed} />}
                    <SidebarUserPanel
                        user={user}
                        collapsed={structuralCollapsed}
                        userMenuOpen={userMenuOpen}
                        userButtonRef={userButtonRef}
                        userMenuRef={userMenuRef}
                        onToggleMenu={onToggleUserMenu}
                        onCloseMenu={onCloseUserMenu}
                        isDarkMode={isDarkMode}
                        toggleTheme={toggleTheme}
                    />
                </div>
            </aside>
        </>
    );
}
