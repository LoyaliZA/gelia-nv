import React, { useCallback, useEffect, useRef } from 'react';
import { GripVertical, Maximize2 } from 'lucide-react';
import {
    GRID_COLS,
    GRID_GAP_PX,
    ROW_HEIGHT_PX,
    layoutToGridStyle,
    moveItem,
    orderPanelsByLayout,
    pointerToGridCell,
    resizeItemFromDelta,
} from './dashboardLayoutUtils';

const ESTILOS_GRID = `
    .dashboard-layout-grid--edit {
        background-image:
            linear-gradient(to right, color-mix(in srgb, var(--color-primario) 12%, transparent) 1px, transparent 1px),
            linear-gradient(to bottom, color-mix(in srgb, var(--color-primario) 12%, transparent) 1px, transparent 1px);
        background-size: calc((100% - ${(GRID_COLS - 1) * GRID_GAP_PX}px) / ${GRID_COLS}) ${ROW_HEIGHT_PX + GRID_GAP_PX}px;
        background-position: 0 0;
    }
    .dashboard-grid-item {
        min-height: 0;
        min-width: 0;
        overflow: hidden;
    }
    .dashboard-grid-item--edit {
        outline: 2px dashed color-mix(in srgb, var(--color-primario) 45%, transparent);
        outline-offset: 2px;
    }
    .dashboard-grid-item__inner {
        height: 100%;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    .dashboard-grid-item__content {
        flex: 1;
        min-height: 0;
        min-width: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
`;

function DashboardGridItem({ item, editMode, onMove, onResize, children }) {
    const itemRef = useRef(null);
    const dragRef = useRef(null);
    const resizeRef = useRef(null);

    const onDragPointerDown = useCallback(
        (e) => {
            if (!editMode) return;
            e.preventDefault();
            e.stopPropagation();

            const gridEl = itemRef.current?.closest('[data-dashboard-grid]');
            if (!gridEl) return;

            dragRef.current = {
                itemId: item.i,
                w: item.w,
                h: item.h,
            };

            const onMovePointer = (ev) => {
                const rect = gridEl.getBoundingClientRect();
                const cell = pointerToGridCell(ev.clientX, ev.clientY, rect);
                const nx = Math.min(GRID_COLS - dragRef.current.w, Math.max(0, cell.x));
                const ny = Math.max(0, cell.y);
                onMove(dragRef.current.itemId, nx, ny);
            };

            const onUp = () => {
                dragRef.current = null;
                window.removeEventListener('pointermove', onMovePointer);
                window.removeEventListener('pointerup', onUp);
            };

            window.addEventListener('pointermove', onMovePointer);
            window.addEventListener('pointerup', onUp);
        },
        [editMode, item, onMove]
    );

    const onResizePointerDown = useCallback(
        (e) => {
            if (!editMode) return;
            e.preventDefault();
            e.stopPropagation();

            const gridEl = itemRef.current?.closest('[data-dashboard-grid]');
            if (!gridEl) return;

            const rect = gridEl.getBoundingClientRect();
            const colWidth = rect.width / GRID_COLS;
            const rowStride = ROW_HEIGHT_PX + GRID_GAP_PX;
            const startX = e.clientX;
            const startY = e.clientY;
            const origW = item.w;
            const origH = item.h;

            resizeRef.current = { itemId: item.i };

            const onMovePointer = (ev) => {
                const deltaCols = Math.round((ev.clientX - startX) / colWidth);
                const deltaRows = Math.round((ev.clientY - startY) / rowStride);
                onResize(resizeRef.current.itemId, origW, origH, deltaCols, deltaRows);
            };

            const onUp = () => {
                resizeRef.current = null;
                window.removeEventListener('pointermove', onMovePointer);
                window.removeEventListener('pointerup', onUp);
            };

            window.addEventListener('pointermove', onMovePointer);
            window.addEventListener('pointerup', onUp);
        },
        [editMode, item, onResize]
    );

    return (
        <div
            ref={itemRef}
            className={`dashboard-grid-item relative ${editMode ? 'dashboard-grid-item--edit z-20' : 'z-10'}`}
            style={layoutToGridStyle(item)}
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

            <div className={`dashboard-grid-item__inner ${editMode ? 'pt-10' : ''}`}>
                <div className="dashboard-grid-item__content">{children}</div>
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

function DashboardMobileStack({ layout, visiblePanelIds, panels }) {
    const orderedIds = orderPanelsByLayout(layout, visiblePanelIds);

    return (
        <div className="flex flex-col gap-6 w-full min-w-0">
            {orderedIds.map((id) => (
                <div key={id} className="w-full min-w-0 shrink-0">
                    {panels[id]}
                </div>
            ))}
        </div>
    );
}

export default function DashboardLayoutGrid({
    layout,
    editMode,
    onLayoutChange,
    panels,
    isMobile,
    visiblePanelIds,
}) {
    const resizeSnapshot = useRef({});

    const handleMove = useCallback(
        (itemId, x, y) => {
            onLayoutChange(layout.map((entry) => (entry.i === itemId ? moveItem(entry, x, y) : entry)));
        },
        [layout, onLayoutChange]
    );

    const handleResize = useCallback(
        (itemId, origW, origH, deltaCols, deltaRows) => {
            onLayoutChange(
                layout.map((entry) => {
                    if (entry.i !== itemId) return entry;
                    const key = itemId;
                    if (!resizeSnapshot.current[key]) {
                        resizeSnapshot.current[key] = { w: origW, h: origH };
                    }
                    const base = resizeSnapshot.current[key];
                    return resizeItemFromDelta({ ...entry, w: base.w, h: base.h }, deltaCols, deltaRows);
                })
            );
        },
        [layout, onLayoutChange]
    );

    useEffect(() => {
        if (!editMode) resizeSnapshot.current = {};
    }, [editMode]);

    if (isMobile) {
        return <DashboardMobileStack layout={layout} visiblePanelIds={visiblePanelIds} panels={panels} />;
    }

    return (
        <>
            <style>{ESTILOS_GRID}</style>
            <div
                data-dashboard-grid
                className={`grid relative w-full min-w-0 ${editMode ? 'dashboard-layout-grid--edit rounded-[2rem] p-2' : ''}`}
                style={{
                    gridTemplateColumns: `repeat(${GRID_COLS}, minmax(0, 1fr))`,
                    gridAutoRows: `${ROW_HEIGHT_PX}px`,
                    gap: `${GRID_GAP_PX}px`,
                }}
            >
                {layout.map((item) => (
                    <DashboardGridItem
                        key={item.i}
                        item={item}
                        editMode={editMode}
                        onMove={handleMove}
                        onResize={handleResize}
                    >
                        {panels[item.i]}
                    </DashboardGridItem>
                ))}
            </div>
        </>
    );
}
