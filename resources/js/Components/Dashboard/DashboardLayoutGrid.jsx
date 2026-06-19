import React, { useCallback, useEffect, useRef, useState } from 'react';
import { GripVertical, Maximize2 } from 'lucide-react';
import {
    GRID_COLS,
    GRID_GAP_PX,
    ROW_HEIGHT_PX,
    applyLayoutChange,
    getCollisions,
    gridColumnStridePx,
    gridHeightFromLayout,
    layoutToPixelStyle,
    moveItem,
    pointerToGridCell,
    resizeItemFromDelta,
    resolveCollisionsOnly,
} from './dashboardLayoutUtils';

function DashboardGridItem({
    item,
    editMode,
    hasCollision,
    isInteracting,
    animateLayout,
    gridWidth,
    onInteractionStart,
    onInteractionEnd,
    onMove,
    onResize,
    children,
}) {
    const itemRef = useRef(null);

    const onDragPointerDown = useCallback(
        (e) => {
            if (!editMode) return;
            e.preventDefault();
            e.stopPropagation();

            const gridEl = itemRef.current?.closest('[data-dashboard-grid]');
            if (!gridEl) return;

            onInteractionStart(item.i);

            const snapshot = { itemId: item.i, w: item.w, h: item.h };

            const onMovePointer = (ev) => {
                const rect = gridEl.getBoundingClientRect();
                const cell = pointerToGridCell(ev.clientX, ev.clientY, rect);
                const nx = Math.min(GRID_COLS - snapshot.w, Math.max(0, cell.x));
                const ny = Math.max(0, cell.y);
                onMove(snapshot.itemId, nx, ny);
            };

            const onUp = () => {
                onInteractionEnd(snapshot.itemId);
                window.removeEventListener('pointermove', onMovePointer);
                window.removeEventListener('pointerup', onUp);
            };

            window.addEventListener('pointermove', onMovePointer);
            window.addEventListener('pointerup', onUp);
        },
        [editMode, item, onMove, onInteractionStart, onInteractionEnd]
    );

    const onResizePointerDown = useCallback(
        (e) => {
            if (!editMode) return;
            e.preventDefault();
            e.stopPropagation();

            const gridEl = itemRef.current?.closest('[data-dashboard-grid]');
            if (!gridEl) return;

            onInteractionStart(item.i);

            const rect = gridEl.getBoundingClientRect();
            const colStride = gridColumnStridePx(rect.width);
            const rowStride = ROW_HEIGHT_PX + GRID_GAP_PX;
            const startX = e.clientX;
            const startY = e.clientY;
            const snapshot = { ...item };

            const onMovePointer = (ev) => {
                const deltaCols = Math.round((ev.clientX - startX) / colStride);
                const deltaRows = Math.round((ev.clientY - startY) / rowStride);
                onResize(snapshot, deltaCols, deltaRows);
            };

            const onUp = () => {
                onInteractionEnd(snapshot.i);
                window.removeEventListener('pointermove', onMovePointer);
                window.removeEventListener('pointerup', onUp);
            };

            window.addEventListener('pointermove', onMovePointer);
            window.addEventListener('pointerup', onUp);
        },
        [editMode, item, onResize, onInteractionStart, onInteractionEnd]
    );

    const animateClass = animateLayout && !isInteracting ? 'dashboard-grid-item--animate' : '';
    const interactingClass = isInteracting ? 'dashboard-grid-item--interacting' : '';

    return (
        <div
            ref={itemRef}
            className={`dashboard-grid-item relative ${animateClass} ${interactingClass} ${editMode ? 'dashboard-grid-item--edit z-20' : 'z-10'} ${hasCollision ? 'dashboard-grid-item--collision' : ''}`}
            style={layoutToPixelStyle(item, gridWidth)}
        >
            {editMode && (
                <div className="absolute top-2 left-2 right-2 flex items-center justify-between z-30 pointer-events-none">
                    <button
                        type="button"
                        onPointerDown={onDragPointerDown}
                        className="pointer-events-auto flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg theme-surface border theme-border shadow-md cursor-grab active:cursor-grabbing text-[9px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main outline-none"
                        aria-label={`Mover ${item.i}`}
                    >
                        <GripVertical className="w-3.5 h-3.5" style={{ color: 'var(--color-primario)' }} />
                        Arrastrar
                    </button>
                </div>
            )}

            {editMode && (
                <span className="dashboard-grid-size-badge" aria-hidden="true">
                    {item.w}×{item.h}
                </span>
            )}

            <div className="dashboard-grid-item__inner">
                <div className="dashboard-grid-item__content h-full">{children}</div>
            </div>

            {editMode && (
                <button
                    type="button"
                    onPointerDown={onResizePointerDown}
                    className="absolute bottom-1 right-1 z-30 p-1.5 rounded-lg theme-surface border theme-border shadow-md cursor-se-resize outline-none hover:border-[var(--color-primario)]"
                    aria-label={`Redimensionar ${item.i}`}
                >
                    <Maximize2 className="w-3.5 h-3.5 rotate-90" style={{ color: 'var(--color-primario)' }} />
                </button>
            )}
        </div>
    );
}

