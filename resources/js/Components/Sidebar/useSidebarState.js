import { useCallback, useEffect, useRef, useState } from 'react';

const SIDEBAR_MODE_STORAGE_KEY = 'theme_sidebar_mode';
/** Fallback si no llega transitionend (debe ≥ --gelia-pro-duration). */
const ANIM_FALLBACK_MS = 450;
/** Morph card → icono tras el clip. Debe cubrir --gelia-pro-settle-duration. */
const SETTLE_MS = 260;

/** Contraído asentado: no unmount mid-`collapsing`/`settling`. */
export function resolveStructuralCollapsed(mode, anim) {
    return mode === 'collapsed' && anim !== 'collapsing' && anim !== 'settling';
}

function prefersReducedMotion() {
    try {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch {
        return false;
    }
}

/**
 * Estado del sidebar profesional: expand/collapse, drawer móvil, Escape y foco.
 *
 * `collapsed` = modo rail (inmediato, drive CSS width).
 * `structuralCollapsed` = contraído asentado (tras anim); evita unmount mid-transition.
 * `anim` = 'collapsing' | 'settling' | 'expanding' | null → data-anim en el aside.
 */
export default function useSidebarState({
    initialMode = 'expanded',
    isMobile = false,
    onModePersist,
} = {}) {
    const [mode, setMode] = useState(() => (
        initialMode === 'collapsed' ? 'collapsed' : 'expanded'
    ));
    const [anim, setAnim] = useState(null);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const drawerTriggerRef = useRef(null);
    const drawerPanelRef = useRef(null);
    const userMenuRef = useRef(null);
    const userButtonRef = useRef(null);

    const animTimerRef = useRef(null);
    const animCleanupRef = useRef(null);
    const modeRef = useRef(mode);
    modeRef.current = mode;

    const collapsed = !isMobile && mode === 'collapsed';
    const structuralCollapsed = !isMobile && resolveStructuralCollapsed(mode, anim);

    const clearAnimListeners = useCallback(() => {
        if (animTimerRef.current) {
            clearTimeout(animTimerRef.current);
            animTimerRef.current = null;
        }
        if (animCleanupRef.current) {
            animCleanupRef.current();
            animCleanupRef.current = null;
        }
    }, []);

    const finishAnim = useCallback(() => {
        clearAnimListeners();
        setAnim(null);
    }, [clearAnimListeners]);

    const beginSettling = useCallback(() => {
        clearAnimListeners();
        setAnim('settling');
        animTimerRef.current = setTimeout(finishAnim, SETTLE_MS);
    }, [clearAnimListeners, finishAnim]);

    const bindWidthTransitionEnd = useCallback((sidebar, { settle = false } = {}) => {
        clearAnimListeners();
        if (!sidebar) {
            finishAnim();
            return;
        }

        const complete = settle ? beginSettling : finishAnim;
        const onEnd = (event) => {
            if (event.target !== sidebar || event.propertyName !== 'width') return;
            complete();
        };

        sidebar.addEventListener('transitionend', onEnd);
        animCleanupRef.current = () => {
            sidebar.removeEventListener('transitionend', onEnd);
        };
        animTimerRef.current = setTimeout(complete, ANIM_FALLBACK_MS);
    }, [clearAnimListeners, finishAnim, beginSettling]);

    const setExpandedMode = useCallback((nextMode) => {
        const normalized = nextMode === 'collapsed' ? 'collapsed' : 'expanded';
        const isCollapsed = normalized === 'collapsed';
        modeRef.current = normalized;

        const shell = document.querySelector('.gelia-app-shell');
        if (shell) shell.setAttribute('data-sidebar-mode', normalized);

        try {
            localStorage.setItem(SIDEBAR_MODE_STORAGE_KEY, normalized);
        } catch {
            /* ignore */
        }
        onModePersist?.(normalized);
        window.dispatchEvent(new CustomEvent('theme-sidebar-mode-preview', { detail: { mode: normalized } }));

        setAnim(isCollapsed ? 'collapsing' : 'expanding');
        setMode(normalized);

        requestAnimationFrame(() => {
            const sidebar = document.querySelector('.gelia-pro-sidebar');
            if (prefersReducedMotion()) {
                finishAnim();
                return;
            }
            bindWidthTransitionEnd(sidebar, { settle: isCollapsed });
        });
    }, [onModePersist, bindWidthTransitionEnd, finishAnim]);

    const toggleCollapsed = useCallback(() => {
        const nextMode = modeRef.current === 'collapsed' ? 'expanded' : 'collapsed';
        setExpandedMode(nextMode);
    }, [setExpandedMode]);

    useEffect(() => () => {
        clearAnimListeners();
    }, [clearAnimListeners]);

    const openDrawer = useCallback(() => {
        setDrawerOpen(true);
        setUserMenuOpen(false);
    }, []);

    const closeDrawer = useCallback(() => {
        setDrawerOpen(false);
        setUserMenuOpen(false);
        queueMicrotask(() => {
            drawerTriggerRef.current?.focus?.();
        });
    }, []);

    const toggleDrawer = useCallback(() => {
        setDrawerOpen((open) => {
            if (open) {
                queueMicrotask(() => drawerTriggerRef.current?.focus?.());
                return false;
            }
            return true;
        });
        setUserMenuOpen(false);
    }, []);

    const closeUserMenu = useCallback(() => setUserMenuOpen(false), []);
    const toggleUserMenu = useCallback(() => setUserMenuOpen((v) => !v), []);

    useEffect(() => {
        if (initialMode === 'collapsed' || initialMode === 'expanded') {
            setMode(initialMode);
            setAnim(null);
        }
    }, [initialMode]);

    useEffect(() => {
        if (!isMobile) setDrawerOpen(false);
    }, [isMobile]);

    useEffect(() => {
        const onToggle = () => {
            if (isMobile) toggleDrawer();
        };
        const onOpen = () => {
            if (isMobile) openDrawer();
        };
        window.addEventListener('gelia-sidebar-toggle-menu', onToggle);
        window.addEventListener('gelia-sidebar-open-menu', onOpen);
        return () => {
            window.removeEventListener('gelia-sidebar-toggle-menu', onToggle);
            window.removeEventListener('gelia-sidebar-open-menu', onOpen);
        };
    }, [isMobile, openDrawer, toggleDrawer]);

    useEffect(() => {
        if (!drawerOpen && !userMenuOpen) return undefined;

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                if (userMenuOpen) {
                    setUserMenuOpen(false);
                    userButtonRef.current?.focus?.();
                    return;
                }
                if (drawerOpen) closeDrawer();
            }
        };

        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, [drawerOpen, userMenuOpen, closeDrawer]);

    useEffect(() => {
        if (!drawerOpen || !isMobile) {
            document.body.style.overflow = '';
            return undefined;
        }
        const prev = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = prev;
        };
    }, [drawerOpen, isMobile]);

    useEffect(() => {
        if (!drawerOpen || !isMobile) return undefined;
        const panel = drawerPanelRef.current;
        if (!panel) return undefined;

        const focusables = panel.querySelectorAll(
            'a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])'
        );
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        first?.focus?.();

        const onKeyDown = (event) => {
            if (event.key !== 'Tab' || focusables.length === 0) return;
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus?.();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus?.();
            }
        };

        panel.addEventListener('keydown', onKeyDown);
        return () => panel.removeEventListener('keydown', onKeyDown);
    }, [drawerOpen, isMobile]);

    useEffect(() => {
        if (!userMenuOpen) return undefined;
        const onPointer = (event) => {
            if (
                userMenuRef.current?.contains(event.target)
                || userButtonRef.current?.contains(event.target)
            ) {
                return;
            }
            setUserMenuOpen(false);
        };
        const timer = setTimeout(() => document.addEventListener('mousedown', onPointer), 0);
        return () => {
            clearTimeout(timer);
            document.removeEventListener('mousedown', onPointer);
        };
    }, [userMenuOpen]);

    return {
        mode,
        collapsed,
        structuralCollapsed,
        anim,
        drawerOpen,
        userMenuOpen,
        drawerTriggerRef,
        drawerPanelRef,
        userMenuRef,
        userButtonRef,
        toggleCollapsed,
        setExpandedMode,
        openDrawer,
        closeDrawer,
        toggleDrawer,
        closeUserMenu,
        toggleUserMenu,
    };
}

export { SIDEBAR_MODE_STORAGE_KEY, ANIM_FALLBACK_MS, SETTLE_MS };
