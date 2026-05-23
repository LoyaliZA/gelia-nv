export const GRID_COLS = 12;
export const ROW_HEIGHT_PX = 52;
export const GRID_GAP_PX = 24;

export const PANEL_IDS = {
    MODULOS: 'panel_modulos',
    FUNCIONES: 'panel_funciones',
    SOLICITUDES: 'panel_solicitudes',
};

/**
 * Disposición inicial que replica el layout anterior (módulos + widget en fila, funciones abajo).
 */
export function buildDefaultLayout({ hasModulos, hasFunciones, hasWidget }) {
    const layout = [];
    let nextRow = 0;

    if (hasModulos) {
        layout.push({
            i: PANEL_IDS.MODULOS,
            x: 0,
            y: 0,
            w: hasWidget ? 8 : 12,
            h: 12,
            minW: 4,
            minH: 5,
        });
        nextRow = 12;
    }

    if (hasWidget) {
        layout.push({
            i: PANEL_IDS.SOLICITUDES,
            x: hasModulos ? 8 : 0,
            y: 0,
            w: hasModulos ? 4 : 12,
            h: 12,
            minW: 3,
            minH: 5,
        });
        if (!hasModulos) nextRow = 12;
    }

    if (hasFunciones) {
        layout.push({
            i: PANEL_IDS.FUNCIONES,
            x: 0,
            y: nextRow,
            w: 12,
            h: 7,
            minW: 4,
            minH: 4,
        });
    }

    return layout;
}

function clampItem(item) {
    const minW = item.minW ?? 2;
    const minH = item.minH ?? 2;
    const w = Math.max(minW, Math.min(GRID_COLS, item.w));
    const x = Math.max(0, Math.min(GRID_COLS - w, item.x));
    const h = Math.max(minH, item.h);
    const y = Math.max(0, item.y);

    return { ...item, x, y, w, h };
}

/**
 * Combina layout guardado en BD con paneles visibles y valores por defecto.
 */
export function resolveLayout(savedLayout, visiblePanelIds, defaultLayout) {
    const defaultsById = Object.fromEntries(defaultLayout.map((item) => [item.i, item]));
    const savedById = Object.fromEntries((savedLayout || []).map((item) => [item.i, item]));

    return visiblePanelIds
        .map((id) => {
            const base = defaultsById[id];
            if (!base) return null;
            const saved = savedById[id];
            return clampItem(saved ? { ...base, ...saved, i: id } : base);
        })
        .filter(Boolean);
}

export function layoutToGridStyle(item) {
    return {
        gridColumn: `${item.x + 1} / span ${item.w}`,
        gridRow: `${item.y + 1} / span ${item.h}`,
    };
}

export function pointerToGridCell(clientX, clientY, gridRect, cols = GRID_COLS) {
    const relX = clientX - gridRect.left;
    const relY = clientY - gridRect.top;
    const colWidth = gridRect.width / cols;
    const rowStride = ROW_HEIGHT_PX + GRID_GAP_PX;

    const x = Math.max(0, Math.min(cols - 1, Math.floor(relX / colWidth)));
    const y = Math.max(0, Math.floor(relY / rowStride));

    return { x, y };
}

export function resizeItemFromDelta(item, deltaCols, deltaRows) {
    return clampItem({
        ...item,
        w: item.w + deltaCols,
        h: item.h + deltaRows,
    });
}

export function moveItem(item, x, y) {
    return clampItem({ ...item, x, y });
}

/**
 * Orden de paneles en móvil según la disposición guardada (arriba→abajo, izquierda→derecha).
 */
export function orderPanelsByLayout(layout, visiblePanelIds) {
    const sorted = [...layout]
        .sort((a, b) => a.y - b.y || a.x - b.x)
        .map((item) => item.i)
        .filter((id) => visiblePanelIds.includes(id));
    const missing = visiblePanelIds.filter((id) => !sorted.includes(id));
    return [...sorted, ...missing];
}