export default function DashboardLayoutGrid({
    layout,
    editMode,
    onLayoutChange,
    panels,
    visiblePanelIds,
    animateLayout = true,
}) {
    const gridRef = useRef(null);
    const [gridWidth, setGridWidth] = useState(0);

    useEffect(() => {
        const el = gridRef.current;
        if (!el) return undefined;

        const measure = () => setGridWidth(el.clientWidth);
        measure();

        const observer = new ResizeObserver(measure);
        observer.observe(el);
        return () => observer.disconnect();
    }, []);
    const interactionRef = useRef(null);
    const layoutSnapshotRef = useRef(null);
    const draftLayoutRef = useRef(layout);
    const layoutRef = useRef(layout);
    const [interactingId, setInteractingId] = useState(null);

    useEffect(() => {
        layoutRef.current = layout;
        if (!interactionRef.current) {
            draftLayoutRef.current = layout;
        }
    }, [layout]);

    const handleInteractionStart = useCallback((itemId) => {
        interactionRef.current = itemId;
        layoutSnapshotRef.current = layoutRef.current.map((entry) => ({ ...entry }));
        draftLayoutRef.current = layoutRef.current;
        setInteractingId(itemId);
    }, []);

    const handleInteractionEnd = useCallback(
        (itemId) => {
            interactionRef.current = null;
            layoutSnapshotRef.current = null;
            setInteractingId(null);
            const finalized = applyLayoutChange(draftLayoutRef.current, itemId);
            draftLayoutRef.current = finalized;
            onLayoutChange(finalized);
        },
        [onLayoutChange]
    );

    const handleMove = useCallback(
        (itemId, x, y) => {
            const base = layoutSnapshotRef.current ?? layoutRef.current;
            const moved = base.map((entry) => (entry.i === itemId ? moveItem(entry, x, y) : entry));
            const next = resolveCollisionsOnly(moved, itemId);
            draftLayoutRef.current = next;
            onLayoutChange(next);
        },
        [onLayoutChange]
    );

    const handleResize = useCallback(
        (snapshot, deltaCols, deltaRows) => {
            const resized = (layoutSnapshotRef.current ?? layoutRef.current).map((entry) => {
                if (entry.i !== snapshot.i) return entry;
                return resizeItemFromDelta(snapshot, deltaCols, deltaRows);
            });
            draftLayoutRef.current = resized;
            onLayoutChange(resized);
        },
        [onLayoutChange]
    );

    const collisionIds =
        editMode && !interactingId
            ? new Set(layout.flatMap((entry) => getCollisions(layout, entry.i).map((other) => other.i)))
            : new Set();

    useEffect(() => {
        if (!editMode) {
            interactionRef.current = null;
            layoutSnapshotRef.current = null;
            setInteractingId(null);
        }
    }, [editMode]);

    const gridHeight = gridHeightFromLayout(layout);

    return (
        <div
                ref={gridRef}
                data-dashboard-grid
                className={`dashboard-layout-absolute ${editMode ? 'dashboard-layout-grid--edit rounded-[2rem] p-2' : ''}`}
                style={{
                    height: `${gridHeight}px`,
                    minHeight: `${gridHeight}px`,
                    '--dg-cols': GRID_COLS,
                    '--dg-row': `${ROW_HEIGHT_PX}px`,
                    '--dg-gap': `${GRID_GAP_PX}px`,
                }}
            >
                {layout.map((item) => (
                    <DashboardGridItem
                        key={item.i}
                        item={item}
                        editMode={editMode}
                        hasCollision={collisionIds.has(item.i)}
                        isInteracting={interactingId === item.i}
                        animateLayout={animateLayout}
                        gridWidth={gridWidth}
                        onInteractionStart={handleInteractionStart}
                        onInteractionEnd={handleInteractionEnd}
                        onMove={handleMove}
                        onResize={handleResize}
                    >
                        {panels[item.i]}
                    </DashboardGridItem>
                ))}
            </div>
    );
}
