import React, { useState, useRef, useEffect, useLayoutEffect, useCallback } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import {
    Menu, X, Moon, Sun, ArrowLeft,
    Settings2, LogOut,
    User, Sparkles,
} from 'lucide-react';
import { hasAnyAdminModuleAccess } from '../config/adminModules';

import GeliaLogo from './GeliaLogo';
import SidebarNavMenu from './SidebarNavMenu';
import SidebarNavLeafLink from './SidebarNavLeafLink';

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

/** Separación exterior widget ↔ panel flotante (mitad del mt-4 / 1rem anterior = 0.5rem) */
const SIDEBAR_PANEL_EXTERIOR_GAP = 'gap-2';

/** Ancho del shell flotante: 0 en layout cuando está cerrado (el árbol interior no se toca) */
function floatShellLayoutWidth(shellExpanded, { mobile = false } = {}) {
    if (shellExpanded) {
        const capped = 'min(var(--gelia-sidebar-menu-width),calc(100vw-2rem))';
        return mobile
            ? `w-[${capped}] max-w-[${capped}] min-w-[${capped}] shrink-0`
            : `w-[var(--gelia-sidebar-menu-width)] max-w-[${capped}] min-w-[var(--gelia-sidebar-menu-width)] shrink-0`;
    }
    return 'w-0 min-w-0 max-w-0 shrink-0';
}

/** Ancho del panel perfil: nunca min-w-0 (evita colapso a ~2px en flex-col) */
function profileShellLayoutWidth(shellExpanded, { mobile = false } = {}) {
    if (shellExpanded) {
        return mobile ? 'w-[90vw] max-w-[320px] min-w-[280px] shrink-0' : 'w-[var(--gelia-sidebar-menu-width)] max-w-[var(--gelia-sidebar-menu-width)] min-w-[var(--gelia-sidebar-menu-width)] shrink-0';
    }
    return 'w-0 min-w-0 max-w-0 shrink-0';
}

/** Clases de posición/animación compartidas entre menú principal y menú de perfil */
function buildSidebarMenuLayoutClasses({ isFixedVertical, isFixedHorizontal, isMobileMode, fixedPos, isOpen, isShellExpanded, isDrawer = false, isMobileBottomFloat = false }) {
    const shellExpanded = isShellExpanded ?? isOpen;
    if (isMobileMode && isDrawer) {
        return ` gelia-sidebar-drawer h-full ml-0 rounded-none border-r theme-border ${isOpen ? '' : ''}`;
    }
    if (isFixedVertical) {
        const menuFixedClass = fixedPos === FIXED_POSITIONS.right ? 'sidebar-menu-fixed-right' : 'sidebar-menu-fixed';
        const menuRounding = fixedPos === FIXED_POSITIONS.right
            ? 'rounded-l-[2.5rem] rounded-r-none border-r-0'
            : 'rounded-r-[2.5rem] rounded-l-none border-l-0';
        return ` h-auto max-h-[100dvh] shrink-0 ml-0 ${menuRounding} ${menuFixedClass} ${isOpen ? `${menuFixedClass}--open` : ''}`;
    }
    const floatOpenClass = shellExpanded ? 'sidebar-menu-float--open' : '';

    if (isFixedHorizontal) {
        return ` rounded-[2rem] max-w-[90vw] ${floatShellLayoutWidth(shellExpanded)} sidebar-menu-float ${floatOpenClass}`;
    }
    if (isMobileMode) {
        const mobileBottomClass = isMobileBottomFloat && !isDrawer ? ' gelia-sidebar-mobile-bottom-panel' : '';
        if (isMobileBottomFloat && !isDrawer && shellExpanded) {
            return ` rounded-[2rem] shrink-0 sidebar-menu-float gelia-sidebar-mobile-bottom-panel--positioned${mobileBottomClass} ${floatOpenClass}`;
        }
        return ` rounded-[2rem] ${floatShellLayoutWidth(shellExpanded, { mobile: true })} sidebar-menu-float${mobileBottomClass} ${floatOpenClass}`;
    }
    return ` rounded-[2.5rem] ${floatShellLayoutWidth(shellExpanded)} sidebar-menu-float ${floatOpenClass}`;
}

