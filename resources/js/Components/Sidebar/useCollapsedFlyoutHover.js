import { useCallback, useEffect, useRef } from 'react';

export const HOVER_OPEN_DELAY_MS = 220;
export const HOVER_CLOSE_DELAY_MS = 450;
export const NESTED_OPEN_DELAY_MS = 180;

function prefersReducedMotion() {
    if (typeof window === 'undefined') return false;
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function canHover() {
    if (typeof window === 'undefined') return false;
    return window.matchMedia('(hover: hover)').matches;
}

/**
 * Hover con pausa para flyout del sidebar profesional contraído.
 */
export default function useCollapsedFlyoutHover({
    collapsed,
    onOpenFlyout,
    onCloseFlyout,
    onOpenNestedGroup,
}) {
    const openTimerRef = useRef(null);
    const closeTimerRef = useRef(null);
    const nestedTimerRef = useRef(null);
    const hoverActiveRef = useRef(false);
    const clickModeRef = useRef(false);

    const clearOpenTimer = useCallback(() => {
        clearTimeout(openTimerRef.current);
        openTimerRef.current = null;
    }, []);

    const clearCloseTimer = useCallback(() => {
        clearTimeout(closeTimerRef.current);
        closeTimerRef.current = null;
    }, []);

    const clearNestedTimer = useCallback(() => {
        clearTimeout(nestedTimerRef.current);
        nestedTimerRef.current = null;
    }, []);

    const clearAllTimers = useCallback(() => {
        clearOpenTimer();
        clearCloseTimer();
        clearNestedTimer();
    }, [clearOpenTimer, clearCloseTimer, clearNestedTimer]);

    useEffect(() => () => clearAllTimers(), [clearAllTimers]);

    useEffect(() => {
        if (!collapsed) {
            clearAllTimers();
            hoverActiveRef.current = false;
            clickModeRef.current = false;
        }
    }, [collapsed, clearAllTimers]);

    const scheduleClose = useCallback(() => {
        if (!collapsed || clickModeRef.current) return;
        clearCloseTimer();
        closeTimerRef.current = setTimeout(() => {
            if (!hoverActiveRef.current) onCloseFlyout?.();
        }, HOVER_CLOSE_DELAY_MS);
    }, [collapsed, clearCloseTimer, onCloseFlyout]);

    const cancelClose = useCallback(() => {
        clearCloseTimer();
        hoverActiveRef.current = true;
    }, [clearCloseTimer]);

    const handleRootHoverEnter = useCallback((id, event) => {
        if (!collapsed || !canHover() || prefersReducedMotion()) return;
        clickModeRef.current = false;
        cancelClose();
        clearOpenTimer();
        const rect = event.currentTarget.getBoundingClientRect();
        openTimerRef.current = setTimeout(() => {
            onOpenFlyout?.(id, rect);
        }, HOVER_OPEN_DELAY_MS);
    }, [collapsed, cancelClose, clearOpenTimer, onOpenFlyout]);

    const handleRootHoverLeave = useCallback(() => {
        if (!collapsed || !canHover()) return;
        clearOpenTimer();
        hoverActiveRef.current = false;
        scheduleClose();
    }, [collapsed, clearOpenTimer, scheduleClose]);

    const handleFlyoutEnter = useCallback(() => {
        if (!collapsed) return;
        cancelClose();
    }, [collapsed, cancelClose]);

    const handleFlyoutLeave = useCallback(() => {
        if (!collapsed || clickModeRef.current) return;
        hoverActiveRef.current = false;
        scheduleClose();
    }, [collapsed, scheduleClose]);

    const handleCollapsedClick = useCallback((id, event) => {
        clickModeRef.current = true;
        clearAllTimers();
        hoverActiveRef.current = true;
        const rect = event.currentTarget.getBoundingClientRect();
        onOpenFlyout?.(id, rect, { toggle: true });
    }, [clearAllTimers, onOpenFlyout]);

    const handleNestedHoverEnter = useCallback((groupId) => {
        if (!canHover() || prefersReducedMotion()) return;
        cancelClose();
        clearNestedTimer();
        nestedTimerRef.current = setTimeout(() => {
            onOpenNestedGroup?.(groupId);
        }, NESTED_OPEN_DELAY_MS);
    }, [cancelClose, clearNestedTimer, onOpenNestedGroup]);

    const handleNestedHoverLeave = useCallback(() => {
        clearNestedTimer();
    }, [clearNestedTimer]);

    const resetClickMode = useCallback(() => {
        clickModeRef.current = false;
        hoverActiveRef.current = false;
    }, []);

    return {
        handleRootHoverEnter,
        handleRootHoverLeave,
        handleFlyoutEnter,
        handleFlyoutLeave,
        handleCollapsedClick,
        handleNestedHoverEnter,
        handleNestedHoverLeave,
        resetClickMode,
        cancelClose,
        scheduleClose,
    };
}
