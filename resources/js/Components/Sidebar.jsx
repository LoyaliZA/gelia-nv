import React, { useState, useRef, useEffect, useCallback } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import {
    Menu, X, Moon, Sun, ArrowLeft,
    LayoutDashboard, Briefcase,
    Settings, Settings2, Users, LogOut,
    Map, FileText, Package, Receipt, Ban, MessageCircle,
    User, Sparkles, BarChart3,
} from 'lucide-react';
import { hasAnyAdminModuleAccess } from '../config/adminModules';

import GeliaLogo from './GeliaLogo';

import NotificationBell, { NotificationCountBadge } from './NotificationBell';
import MensajeriaWidget from './Mensajeria/MensajeriaWidget';

const SIDEBAR_MODE_STORAGE_KEY = 'theme_sidebar_mode';
const SIDEBAR_MODES = { collapsed: 'collapsed', expanded: 'expanded' };
const WIDGET_MS = 320;
const MENU_OPEN_MS = 360;
const MENU_CLOSE_MS = 300;
const HOVER_LEAVE_DELAY_MS = 160;
const EASE_SMOOTH = 'cubic-bezier(0.22, 1, 0.36, 1)';

const FIXED_POSITIONS = { left: 'left', right: 'right', top: 'top', bottom: 'bottom' };

/** Clases de posición/animación compartidas entre menú principal y menú de perfil */
function buildSidebarMenuLayoutClasses({ isFixedVertical, isFixedHorizontal, isMobileMode, fixedPos, isOpen, isDrawer = false }) {
    if (isMobileMode && isDrawer) {
        return ` gelia-sidebar-drawer h-full ml-0 rounded-none border-r theme-border ${isOpen ? '' : ''}`;
    }
    if (isFixedVertical) {
        const menuFixedClass = fixedPos === FIXED_POSITIONS.right ? 'sidebar-menu-fixed-right' : 'sidebar-menu-fixed';
        const menuRounding = fixedPos === FIXED_POSITIONS.right
            ? 'rounded-l-[2.5rem] rounded-r-none border-r-0'
            : 'rounded-r-[2.5rem] rounded-l-none border-l-0';
        return ` h-screen ml-0 ${menuRounding} ${menuFixedClass} ${isOpen ? `${menuFixedClass}--open` : ''}`;
    }
    if (isFixedHorizontal) {
        return ` rounded-[2rem] w-[300px] max-w-[90vw] sidebar-menu-float ${fixedPos === FIXED_POSITIONS.bottom ? 'mb-3' : 'mt-0'} ${isOpen ? 'sidebar-menu-float--open' : ''}`;
    }
    if (isMobileMode) {
        return ` rounded-[2rem] w-[90vw] max-w-[320px] mb-4 sidebar-menu-float ${isOpen ? 'sidebar-menu-float--open' : ''}`;
    }
    return ` rounded-[2.5rem] w-[300px] mt-4 sidebar-menu-float ${isOpen ? 'sidebar-menu-float--open' : ''}`;
}

const prefixGroupClass = (visible, orientation) => {
    const base = 'flex overflow-hidden shrink-0 sidebar-widget-reveal';
    const pointer = visible ? 'pointer-events-auto' : 'pointer-events-none';
    if (orientation === 'vertical') {
        return `${base} ${pointer} flex-col items-center w-full ${visible ? 'max-h-[320px] opacity-100 gap-5 pt-5 border-t theme-border' : 'max-h-0 opacity-0 gap-0 pt-0 border-t-0'}`;
    }
    return `${base} ${pointer} flex-row items-center ${visible ? 'max-w-[11.5rem] opacity-100 gap-4 pl-3 ml-2 border-l theme-border' : 'max-w-0 opacity-0 gap-0 pl-0 ml-0 border-l-0'}`;
};

/** Alertas + mensajería: columna centrada en barra fija vertical; fila en horizontal/flotante */
const suffixGroupClass = (visible, orientation) => {
    const base = 'shrink-0 sidebar-widget-reveal sidebar-widget-icons';
    if (orientation === 'vertical') {
        return `${base} flex flex-col items-center justify-center w-full ${visible ? 'max-h-[9.5rem] opacity-100 gap-3 py-3 border-t theme-border pointer-events-auto overflow-visible' : 'max-h-0 opacity-0 gap-0 py-0 border-t-0 pointer-events-none overflow-hidden'}`;
    }
    return `${base} flex flex-row items-center justify-center ${visible ? 'max-w-[7.5rem] sm:max-w-[8rem] opacity-100 ms-1.5 sm:ms-2 pointer-events-auto overflow-visible gap-1.5 sm:gap-2 py-0.5 border-s theme-border' : 'max-w-0 opacity-0 ms-0 pointer-events-none overflow-hidden gap-0 border-s-0'}`;
};

const SIDEBAR_WIDGET_ICON_BTN_CLASS = 'sidebar-widget-icon-btn';
const PROFILE_MENU_ITEMS = [
    { id: 'perfil', label: 'Mi Perfil', routeName: 'profile.index', path: '/perfil', icon: User },
    { id: 'preferencias', label: 'Preferencias', routeName: 'profile.preferencias', path: '/perfil/preferencias', icon: Settings2 },
    { id: 'novedades', label: 'Novedades', routeName: 'profile.novedades', path: '/perfil/novedades', icon: Sparkles },
];