const MOBILE_BOTTOM_PANEL_BOTTOM_OFFSET = 'calc(env(safe-area-inset-bottom, 0px) + 5.75rem)';

function mobileBottomPanelOpenStyle(maxHeight) {
    return {
        position: 'fixed',
        left: '50%',
        right: 'auto',
        top: 'auto',
        bottom: MOBILE_BOTTOM_PANEL_BOTTOM_OFFSET,
        width: 'min(var(--gelia-sidebar-menu-width), calc(100vw - 2rem))',
        maxWidth: 'min(var(--gelia-sidebar-menu-width), calc(100vw - 2rem))',
        minWidth: 'min(var(--gelia-sidebar-menu-width), calc(100vw - 2rem))',
        maxHeight,
        height: 'auto',
        margin: 0,
        zIndex: 210,
        transform: 'translateX(-50%) translateY(0) scale(1)',
        opacity: 1,
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
    };
}

function applyMobileBottomPanelImportantStyles(el, maxHeight) {
    if (!el) return;
    const width = 'min(var(--gelia-sidebar-menu-width), calc(100vw - 2rem))';
    const entries = [
        ['position', 'fixed'],
        ['left', '50%'],
        ['right', 'auto'],
        ['top', 'auto'],
        ['bottom', MOBILE_BOTTOM_PANEL_BOTTOM_OFFSET],
        ['width', width],
        ['min-width', width],
        ['max-width', width],
        ['max-height', maxHeight],
        ['height', 'auto'],
        ['margin', '0'],
        ['z-index', '210'],
        ['transform', 'translateX(-50%) translateY(0) scale(1)'],
        ['opacity', '1'],
        ['display', 'flex'],
        ['flex-direction', 'column'],
        ['overflow', 'hidden'],
        ['grid-template-rows', 'unset'],
    ];
    entries.forEach(([prop, value]) => el.style.setProperty(prop, value, 'important'));
}

function clearMobileBottomPanelImportantStyles(el) {
    if (!el) return;
    [
        'position', 'left', 'right', 'top', 'bottom', 'width', 'min-width', 'max-width',
        'max-height', 'height', 'margin', 'z-index', 'transform', 'opacity', 'display',
        'flex-direction', 'overflow', 'grid-template-rows',
    ].forEach((prop) => el.style.removeProperty(prop));
}

