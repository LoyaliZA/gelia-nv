import React, { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { hasAnyAdminModuleAccess } from '../../config/adminModules';
import useSidebarState from './useSidebarState';
import MobileSidebarDrawer from './MobileSidebarDrawer';

/**
 * Sidebar profesional fijo a la izquierda (desktop) + drawer (móvil).
 * Se activa con tema_visual.layout_sidebar === 'professional_left'.
 */
export default function ProfessionalSidebar({
    isDarkMode,
    toggleTheme,
    user,
    permissions = [],
    sidebarMode = 'expanded',
    isMobileViewport = false,
}) {
    const { url, props } = usePage();
    const { auth, manuales_hub_visible: manualesHubVisible, gelia_ai_visible: geliaAiVisible } = props;

    const {
        collapsed,
        structuralCollapsed,
        anim,
        drawerOpen,
        userMenuOpen,
        drawerPanelRef,
        userMenuRef,
        userButtonRef,
        toggleCollapsed,
        closeDrawer,
        closeUserMenu,
        toggleUserMenu,
    } = useSidebarState({
        initialMode: sidebarMode,
        isMobile: isMobileViewport,
    });

    const can = (permission) => {
        const isSuperAdmin = user?.roles?.includes('Super Admin');
        return isSuperAdmin || permissions?.includes(permission);
    };

    const showAdminMenu = hasAnyAdminModuleAccess(can);

    useEffect(() => {
        closeDrawer();
        closeUserMenu();
    }, [url, closeDrawer, closeUserMenu]);

    const treeProps = {
        url,
        can,
        showAdminMenu,
        manualesHubVisible: Boolean(manualesHubVisible),
        geliaAiVisible: Boolean(geliaAiVisible),
        saldosFavorPendientes: Number(auth?.saldos_favor_pendientes || 0),
        user,
        userMenuOpen,
        userButtonRef,
        userMenuRef,
        onToggleUserMenu: toggleUserMenu,
        onCloseUserMenu: closeUserMenu,
        isDarkMode,
        toggleTheme,
    };

    if (isMobileViewport) {
        return (
            <MobileSidebarDrawer
                {...treeProps}
                asDrawer
                open={drawerOpen}
                panelRef={drawerPanelRef}
                onClose={closeDrawer}
                collapsed={false}
                structuralCollapsed={false}
                anim={null}
                onNavigate={closeDrawer}
            />
        );
    }

    return (
        <MobileSidebarDrawer
            {...treeProps}
            asDrawer={false}
            open
            collapsed={collapsed}
            structuralCollapsed={structuralCollapsed}
            anim={anim}
            onToggleCollapse={toggleCollapsed}
            onNavigate={undefined}
        />
    );
}