function profileMenuHref(item) {
    if (typeof route === 'function') {
        try {
            return route(item.routeName);
        } catch {
            // Ziggy desactualizado: usar path literal
        }
    }
    return item.path;
}

function adminPanelHref() {
    if (typeof route === 'function') {
        try {
            return route('admin.index');
        } catch {
            // Ziggy desactualizado
        }
    }
    return '/admin';
}

export default function Sidebar({ isDarkMode, toggleTheme, user, permissions, layout = 'floating_left', sidebarMode = 'collapsed', fixedPosition = 'left', useMobileTopBar = false }) {
    const { url, props: { auth } } = usePage();
    const isAdminActive = url.startsWith('/admin');

    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isMenuClosing, setIsMenuClosing] = useState(false);
    const [isHovered, setIsHovered] = useState(false);
    const [isProfileMenuOpen, setIsProfileMenuOpen] = useState(false);
    const [isProfileMenuClosing, setIsProfileMenuClosing] = useState(false);
    const [isMobile, setIsMobile] = useState(false);

    const closeTimerRef = useRef(null);
    const menuCloseTimerRef = useRef(null);
    const profileMenuCloseTimerRef = useRef(null);
    const profileMenuRef = useRef(null);
    const profileAvatarButtonRef = useRef(null);
    const { post } = useForm();

    const unreadCount = (auth?.notificaciones || []).filter(n => !n.read_at).length;

    const isProfileMenuVisible = isProfileMenuOpen || isProfileMenuClosing;

    useEffect(() => () => {
        clearTimeout(closeTimerRef.current);
        clearTimeout(menuCloseTimerRef.current);
        clearTimeout(profileMenuCloseTimerRef.current);
    }, []);

    const isRouteActive = (path) => {
        if (path === '/dashboard' && url === '/dashboard') return true;
        if (path === '/perfil') return url === '/perfil' || url === '/perfil/';
        if (path !== '/dashboard' && url.startsWith(path)) return true;
        return false;
    };

    const isProfileSectionActive = url.startsWith('/perfil');

    const linkBaseClass = "flex items-center w-full px-6 py-4 rounded-[1.5rem] transition-all outline-none ";
    const linkActiveClass = "bg-[var(--color-primario)] text-white shadow-lg border border-transparent";
    const linkInactiveClass = "theme-element theme-text-muted hover:theme-text-main hover:shadow-md border border-transparent hover:border-[var(--color-primario)]";

    useEffect(() => {
        const handleResize = () => setIsMobile(window.innerWidth < 768);
        handleResize();
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    const effectiveLayout = isMobile ? 'mobile_bottom' : layout;
    const isFixed = effectiveLayout === 'fixed';
    const isRight = effectiveLayout === 'floating_right';
    const isMobileMode = effectiveLayout === 'mobile_bottom';
    const fixedPos = FIXED_POSITIONS[fixedPosition] ? fixedPosition : FIXED_POSITIONS.left;
    const isFixedVertical = isFixed && (fixedPos === FIXED_POSITIONS.left || fixedPos === FIXED_POSITIONS.right);
    const isFixedHorizontal = isFixed && (fixedPos === FIXED_POSITIONS.top || fixedPos === FIXED_POSITIONS.bottom);
    const widgetOrientation = isFixedVertical ? 'vertical' : 'horizontal';
    const pinExpanded = !isMobileMode && sidebarMode === SIDEBAR_MODES.expanded;

    const isMenuVisible = isMenuOpen || isMenuClosing;
    const isWidgetExpanded = isMobileMode || pinExpanded || isHovered || isMenuVisible || isProfileMenuVisible;
    const showCollapsedWidget = !isMobileMode && !isWidgetExpanded;
    const showSecondaryControls = isWidgetExpanded;

    // --- LÓGICA DE PERMISOS BLINDADA ---
    const can = (permission) => {
        const isSuperAdmin = user?.roles?.includes('Super Admin');
        const hasPermission = permissions?.includes(permission);
        return isSuperAdmin || hasPermission;
    };

    const showAdminMenu = hasAnyAdminModuleAccess(can);
    const showOperacionesMenu = can('listados.ver');

    const openMenu = () => {
        clearTimeout(closeTimerRef.current);
        clearTimeout(menuCloseTimerRef.current);
        setIsMenuClosing(false);
        setIsMenuOpen(true);
    };

    const closeMenu = () => {
        if (!isMenuOpen) return;

        setIsMenuClosing(true);
        setIsMenuOpen(false);

        clearTimeout(menuCloseTimerRef.current);
        menuCloseTimerRef.current = setTimeout(() => {
            setIsMenuClosing(false);
        }, MENU_CLOSE_MS);
    };

    useEffect(() => {
        if (pinExpanded) {
            setIsHovered(true);
            return;
        }
        if (!isMobileMode) {
            setIsHovered(false);
            if (isMenuOpen) closeMenu();
        }
    }, [pinExpanded, isMobileMode, sidebarMode]);

    const handleMenuToggle = () => {
        if (isMobileMode) {
            if (isMenuOpen) closeMenu();
            else openMenu();
            return;
        }
        if (isMenuOpen) closeMenu();
    };

    useEffect(() => {
        const onToggleFromMensajeria = () => handleMenuToggle();
        const onOpenFromMensajeria = () => openMenu();
        window.addEventListener('gelia-sidebar-toggle-menu', onToggleFromMensajeria);
        window.addEventListener('gelia-sidebar-open-menu', onOpenFromMensajeria);
        return () => {
            window.removeEventListener('gelia-sidebar-toggle-menu', onToggleFromMensajeria);
            window.removeEventListener('gelia-sidebar-open-menu', onOpenFromMensajeria);
        };
    }, [isMenuOpen, isMobileMode]);

    const handleHamburgerHover = () => {
        if (isMobileMode) return;
        clearTimeout(closeTimerRef.current);
        openMenu();
    };

    const handleHoverEnter = () => {
        if (isMobileMode) return;
        clearTimeout(closeTimerRef.current);
        setIsHovered(true);
    };

    const handleHoverLeave = () => {
        if (isMobileMode) return;

        closeTimerRef.current = setTimeout(() => {
            closeMenu();

            if (!pinExpanded) {
                closeTimerRef.current = setTimeout(() => {
                    setIsHovered(false);
                }, WIDGET_MS);
            }
        }, HOVER_LEAVE_DELAY_MS);
    };

    const closeProfileMenu = useCallback(() => {
        if (!isProfileMenuOpen && !isProfileMenuClosing) return;

        setIsProfileMenuClosing(true);
        setIsProfileMenuOpen(false);

        clearTimeout(profileMenuCloseTimerRef.current);
        profileMenuCloseTimerRef.current = setTimeout(() => {
            setIsProfileMenuClosing(false);
        }, MENU_CLOSE_MS);
    }, [isProfileMenuOpen, isProfileMenuClosing]);

    const openProfileMenu = useCallback(() => {
        clearTimeout(profileMenuCloseTimerRef.current);
        setIsProfileMenuClosing(false);
        setIsProfileMenuOpen(true);
    }, []);

    const toggleProfileMenu = (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (isProfileMenuOpen) {
            closeProfileMenu();
            return;
        }
        openProfileMenu();
    };

    // Cerrar menús al navegar
    useEffect(() => {
        setIsProfileMenuOpen(false);
        setIsProfileMenuClosing(false);
        clearTimeout(profileMenuCloseTimerRef.current);
        setIsMenuOpen(false);
        setIsMenuClosing(false);
        clearTimeout(menuCloseTimerRef.current);
    }, [url]);

    // Clic fuera: registrar después del clic que abrió el menú
    useEffect(() => {
        if (!isProfileMenuOpen) return undefined;

        const handlePointerDown = (event) => {
            if (
                profileMenuRef.current?.contains(event.target) ||
                profileAvatarButtonRef.current?.contains(event.target)
            ) {
                return;
            }
            closeProfileMenu();
        };

        const timerId = setTimeout(() => {
            document.addEventListener('mousedown', handlePointerDown);
        }, 0);

        return () => {
            clearTimeout(timerId);
            document.removeEventListener('mousedown', handlePointerDown);
        };
    }, [isProfileMenuOpen, closeProfileMenu]);

    const renderProfileAvatarMenu = (compact = false) => {
        const avatarSizeClass = compact
            ? 'w-6 h-6 border-2'
            : isFixedVertical
                ? 'w-11 h-11 border'
                : 'w-9 h-9 sm:w-10 sm:h-10 border ml-1 sm:ml-2';

        return (
            <div className="relative shrink-0 pointer-events-auto">
                <button
                    ref={profileAvatarButtonRef}
                    type="button"
                    onMouseDown={(e) => e.stopPropagation()}
                    onClick={toggleProfileMenu}
                    aria-expanded={isProfileMenuOpen}
                    aria-haspopup="menu"
                    aria-label="Menú de perfil"
                    className={`rounded-full flex items-center justify-center cursor-pointer overflow-hidden transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] border outline-none group ${avatarSizeClass} ${isProfileSectionActive ? 'border-[var(--color-primario)] shadow-md' : 'theme-element theme-border'}`}
                >
                    {user?.foto_perfil ? (
                        <img src={`/storage/${user.foto_perfil}`} alt="Perfil" className="w-full h-full object-cover rounded-full transition-transform group-hover:scale-110" />
                    ) : (
                        <div className="flex items-center justify-center w-full h-full bg-transparent">
                            <span
                                className={`font-black leading-none select-none ${compact ? 'text-[10px]' : 'text-lg'}`}
                                style={{ color: 'var(--color-primario)' }}
                            >
                                {user?.name ? user.name.charAt(0).toUpperCase() : 'U'}
                            </span>
                        </div>
                    )}
                </button>
            </div>
        );
    };

    const mobileDrawerMode = isMobileMode && useMobileTopBar;

    // 1. Contenedor Base: Ocupa todo el ancho pero NO bloquea los clics
    let navClasses = "fixed z-[200] flex pointer-events-none sidebar-mount ";
    if (mobileDrawerMode) {
        navClasses += "gelia-sidebar-mount--mobile ";
    } else if (isMobileMode) {
        navClasses += "bottom-6 left-0 right-0 w-full flex-col-reverse items-center";
    } else if (isFixed) {
        if (fixedPos === FIXED_POSITIONS.right) navClasses += "top-0 right-0 h-screen flex-row-reverse";
        else if (fixedPos === FIXED_POSITIONS.top) navClasses += "top-0 left-0 right-0 w-full flex-col items-start";
        else if (fixedPos === FIXED_POSITIONS.bottom) navClasses += "bottom-0 left-0 right-0 w-full flex-col-reverse items-start";
        else navClasses += "top-0 left-0 h-screen flex-row";
    } else {
        navClasses += `top-6 flex-col ${isRight ? 'right-6 items-end' : 'left-6 items-start'}`;
    }

    let hoverContainerClasses = "pointer-events-auto flex ";
    if (isFixedVertical) {
        hoverContainerClasses += fixedPos === FIXED_POSITIONS.right ? "flex-row-reverse h-full" : "flex-row h-full";
    } else if (isFixedHorizontal) {
        hoverContainerClasses += fixedPos === FIXED_POSITIONS.bottom
            ? "flex-col-reverse items-start w-full"
            : "flex-col items-start w-full";
    } else if (isMobileMode) {
        hoverContainerClasses += "flex-col-reverse items-center";
    } else {
        hoverContainerClasses += `flex-col ${isRight ? 'items-end' : 'items-start'}`;
    }

    // 2. Botón (Widget): compacto en vista contraída (solo logo + avatar)
    let widgetClasses = "theme-surface theme-border sidebar-glass relative z-20 sidebar-widget-shell overflow-visible ";
    if (isFixedVertical) {
        const edgeBorder = fixedPos === FIXED_POSITIONS.right ? 'border-l' : 'border-r';
        widgetClasses += showCollapsedWidget
            ? `flex flex-col items-center justify-center py-4 w-[4.25rem] h-full ${edgeBorder} rounded-none`
            : `flex flex-col items-center py-5 w-[5.25rem] h-full ${edgeBorder} rounded-none`;
    } else if (isFixedHorizontal) {
        const edgeBorder = fixedPos === FIXED_POSITIONS.bottom ? 'border-t' : 'border-b';
        widgetClasses += showCollapsedWidget
            ? `inline-flex flex-row items-center h-[3.75rem] px-3 gap-2 w-full ${edgeBorder} rounded-none shadow-none`
            : `inline-flex flex-row items-center h-16 px-4 sm:px-5 w-full ${edgeBorder} rounded-none shadow-none`;
    } else {
        widgetClasses += showCollapsedWidget
            ? "inline-flex items-center py-1.5 px-1.5 gap-1.5 rounded-full border shadow-[0_8px_30px_rgba(0,0,0,0.12)]"
            : "inline-flex items-center py-2 px-3 sm:px-4 rounded-full border shadow-[0_8px_30px_rgba(0,0,0,0.12)]";
    }

    const widgetShellStyle = {
        transitionProperty: 'padding, gap, width, opacity, transform',
        transitionDuration: `${WIDGET_MS}ms`,
        transitionTimingFunction: EASE_SMOOTH,
    };

    const menuTransitionStyle = {
        transitionDuration: `${isMenuOpen ? MENU_OPEN_MS : MENU_CLOSE_MS}ms`,
        transitionTimingFunction: EASE_SMOOTH,
    };

    const sidebarMenuLayoutFlags = { isFixedVertical, isFixedHorizontal, isMobileMode, fixedPos };

    const menuShellBase = (isVisible, isOpen) =>
        `floating-menu border shadow-2xl theme-surface theme-border sidebar-glass relative z-10 flex-shrink-0 sidebar-menu-shell sidebar-hamburger-menu ${isVisible ? 'pointer-events-auto' : 'pointer-events-none'} ${isOpen ? 'sidebar-hamburger-menu--open' : ''} `;

    const menuClasses = menuShellBase(isMenuVisible, isMenuOpen)
        + buildSidebarMenuLayoutClasses({ ...sidebarMenuLayoutFlags, isOpen: isMenuOpen, isDrawer: mobileDrawerMode });

    const profileMenuClasses = menuShellBase(isProfileMenuVisible, isProfileMenuOpen)
        + buildSidebarMenuLayoutClasses({ ...sidebarMenuLayoutFlags, isOpen: isProfileMenuOpen, isDrawer: mobileDrawerMode });

    const profileMenuTransitionStyle = {
        transitionDuration: `${isProfileMenuOpen ? MENU_OPEN_MS : MENU_CLOSE_MS}ms`,
        transitionTimingFunction: EASE_SMOOTH,
    };

    const logoGlowClass = "drop-shadow-[0_0_12px_color-mix(in_srgb,var(--color-primario)_60%,transparent)]";
    const logoSizeClass = showCollapsedWidget
        ? `${isFixedVertical ? 'w-11 h-11' : 'w-12 h-12'} ${logoGlowClass}`
        : 'w-7 h-7 drop-shadow-sm';

    const innerFlexClass = widgetOrientation === 'vertical'
        ? 'flex-col w-full'
        : 'flex-row';

    const innerGapClass = showCollapsedWidget
        ? (widgetOrientation === 'vertical' ? 'gap-3' : 'gap-1.5')
        : (widgetOrientation === 'vertical' ? 'gap-5 py-2' : 'gap-1 sm:gap-2');

    const closeMobileMenu = () => {
        if (isMobileMode) closeMenu();
    };

    return (
        <>
            {mobileDrawerMode && (
                <button
                    type="button"
                    aria-label="Cerrar menú"
                    className={`gelia-sidebar-backdrop ${isMenuVisible ? 'gelia-sidebar-backdrop--visible' : ''}`}
                    onClick={closeMobileMenu}
                    tabIndex={isMenuVisible ? 0 : -1}
                />
            )}
            <nav className={navClasses}>
                <div
                    className={hoverContainerClasses}
                    onMouseEnter={handleHoverEnter}
                    onMouseLeave={handleHoverLeave}
                >
                    <div
                        className={`${widgetClasses} ${mobileDrawerMode ? 'gelia-sidebar-widget--mobile-hidden' : ''}`}
                        style={widgetShellStyle}
                        aria-hidden={mobileDrawerMode}
                    >
                        <div className={`flex items-center ${innerFlexClass} ${innerGapClass} w-full ${isFixedVertical ? 'justify-center' : ''} ${isFixedHorizontal ? 'justify-start' : ''}`}>
                            <div
                                className={prefixGroupClass(showSecondaryControls, widgetOrientation)}
                                aria-hidden={!showSecondaryControls}
                            >
                                <button
                                    onClick={handleMenuToggle}
                                    onMouseEnter={handleHamburgerHover}
                                    className={`relative flex items-center justify-center rounded-full theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none shrink-0 ${widgetOrientation === 'vertical' ? 'w-11 h-11' : 'w-10 h-10 mx-0.5'}`}
                                    aria-label={isMenuOpen ? 'Cerrar menú' : 'Abrir menú'}
                                >
                                    <Menu className={`absolute w-5 h-5 sidebar-hamburger-icon ${isMenuOpen ? 'sidebar-hamburger-icon--hidden' : 'sidebar-hamburger-icon--visible'}`} />
                                    <X className={`absolute w-5 h-5 sidebar-hamburger-icon ${isMenuOpen ? 'sidebar-hamburger-icon--visible' : 'sidebar-hamburger-icon--hidden'}`} />
                                </button>

                                <button
                                    onClick={toggleTheme}
                                    className="active:scale-90 hover:scale-110 theme-text-muted hover:theme-text-main outline-none shrink-0"
                                >
                                    {isDarkMode ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
                                </button>

                                <button
                                    onClick={() => window.history.back()}
                                    className="hover:scale-110 theme-text-muted hover:theme-text-main outline-none shrink-0"
                                >
                                    <ArrowLeft className="w-5 h-5" />
                                </button>
                            </div>

                            <Link
                                href={route('dashboard')}
                                className={`relative overflow-visible hover:scale-110 active:scale-90 outline-none flex items-center justify-center shrink-0 sidebar-logo-link ${!showCollapsedWidget && widgetOrientation === 'horizontal' ? 'mx-1 sm:mx-2' : ''}`}
                                aria-label={showCollapsedWidget && unreadCount > 0 ? `Panel principal, ${unreadCount} notificaciones nuevas` : 'Panel principal'}
                            >
                                <GeliaLogo variant="sparkle" className={`sidebar-logo-mark ${logoSizeClass}`} />
                                {showCollapsedWidget && (
                                    <NotificationCountBadge
                                        count={unreadCount}
                                        className="-top-0.5 -right-0.5 animate-pulse"
                                    />
                                )}
                            </Link>

                            <div
                                className={suffixGroupClass(showSecondaryControls, widgetOrientation)}
                                aria-hidden={!showSecondaryControls}
                            >
                                <NotificationBell
                                    notifications={auth?.notificaciones || []}
                                    iconButtonClassName={SIDEBAR_WIDGET_ICON_BTN_CLASS}
                                />
                                <MensajeriaWidget iconButtonClassName={SIDEBAR_WIDGET_ICON_BTN_CLASS} />
                            </div>

                            {renderProfileAvatarMenu(showCollapsedWidget)}
                        </div>
                    </div>

                    <div
                        ref={profileMenuRef}
                        role="menu"
                        aria-hidden={!isProfileMenuVisible}
                        className={profileMenuClasses}
                        style={profileMenuTransitionStyle}
                    >
                        <div className="sidebar-menu-grid-inner overflow-hidden min-h-0">
                            <div className={`sidebar-menu-content p-5 flex flex-col space-y-3 overflow-y-auto custom-scrollbar ${isFixedVertical ? 'w-[300px] h-full pt-10' : 'max-h-[85vh]'}`}>
                                <span
                                    className="text-[11px] font-black tracking-[0.3em] px-5 mb-1 opacity-60 uppercase italic"
                                    style={{ color: 'var(--color-primario)' }}
                                >
                                    PERFIL_
                                </span>
                                <div className="flex flex-col space-y-2 px-2">
                                    {PROFILE_MENU_ITEMS.map((item) => {
                                        const IconComponent = item.icon;
                                        const isActive = isRouteActive(item.path);
                                        return (
                                            <Link
                                                key={item.id}
                                                href={profileMenuHref(item)}
                                                role="menuitem"
                                                onClick={closeProfileMenu}
                                                className={`flex items-center w-full px-5 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all outline-none ${isActive ? 'bg-[var(--color-primario)] text-white shadow-md' : 'theme-element theme-text-muted hover:theme-text-main hover:shadow-sm border border-transparent hover:border-[var(--color-primario)]'}`}
                                            >
                                                <IconComponent className="w-3.5 h-3.5 mr-4 shrink-0" />
                                                {item.label}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className={menuClasses} style={menuTransitionStyle}>
                        <div className="sidebar-menu-grid-inner overflow-hidden min-h-0">
                            <div className={`sidebar-menu-content p-5 flex flex-col space-y-3 overflow-y-auto custom-scrollbar ${isFixedVertical ? 'w-[300px] h-full pt-10' : 'max-h-[85vh]'}`}>
                            <span className="text-[11px] font-black tracking-[0.3em] px-5 mb-1 opacity-60 uppercase italic" style={{ color: 'var(--color-primario)' }}>
                                ACCESOS_
                            </span>

                            <Link
                                href={route('dashboard')}
                                className={linkBaseClass + (isRouteActive('/dashboard') ? linkActiveClass : linkInactiveClass)}
                                onMouseEnter={(e) => { if (!isRouteActive('/dashboard')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                                onMouseLeave={(e) => { if (!isRouteActive('/dashboard')) e.currentTarget.style.borderColor = 'transparent' }}
                            >
                                <div className="flex items-center">
                                    <LayoutDashboard className="w-4 h-4 mr-4" style={{ color: isRouteActive('/dashboard') ? '#ffffff' : 'var(--color-primario)' }} />
                                    <span className="text-xs font-black uppercase italic tracking-tighter justify-between">Panel Principal_</span>
                                </div>
                            </Link>

                            <Link
                                href={route('mensajeria.index')}
                                className={linkBaseClass + (isRouteActive('/mensajeria') ? linkActiveClass : linkInactiveClass)}
                                onMouseEnter={(e) => { if (!isRouteActive('/mensajeria')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                                onMouseLeave={(e) => { if (!isRouteActive('/mensajeria')) e.currentTarget.style.borderColor = 'transparent' }}
                            >
                                <div className="flex items-center">
                                    <MessageCircle className="w-4 h-4 mr-4" style={{ color: isRouteActive('/mensajeria') ? '#ffffff' : 'var(--color-primario)' }} />
                                    <span className="text-xs font-black uppercase italic tracking-tighter justify-between">Mensajería_</span>
                                </div>
                            </Link>

                            {can('solicitudes.ver_listado') && (
                                <Link
                                    href={route('solicitudes.index')}
                                    className={linkBaseClass + (isRouteActive('/solicitudes') ? linkActiveClass : linkInactiveClass)}
                                    onMouseEnter={(e) => { if (!isRouteActive('/solicitudes')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                                    onMouseLeave={(e) => { if (!isRouteActive('/solicitudes')) e.currentTarget.style.borderColor = 'transparent' }}
                                >
                                    <Briefcase className="w-4 h-4 mr-4" />
                                    <span className="text-xs font-black uppercase italic tracking-tighter">Solicitudes_</span>
                                </Link>
                            )}

                            {can('solicitudes.exportar') && (
                                <Link
                                    href={route('reportes.solicitudes.index')}
                                    className={linkBaseClass + (isRouteActive('/reportes/solicitudes') ? linkActiveClass : linkInactiveClass)}
                                    onMouseEnter={(e) => { if (!isRouteActive('/reportes/solicitudes')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                                    onMouseLeave={(e) => { if (!isRouteActive('/reportes/solicitudes')) e.currentTarget.style.borderColor = 'transparent' }}
                                >
                                    <BarChart3 className="w-4 h-4 mr-4" />
                                    <span className="text-xs font-black uppercase italic tracking-tighter">Reportes_</span>
                                </Link>
                            )}

                            {can('facturas.ver_listado') && (
                                <Link
                                    href={route('facturas.index')}
                                    className={linkBaseClass + (isRouteActive('/facturas') ? linkActiveClass : linkInactiveClass)}
                                    onMouseEnter={(e) => { if (!isRouteActive('/facturas')) e.currentTarget.style.borderColor = '#0d9488' }}
                                    onMouseLeave={(e) => { if (!isRouteActive('/facturas')) e.currentTarget.style.borderColor = 'transparent' }}
                                >
                                    <Receipt className="w-4 h-4 mr-4" style={{ color: isRouteActive('/facturas') ? '#ffffff' : '#0d9488' }} />
                                    <span className="text-xs font-black uppercase italic tracking-tighter">Facturas_</span>
                                </Link>
                            )}

                            {can('cancelaciones_cotizaciones.ver_listado') && (
                                <Link
                                    href={route('cancelaciones_cotizaciones.index')}
                                    className={linkBaseClass + (isRouteActive('/cancelaciones-cotizaciones') ? linkActiveClass : linkInactiveClass)}
                                    onMouseEnter={(e) => { if (!isRouteActive('/cancelaciones-cotizaciones')) e.currentTarget.style.borderColor = '#f97316' }}
                                    onMouseLeave={(e) => { if (!isRouteActive('/cancelaciones-cotizaciones')) e.currentTarget.style.borderColor = 'transparent' }}
                                >
                                    <Ban className="w-4 h-4 mr-4" style={{ color: isRouteActive('/cancelaciones-cotizaciones') ? '#ffffff' : '#f97316' }} />
                                    <span className="text-xs font-black uppercase italic tracking-tighter">Cancel. y Cotiz._</span>
                                </Link>
                            )}

                            {can('mis_clientes.gestionar') && (
                                <Link
                                    href={route('mis_clientes.index')}
                                    className={linkBaseClass + (isRouteActive('/mis-clientes') ? linkActiveClass : linkInactiveClass)}
                                    onMouseEnter={(e) => { if (!isRouteActive('/mis-clientes')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                                    onMouseLeave={(e) => { if (!isRouteActive('/mis-clientes')) e.currentTarget.style.borderColor = 'transparent' }}
                                >
                                    <div className="flex items-center">
                                        <Users className="w-4 h-4 mr-4" style={{ color: isRouteActive('/mis-clientes') ? '#ffffff' : 'var(--color-primario)' }} />
                                        <span className="text-xs font-black uppercase italic tracking-tighter justify-between">Mis Clientes_</span>
                                    </div>
                                </Link>
                            )}

                            {can('activos.ver') && (
                                <Link
                                    href={route('activos.index')}
                                    className={linkBaseClass + (isRouteActive('/activos') ? linkActiveClass : linkInactiveClass)}
                                    onMouseEnter={(e) => { if (!isRouteActive('/activos')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                                    onMouseLeave={(e) => { if (!isRouteActive('/activos')) e.currentTarget.style.borderColor = 'transparent' }}
                                >
                                    <div className="flex items-center">
                                        <Package className="w-4 h-4 mr-4" style={{ color: isRouteActive('/activos') ? '#ffffff' : 'var(--color-primario)' }} />
                                        <span className="text-xs font-black uppercase italic tracking-tighter justify-between">Control de Activos_</span>
                                    </div>
                                </Link>
                            )}

                            {can('rh.ver') && (
                                <Link
                                    href={route('rh.index')}
                                    className={linkBaseClass + (isRouteActive('/rh') ? linkActiveClass : linkInactiveClass)}
                                    onMouseEnter={(e) => { if (!isRouteActive('/rh')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                                    onMouseLeave={(e) => { if (!isRouteActive('/rh')) e.currentTarget.style.borderColor = 'transparent' }}
                                >
                                    <div className="flex items-center">
                                        <Briefcase className="w-4 h-4 mr-4" style={{ color: isRouteActive('/rh') ? '#ffffff' : 'var(--color-primario)' }} />
                                        <span className="text-xs font-black uppercase italic tracking-tighter justify-between">Recursos Humanos_</span>
                                    </div>
                                </Link>
                            )}

                            {can('entregas.cotizar') && (
                                <Link
                                    href={route('entregas.index')}
                                    className={linkBaseClass + (isRouteActive('/entregas') ? linkActiveClass : linkInactiveClass)}
                                    onMouseEnter={(e) => { if (!isRouteActive('/entregas')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                                    onMouseLeave={(e) => { if (!isRouteActive('/entregas')) e.currentTarget.style.borderColor = 'transparent' }}
                                >
                                    <div className="flex items-center">
                                        <Map className="w-4 h-4 mr-4" style={{ color: isRouteActive('/entregas') ? '#ffffff' : 'var(--color-primario)' }} />
                                        <span className="text-xs font-black uppercase italic tracking-tighter justify-between">Cotizar Entregas</span>
                                    </div>
                                </Link>
                            )}

                            {showOperacionesMenu && (
                                <div className="flex flex-col">
                                    <div className="pt-3 pb-1">
                                        <span className="text-[11px] font-black tracking-[0.3em] px-5 opacity-60 uppercase italic" style={{ color: 'var(--color-primario)' }}>
                                            FUNCIONES OPERATIVAS_
                                        </span>
                                    </div>

                                    {can('listados.ver') && (
                                        <Link
                                            href={route('listados.index')}
                                            className={linkBaseClass + (isRouteActive('/listados') ? linkActiveClass : linkInactiveClass)}
                                            onMouseEnter={(e) => { if (!isRouteActive('/listados')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                                            onMouseLeave={(e) => { if (!isRouteActive('/listados')) e.currentTarget.style.borderColor = 'transparent' }}
                                        >
                                            <div className="flex items-center">
                                                <FileText className="w-4 h-4 mr-4" style={{ color: isRouteActive('/listados') ? '#ffffff' : 'var(--color-primario)' }} />
                                                <span className="text-xs font-black uppercase italic tracking-tighter justify-between">Listados_</span>
                                            </div>
                                        </Link>
                                    )}
                                </div>
                            )}

                            {showAdminMenu && (
                                <Link
                                    href={adminPanelHref()}
                                    className={linkBaseClass + (isAdminActive ? linkActiveClass : linkInactiveClass)}
                                    onMouseEnter={(e) => { if (!isAdminActive) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                                    onMouseLeave={(e) => { if (!isAdminActive) e.currentTarget.style.borderColor = 'transparent' }}
                                >
                                    <div className="flex items-center">
                                        <Settings className="w-4 h-4 mr-4" style={{ color: isAdminActive ? '#ffffff' : 'var(--color-primario)' }} />
                                        <span className="text-xs font-black uppercase italic tracking-tighter">Administración_</span>
                                    </div>
                                </Link>
                            )}

                            <div className="mt-auto pt-6 pb-2">
                                <button
                                    onClick={() => {
                                        localStorage.clear();
                                        post(route('logout'));
                                    }}
                                    className="flex items-center w-full px-6 py-4 rounded-3xl transition-all theme-element border border-transparent hover:border-red-500 hover:shadow-md outline-none group">
                                    <LogOut className="w-4 h-4 mr-4 text-red-500 group-hover:text-red-600 transition-colors" />
                                    <span className="text-xs font-black uppercase italic tracking-widest text-red-500 group-hover:text-red-600 transition-colors">Cerrar Sesión_</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </nav>

            <style>{`
                @keyframes sidebarMount {
                    from { opacity: 0; transform: translate3d(0, 10px, 0); }
                    to { opacity: 1; transform: translate3d(0, 0, 0); }
                }
                .sidebar-mount {
                    animation: sidebarMount 420ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
                }
                .sidebar-widget-shell {
                    will-change: padding, gap, width, transform;
                    backface-visibility: hidden;
                }
                .sidebar-widget-reveal {
                    transition-property: max-width, max-height, opacity, gap, padding, margin, border-color;
                    transition-duration: ${WIDGET_MS}ms;
                    transition-timing-function: ${EASE_SMOOTH};
                    pointer-events: auto;
                }
                .sidebar-widget-icons {
                    box-sizing: border-box;
                }
                .sidebar-widget-icon-btn {
                    width: 2.75rem;
                    height: 2.75rem;
                    min-width: 2.75rem;
                    min-height: 2.75rem;
                    padding: 0;
                    margin: 0;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }
                .sidebar-widget-icon-btn svg {
                    display: block;
                    flex-shrink: 0;
                }
                .sidebar-logo-link {
                    transition: transform ${WIDGET_MS}ms ${EASE_SMOOTH};
                }
                .sidebar-logo-mark {
                    transition: width ${WIDGET_MS}ms ${EASE_SMOOTH}, height ${WIDGET_MS}ms ${EASE_SMOOTH}, filter ${WIDGET_MS}ms ${EASE_SMOOTH};
                    will-change: width, height, transform;
                }
                .sidebar-menu-shell {
                    overflow: hidden;
                    will-change: transform, opacity, width, grid-template-rows;
                    backface-visibility: hidden;
                }
                .sidebar-menu-float {
                    display: grid;
                    grid-template-rows: 0fr;
                    opacity: 0;
                    transform: translate3d(0, -6px, 0) scale(0.985);
                    transition-property: grid-template-rows, opacity, transform, margin;
                }
                .sidebar-menu-float--open {
                    grid-template-rows: 1fr;
                    opacity: 1;
                    transform: translate3d(0, 0, 0) scale(1);
                }
                .sidebar-menu-fixed {
                    width: 0;
                    opacity: 0;
                    transform: translate3d(-8px, 0, 0);
                    transition-property: width, opacity, transform;
                }
                .sidebar-menu-fixed--open {
                    width: 300px;
                    opacity: 1;
                    transform: translate3d(0, 0, 0);
                }
                .sidebar-menu-fixed-right {
                    width: 0;
                    opacity: 0;
                    transform: translate3d(8px, 0, 0);
                    transition-property: width, opacity, transform;
                }
                .sidebar-menu-fixed-right--open {
                    width: 300px;
                    opacity: 1;
                    transform: translate3d(0, 0, 0);
                }
                .sidebar-hamburger-icon {
                    transition: opacity ${MENU_OPEN_MS}ms ${EASE_SMOOTH}, transform ${MENU_OPEN_MS}ms ${EASE_SMOOTH};
                    will-change: opacity, transform;
                }
                .sidebar-hamburger-icon--visible {
                    opacity: 1;
                    transform: rotate(0deg) scale(1);
                }
                .sidebar-hamburger-icon--hidden {
                    opacity: 0;
                    transform: rotate(-90deg) scale(0.72);
                    pointer-events: none;
                }
                .sidebar-hamburger-menu .sidebar-menu-content {
                    opacity: 0;
                    transform: translate3d(0, -12px, 0);
                    transition: opacity ${MENU_OPEN_MS}ms ${EASE_SMOOTH}, transform ${MENU_OPEN_MS}ms ${EASE_SMOOTH};
                }
                .sidebar-hamburger-menu--open .sidebar-menu-content {
                    opacity: 1;
                    transform: translate3d(0, 0, 0);
                    transition-delay: 90ms;
                }
                .sidebar-hamburger-menu:not(.sidebar-hamburger-menu--open) .sidebar-menu-content {
                    transition-delay: 0ms;
                    transition-duration: ${MENU_CLOSE_MS}ms;
                }
            `}</style>
        </>
    );
}
