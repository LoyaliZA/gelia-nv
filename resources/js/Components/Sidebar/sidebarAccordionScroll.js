/** Sangría de scroll al expandir secciones del sidebar */
export const SCROLL_PAD_PX = 8;
/** Alineado con --gelia-sidebar-widget-ms (Sidebar.jsx) + margen de transición */
export const EXPAND_SCROLL_WATCH_MS = 360;

/** Delta de scrollTop para revelar el bloque en el scroller (0 = ya visible). */
export function sidebarExpandScrollDelta(scrollerRect, blockRect, pad = SCROLL_PAD_PX) {
    const usableHeight = scrollerRect.height - pad * 2;

    if (blockRect.height > usableHeight) {
        return blockRect.top - scrollerRect.top - pad;
    }
    if (blockRect.bottom > scrollerRect.bottom - pad) {
        return blockRect.bottom - scrollerRect.bottom + pad;
    }
    if (blockRect.top < scrollerRect.top + pad) {
        return blockRect.top - scrollerRect.top - pad;
    }
    return 0;
}

/** Ajusta el scroll del panel para ver el bloque expandido completo (o el tope si no cabe). */
export function ensureBlockVisibleInSidebarScroll(blockEl, scrollerSelector) {
    const scroller = blockEl?.closest?.(scrollerSelector);
    if (!scroller || !blockEl) return;

    const delta = sidebarExpandScrollDelta(
        scroller.getBoundingClientRect(),
        blockEl.getBoundingClientRect()
    );
    if (Math.abs(delta) > 1) scroller.scrollTop += delta;
}

/** Durante la animación de apertura, mantiene el bloque a la vista; suelta al terminar. */
export function trackExpandScroll(blockEl, scrollerSelector) {
    if (!blockEl) return () => {};

    ensureBlockVisibleInSidebarScroll(blockEl, scrollerSelector);

    if (typeof ResizeObserver === 'undefined') {
        const t = setTimeout(
            () => ensureBlockVisibleInSidebarScroll(blockEl, scrollerSelector),
            EXPAND_SCROLL_WATCH_MS
        );
        return () => clearTimeout(t);
    }

    const ro = new ResizeObserver(() => ensureBlockVisibleInSidebarScroll(blockEl, scrollerSelector));
    ro.observe(blockEl);
    const t = setTimeout(() => ro.disconnect(), EXPAND_SCROLL_WATCH_MS);
    return () => {
        clearTimeout(t);
        ro.disconnect();
    };
}