/** Perfil: panel compacto (3 enlaces), nunca h-screen ni altura de viewport */
function buildProfileMenuLayoutClasses({ isMobileMode, isOpen, isShellExpanded, isDrawer = false, isMobileBottomFloat = false }) {
    const shellExpanded = isShellExpanded ?? isOpen;
    if (isMobileMode && isDrawer) {
        return ` gelia-sidebar-drawer h-auto ml-0 rounded-none border-r theme-border`;
    }
    const floatOpenClass = shellExpanded ? 'sidebar-menu-float--open' : '';
    const mobileBottomClass = isMobileBottomFloat && isMobileMode ? ' gelia-sidebar-mobile-bottom-panel' : '';
    return ` rounded-[2.5rem] ${profileShellLayoutWidth(shellExpanded)} sidebar-menu-float${mobileBottomClass} ${floatOpenClass}`;
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
    { id: 'perfil', label: 'Mi perfil', routeName: 'profile.index', path: '/perfil', icon: User },
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

export default function Sidebar({ isDarkMode, toggleTheme, user, permissions, layout = 'floating_left', sidebarMode = 'collapsed', fixedPosition = 'left', useMobileTopBar = false, isMobileViewport = false }) {
    const { url, props: { auth } } = usePage();

    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isMenuClosing, setIsMenuClosing] = useState(false);
    const [isHovered, setIsHovered] = useState(false);
    const [isProfileMenuOpen, setIsProfileMenuOpen] = useState(false);
    const [isProfileMenuClosing, setIsProfileMenuClosing] = useState(false);
    const isMobile = isMobileViewport;
    const closeTimerRef = useRef(null);
    const menuCloseTimerRef = useRef(null);
    const profileMenuCloseTimerRef = useRef(null);
    const profileMenuShellRef = useRef(null);
    const accessMenuShellRef = useRef(null);
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

    const effectiveLayout = isMobile ? 'mobile_bottom' : layout;
    const isFixed = effectiveLayout === 'fixed';
    const isRight = effectiveLayout === 'floating_right';
    const isMobileMode = effectiveLayout === 'mobile_bottom';
    const fixedPos = FIXED_POSITIONS[fixedPosition] ? fixedPosition : FIXED_POSITIONS.left;
    const isFixedVertical = isFixed && (fixedPos === FIXED_POSITIONS.left || fixedPos === FIXED_POSITIONS.right);
    const isFixedHorizontal = isFixed && (fixedPos === FIXED_POSITIONS.top || fixedPos === FIXED_POSITIONS.bottom);
    const widgetOrientation = isFixedVertical ? 'vertical' : 'horizontal';
    const isMenuVisible = isMenuOpen || isMenuClosing;
    const mobileDrawerMode = isMobileMode && useMobileTopBar;
    const pinExpanded = sidebarMode === SIDEBAR_MODES.expanded && (!isMobileMode || !mobileDrawerMode);
    const isWidgetExpanded = mobileDrawerMode
        ? true
        : isMobileMode
            ? true
            : (pinExpanded || isHovered || isMenuVisible || isProfileMenuVisible);
    const showCollapsedWidget = mobileDrawerMode
        ? false
        : !isMobileMode && !isWidgetExpanded;
    const showSecondaryControls = isWidgetExpanded;

    // --- LÓGICA DE PERMISOS BLINDADA ---
    const can = (permission) => {
        const isSuperAdmin = user?.roles?.includes('Super Admin');
        const hasPermission = permissions?.includes(permission);
        return isSuperAdmin || hasPermission;
    };

    const showAdminMenu = hasAnyAdminModuleAccess(can);

    const openMenu = () => {
        clearTimeout(closeTimerRef.current);
        clearTimeout(menuCloseTimerRef.current);
        clearTimeout(profileMenuCloseTimerRef.current);
        setIsProfileMenuClosing(false);
        setIsProfileMenuOpen(false);
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
        if (isMenuOpen) closeMenu();
        else openMenu();
    };

    const handleMobileLogoClick = (event) => {
        if (isMobileMode && !mobileDrawerMode) {
            event.preventDefault();
            handleMenuToggle();
        }
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
        clearTimeout(menuCloseTimerRef.current);
        setIsProfileMenuClosing(false);
        setIsMenuClosing(false);
        setIsMenuOpen(false);
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
                profileMenuShellRef.current?.contains(event.target) ||
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
                                className={`gelia-sidebar-avatar-initial leading-none select-none ${compact ? 'gelia-sidebar-avatar-initial--compact' : 'gelia-sidebar-avatar-initial--expanded'}`}
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

    const isFloatCornerLayout = !isFixed && !isMobileMode;

    // 1. Contenedor Base: solo ocupa el espacio del sidebar; nunca bloquear toda la pantalla
    let navClasses = 'fixed z-[200] flex pointer-events-none sidebar-mount ';
    if (isFloatCornerLayout) {
        navClasses += 'h-auto w-auto max-h-none overflow-visible ';
    } else if (isFixedVertical) {
        navClasses += 'h-screen w-auto max-h-[100dvh] overflow-visible ';
    } else if (isFixedHorizontal) {
        navClasses += 'h-auto w-full max-h-none overflow-visible ';
    } else {
        navClasses += 'h-auto w-full max-h-[100dvh] overflow-visible ';
    }
    if (mobileDrawerMode) {
        navClasses += 'gelia-sidebar-mount--mobile ';
    } else if (isMobileMode) {
        navClasses += 'gelia-sidebar-mount--mobile-bottom bottom-6 left-0 right-0 w-full flex-col-reverse items-center justify-end';
    } else if (isFixed) {
        if (fixedPos === FIXED_POSITIONS.right) navClasses += 'top-0 right-0 h-screen flex-row-reverse';
        else if (fixedPos === FIXED_POSITIONS.top) navClasses += 'top-0 left-0 right-0 w-full flex-col items-start';
        else if (fixedPos === FIXED_POSITIONS.bottom) navClasses += 'bottom-0 left-0 right-0 w-full flex-col-reverse items-start';
        else navClasses += 'top-0 left-0 h-screen flex-row';
    } else {
        navClasses += `top-6 flex-col ${isRight ? 'right-6 items-end' : 'left-6 items-start'}`;
    }

    const sidebarTimingStyle = {
        '--gelia-sidebar-widget-ms': `${WIDGET_MS}ms`,
        '--gelia-sidebar-menu-open-ms': `${MENU_OPEN_MS}ms`,
        '--gelia-sidebar-menu-close-ms': `${MENU_CLOSE_MS}ms`,
        '--gelia-sidebar-mount-ms': '420ms',
        '--gelia-sidebar-ease': EASE_SMOOTH,
    };

    let hoverContainerClasses = `gelia-sidebar-root gelia-sidebar-hover-cluster flex min-h-0 pointer-events-none ${SIDEBAR_PANEL_EXTERIOR_GAP} `;
    if (isFloatCornerLayout) {
        hoverContainerClasses += 'h-auto w-auto max-w-none self-start overflow-visible ';
    } else if (isFixedVertical) {
        hoverContainerClasses += 'h-full w-auto max-h-full overflow-visible ';
    } else if (isFixedHorizontal) {
        hoverContainerClasses += 'h-auto w-full overflow-visible ';
    } else {
        hoverContainerClasses += 'h-auto w-full max-h-full overflow-visible ';
    }
    if (isFixedVertical) {
        hoverContainerClasses += fixedPos === FIXED_POSITIONS.right ? 'flex-row-reverse h-full' : 'flex-row h-full';
    } else if (isFixedHorizontal) {
        hoverContainerClasses += fixedPos === FIXED_POSITIONS.bottom
            ? 'flex-col-reverse items-start w-full'
            : 'flex-col items-start w-full';
    } else if (isMobileMode) {
        hoverContainerClasses += mobileDrawerMode
            ? 'flex-col items-start w-auto max-w-none self-stretch'
            : 'flex-col-reverse items-center';
    } else {
        hoverContainerClasses += `flex-col ${isRight ? 'items-end' : 'items-start'}`;
    }

    // 2. Botón (Widget): compacto en vista contraída (solo logo + avatar)
    let widgetClasses = 'theme-surface theme-border sidebar-glass relative z-20 sidebar-widget-shell overflow-visible pointer-events-auto ';
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

    const sidebarMenuLayoutFlags = { isFixedVertical, isFixedHorizontal, isMobileMode, fixedPos, isMobileBottomFloat: isMobileMode && !mobileDrawerMode };

    const accessMenuShellBase = (isVisible, isOpen) =>
        `floating-menu border shadow-2xl theme-surface theme-border sidebar-glass relative z-10 flex-shrink-0 sidebar-menu-shell sidebar-hamburger-menu ${isOpen ? 'pointer-events-auto sidebar-hamburger-menu--open' : 'pointer-events-none'} `;

    const profileMenuShellBase = (isVisible, isOpen) =>
        `floating-menu border shadow-2xl theme-surface theme-border sidebar-glass relative z-10 shrink-0 sidebar-menu-shell sidebar-profile-menu ${isOpen ? 'pointer-events-auto sidebar-profile-menu--open sidebar-menu-float--open' : 'pointer-events-none'} `;

    const mobileBottomAccessOpen = isMobileMode && !mobileDrawerMode && isMenuVisible;
    const mobileBottomProfileOpen = isMobileMode && !mobileDrawerMode && isProfileMenuVisible;

    const menuClasses = accessMenuShellBase(isMenuVisible, isMenuOpen)
        + buildSidebarMenuLayoutClasses({
            ...sidebarMenuLayoutFlags,
            isOpen: isMenuOpen,
            isShellExpanded: isMenuVisible,
            isDrawer: mobileDrawerMode,
        })
        + ' gelia-sidebar-access-shell'
        + (mobileBottomAccessOpen ? ' gelia-sidebar-mobile-bottom-panel--open' : '');

    const profileMenuClasses = profileMenuShellBase(isProfileMenuVisible, isProfileMenuOpen)
        + buildProfileMenuLayoutClasses({
            isMobileMode: isMobileMode && !mobileDrawerMode,
            isOpen: isProfileMenuOpen,
            isShellExpanded: isProfileMenuVisible,
            isDrawer: false,
            isMobileBottomFloat: isMobileMode && !mobileDrawerMode,
        })
        + (mobileBottomProfileOpen ? ' gelia-sidebar-mobile-bottom-panel--open gelia-sidebar-mobile-bottom-panel--positioned' : '');

    const closeMobileBottomPanels = () => {
        closeMenu();
        closeProfileMenu();
    };

    const mobileBottomPanelVisible = isMobileMode && !mobileDrawerMode && (isMenuVisible || isProfileMenuVisible);
    const mobileBottomAccessStyle = mobileBottomAccessOpen
        ? mobileBottomPanelOpenStyle('min(72dvh, calc(100dvh - 9rem))')
        : undefined;
    const mobileBottomProfileStyle = mobileBottomProfileOpen
        ? mobileBottomPanelOpenStyle('min(40dvh, calc(100dvh - 12rem))')
        : undefined;

    useLayoutEffect(() => {
        if (!isMobileMode || mobileDrawerMode) return undefined;
        const accessEl = accessMenuShellRef.current;
        const profileEl = profileMenuShellRef.current;

        if (mobileBottomAccessOpen) {
            applyMobileBottomPanelImportantStyles(accessEl, 'min(72dvh, calc(100dvh - 9rem))');
        } else {
            clearMobileBottomPanelImportantStyles(accessEl);
        }

        if (mobileBottomProfileOpen) {
            applyMobileBottomPanelImportantStyles(profileEl, 'min(40dvh, calc(100dvh - 12rem))');
        } else {
            clearMobileBottomPanelImportantStyles(profileEl);
        }

        return () => {
            clearMobileBottomPanelImportantStyles(accessEl);
            clearMobileBottomPanelImportantStyles(profileEl);
        };
    }, [isMobileMode, mobileDrawerMode, mobileBottomAccessOpen, mobileBottomProfileOpen]);

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
            {(mobileDrawerMode || mobileBottomPanelVisible || (!isMobileMode && isMenuVisible)) && (
                <button
                    type="button"
                    aria-label="Cerrar menú"
                    className={`gelia-sidebar-backdrop ${
                        (mobileDrawerMode ? isMenuVisible : mobileBottomPanelVisible || (!isMobileMode && isMenuVisible))
                            ? 'gelia-sidebar-backdrop--visible'
                            : ''
                    }`}
                    onClick={mobileDrawerMode ? closeMobileMenu : (isMobileMode ? closeMobileBottomPanels : closeMenu)}
                    tabIndex={(mobileDrawerMode ? isMenuVisible : mobileBottomPanelVisible || (!isMobileMode && isMenuVisible)) ? 0 : -1}
                />
            )}
            <nav className={navClasses}>
                <div
                    className={hoverContainerClasses}
                    style={sidebarTimingStyle}
                    onMouseEnter={handleHoverEnter}
                    onMouseLeave={handleHoverLeave}
                >
                    <div
                        className={`${widgetClasses} ${mobileDrawerMode ? 'gelia-sidebar-widget--mobile-hidden' : ''}`}
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
                                onClick={handleMobileLogoClick}
                                className={`relative overflow-visible hover:scale-110 active:scale-90 outline-none flex items-center justify-center shrink-0 sidebar-logo-link ${!showCollapsedWidget && widgetOrientation === 'horizontal' ? 'mx-1 sm:mx-2' : ''}`}
                                aria-label={isMobileMode && !mobileDrawerMode ? (isMenuOpen ? 'Cerrar menú' : 'Abrir menú') : (showCollapsedWidget && unreadCount > 0 ? `Panel principal, ${unreadCount} notificaciones nuevas` : 'Panel principal')}
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
                        ref={profileMenuShellRef}
                        role="menu"
                        aria-hidden={!isProfileMenuVisible}
                        className={profileMenuClasses}
                        style={mobileBottomProfileStyle}
                    >
                        <div className="sidebar-menu-grid-inner overflow-hidden min-h-0 h-auto shrink-0">
                            <div
                                className={`sidebar-menu-content gelia-sidebar-profile-panel p-5 flex flex-col min-h-0 h-auto shrink-0 overflow-visible w-full max-w-full`}
                            >
                                <span className="gelia-sidebar-nav-header px-4 mb-1">
                                    PERFIL_
                                </span>
                                <nav className="gelia-sidebar-profile-links flex flex-col gap-0.5 px-2 min-w-0" aria-label="Perfil">
                                    {PROFILE_MENU_ITEMS.map((item) => {
                                        const IconComponent = item.icon;
                                        const isActive = isRouteActive(item.path);
                                        return (
                                            <SidebarNavLeafLink
                                                key={item.id}
                                                href={profileMenuHref(item)}
                                                active={isActive}
                                                onClick={closeProfileMenu}
                                                icon={IconComponent}
                                                label={item.label}
                                                paddingClass="pl-10"
                                                role="menuitem"
                                            />
                                        );
                                    })}
                                </nav>
                            </div>
                        </div>
                    </div>

                    <div
                        ref={accessMenuShellRef}
                        className={menuClasses}
                        style={mobileBottomAccessStyle}
                    >
                        <div className="sidebar-menu-grid-inner overflow-hidden min-h-0 h-auto">
                            <div
                                className={`sidebar-menu-content gelia-sidebar-access-panel p-5 flex flex-col min-h-0 max-h-full overflow-hidden h-auto w-full max-w-full ${isFixedVertical ? 'pt-10' : ''}`}
                            >
                                <div className="gelia-sidebar-access-scroll flex-1 min-h-0 overflow-y-auto overflow-x-hidden custom-scrollbar">
                                    <SidebarNavMenu
                                        url={url}
                                        can={can}
                                        showAdminMenu={showAdminMenu}
                                        onNavigate={closeMobileMenu}
                                    />
                                </div>

                                <div className="gelia-sidebar-access-footer shrink-0 pt-6 pb-2 px-2">
                                    <button
                                        onClick={() => {
                                            localStorage.clear();
                                            post(route('logout'));
                                        }}
                                        className="flex items-center w-full px-6 py-4 rounded-3xl transition-all theme-element border border-transparent hover:border-red-500 hover:shadow-md outline-none group"
                                    >
                                        <LogOut className="w-4 h-4 mr-4 text-red-500 group-hover:text-red-600 transition-colors" />
                                        <span className="gelia-sidebar-access-footer-label text-red-500 group-hover:text-red-600 transition-colors">
                                            Cerrar Sesión_
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>
        </>
    );
}
