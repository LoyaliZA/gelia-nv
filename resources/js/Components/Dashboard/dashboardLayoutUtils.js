export const GRID_COLS = 24;
export const LEGACY_GRID_COLS = 12;
export const ROW_HEIGHT_PX = 48;
export const GRID_GAP_PX = 16;

/** Mínimo absoluto de todos los paneles personalizables del dashboard (ancho × alto en celdas). */
export const PANEL_GRID_MIN_W = 4;
export const PANEL_GRID_MIN_H = 4;
/** Mínimos de paneles con cuadrícula de tarjetas (Módulos / Funciones). */
export const CARD_GRID_PANEL_MIN_H = 4;
export const CARD_GRID_PANEL_MIN_W = 10;

export const PANEL_IDS = {
    MODULOS: 'panel_modulos',
    FUNCIONES: 'panel_funciones',
    SOLICITUDES: 'panel_solicitudes',
    CANCELACIONES: 'panel_cancelaciones_cotizaciones',
    ACTIVOS: 'panel_activos',
    RH: 'panel_rh',
};

/**
 * Disposición inicial que replica el layout anterior (módulos + widgets en fila, funciones abajo).
 */
export function buildDefaultLayout({ hasModulos, hasFunciones, hasWidgetSolicitudes, hasWidgetCancelaciones, hasWidgetActivos, hasWidgetRh }) {
    const layout = [];
    
    // 1. Widgets en la parte superior (6x4 cada uno)
    let widgetX = 0;
    let widgetY = 0;
    let maxWidgetY = 0;

    const pushWidget = (id) => {
        layout.push({
            i: id,
            x: widgetX,
            y: widgetY,
            w: 6,
            h: 4,
            minW: PANEL_GRID_MIN_W,
            minH: PANEL_GRID_MIN_H,
        });
        widgetX += 6;
        if (widgetX >= GRID_COLS) {
            widgetX = 0;
            widgetY += 4;
        }
        maxWidgetY = Math.max(maxWidgetY, widgetY + (widgetX > 0 ? 4 : 0));
    };

    // Orden en el que aparecen de izquierda a derecha
    if (hasWidgetRh) pushWidget(PANEL_IDS.RH);
    if (hasWidgetActivos) pushWidget(PANEL_IDS.ACTIVOS);
    if (hasWidgetCancelaciones) pushWidget(PANEL_IDS.CANCELACIONES);
    if (hasWidgetSolicitudes) pushWidget(PANEL_IDS.SOLICITUDES);

    // 2. Paneles principales debajo de los widgets (12x8 cada uno)
    let nextRow = maxWidgetY;
    let panelX = 0;

    const pushPanel = (id) => {
        layout.push({
            i: id,
            x: panelX,
            y: nextRow,
            w: 12,
            h: 8,
            minW: CARD_GRID_PANEL_MIN_W,
            minH: CARD_GRID_PANEL_MIN_H,
        });
        panelX += 12;
        if (panelX >= GRID_COLS) {
            panelX = 0;
            nextRow += 8;
        }
    };

    if (hasModulos) pushPanel(PANEL_IDS.MODULOS);
    if (hasFunciones) pushPanel(PANEL_IDS.FUNCIONES);

    return layout;
}

function clampItem(item) {
    const minW = Math.max(item.minW ?? 2, PANEL_GRID_MIN_W);
    const minH = Math.max(item.minH ?? 2, PANEL_GRID_MIN_H);
    const w = Math.max(minW, Math.min(GRID_COLS, item.w));
    const x = Math.max(0, Math.min(GRID_COLS - w, item.x));
    const h = Math.max(minH, item.h);
    const y = Math.max(0, item.y);

    return { ...item, x, y, w, h, minW, minH };
}

export function rectsOverlap(a, b) {
    if (a.i === b.i) return false;
    return !(a.x + a.w <= b.x || b.x + b.w <= a.x || a.y + a.h <= b.y || b.y + b.h <= a.y);
}

export function getCollisions(layout, itemId) {
    const item = layout.find((entry) => entry.i === itemId);
    if (!item) return [];
    return layout.filter((other) => other.i !== itemId && rectsOverlap(item, other));
}

export function layoutHasCollisions(layout) {
    for (let i = 0; i < layout.length; i++) {
        for (let j = i + 1; j < layout.length; j++) {
            if (rectsOverlap(layout[i], layout[j])) return true;
        }
    }
    return false;
}

export function pushItemDown(item, obstacle) {
    return clampItem({ ...item, y: obstacle.y + obstacle.h });
}

/**
 * Empuja paneles que solapan con el item cambiado hacia abajo (estilo launcher Android).
 */
export function resolveCollisions(layout, changedId) {
    let result = layout.map((entry) => ({ ...entry }));
    const maxIterations = result.length * result.length * 4;

    for (let iter = 0; iter < maxIterations; iter++) {
        const anchor = result.find((entry) => entry.i === changedId);
        if (!anchor) break;

        const collisions = getCollisions(result, changedId);
        if (collisions.length === 0) break;

        collisions.sort((a, b) => a.y - b.y || a.x - b.x);

        for (const colliding of collisions) {
            const idx = result.findIndex((entry) => entry.i === colliding.i);
            result[idx] = pushItemDown(result[idx], anchor);
        }

        for (let i = 0; i < result.length; i++) {
            for (let j = i + 1; j < result.length; j++) {
                if (rectsOverlap(result[i], result[j])) {
                    const pushIdx = result[j].y >= result[i].y ? j : i;
                    const stayIdx = pushIdx === i ? j : i;
                    if (result[pushIdx].i !== changedId) {
                        result[pushIdx] = pushItemDown(result[pushIdx], result[stayIdx]);
                    } else {
                        result[stayIdx] = pushItemDown(result[stayIdx], result[pushIdx]);
                    }
                }
            }
        }
    }

    return result.map(clampItem);
}

function findMinY(item, placed) {
    let y = 0;
    let guard = 0;
    while (guard < 500) {
        guard++;
        const test = { ...item, y };
        const blocker = placed.find((p) => rectsOverlap(test, p));
        if (!blocker) return y;
        y = blocker.y + blocker.h;
    }
    return item.y;
}

const PANEL_PRIORITY = {
    [PANEL_IDS.MODULOS]: 0,
    [PANEL_IDS.SOLICITUDES]: 1,
    [PANEL_IDS.ACTIVOS]: 2,
    [PANEL_IDS.RH]: 3,
    [PANEL_IDS.FUNCIONES]: 4,
};

function packItems(items) {
    const sorted = [...items].sort((a, b) => {
        const pa = PANEL_PRIORITY[a.i] ?? 99;
        const pb = PANEL_PRIORITY[b.i] ?? 99;
        if (pa !== pb) return pa - pb;
        return b.w * b.h - a.w * a.h;
    });

    const placed = [];

    for (const item of sorted) {
        let bestPos = null;
        let bestScore = Infinity;

        for (let x = 0; x <= GRID_COLS - item.w; x++) {
            const candidate = clampItem({ ...item, x, y: 0 });
            const y = findMinY(candidate, placed);
            const score = y * GRID_COLS + x;

            if (score < bestScore) {
                bestScore = score;
                bestPos = clampItem({ ...item, x, y });
            }
        }

        placed.push(bestPos ?? clampItem(item));
    }

    return compactLayout(placed);
}

/**
 * Busca la mejor disposición: restaura tamaños por defecto y empaqueta sin solapamientos.
 */
export function optimizeLayout(layout, defaultLayout) {
    if (layout.length === 0) return layout;

    const defaultById = Object.fromEntries((defaultLayout || []).map((d) => [d.i, d]));
    const items = layout.map((item) => {
        const def = defaultById[item.i];
        if (!def) return { ...item };
        return clampItem({ ...def, i: item.i });
    });

    return packItems(items);
}

/**
 * Resuelve colisiones sin compactar (preview durante interacción).
 */
export function resolveCollisionsOnly(layout, changedId) {
    return resolveCollisions(layout, changedId);
}

/**
 * Compactación vertical: sube cada panel al máximo y posible sin solaparse, preservando x.
 */
export function compactLayout(layout) {
    const sorted = [...layout].sort((a, b) => a.y - b.y || a.x - b.x);
    const placed = [];

    for (const item of sorted) {
        placed.push(clampItem({ ...item, y: findMinY(item, placed) }));
    }

    return placed;
}

/**
 * Aplica resolución de colisiones y compactación tras mover o redimensionar.
 */
export function applyLayoutChange(layout, changedId) {
    const resolved = resolveCollisions(layout, changedId);
    return compactLayout(resolved);
}

/**
 * Detecta layouts guardados con la cuadrícula anterior de 12 columnas.
 */
export function isLegacyGridLayout(layout) {
    if (!layout?.length) return false;
    const maxExtent = Math.max(...layout.map((item) => item.x + item.w));
    return maxExtent <= LEGACY_GRID_COLS;
}

/**
 * Escala un layout 12-col al sistema actual (24 columnas).
 */
export function migrateLayoutFromLegacy(layout) {
    if (!layout?.length || !isLegacyGridLayout(layout)) {
        return layout.map(clampItem);
    }

    const scale = GRID_COLS / LEGACY_GRID_COLS;

    return layout.map((item) =>
        clampItem({
            ...item,
            x: Math.round(item.x * scale),
            w: Math.max(
                item.minW ?? PANEL_GRID_MIN_W,
                Math.round(item.w * scale)
            ),
            minW: item.minW
                ? Math.max(PANEL_GRID_MIN_W, Math.round(item.minW * scale))
                : PANEL_GRID_MIN_W,
        })
    );
}

/**
 * Combina layout guardado en BD con paneles visibles y valores por defecto.
 */
export function resolveLayout(savedLayout, visiblePanelIds, defaultLayout) {
    const defaultsById = Object.fromEntries(defaultLayout.map((item) => [item.i, item]));
    const migratedSaved = migrateLayoutFromLegacy(savedLayout || []);
    const savedById = Object.fromEntries(migratedSaved.map((item) => [item.i, item]));

    let layout = visiblePanelIds
        .map((id) => {
            const base = defaultsById[id];
            if (!base) return null;
            const saved = savedById[id];
            return clampItem({ ...base, ...saved, i: id, minW: base.minW, minH: base.minH });
        })
        .filter(Boolean);

    if (layoutHasCollisions(layout)) {
        layout = compactLayout(layout);
    }

    return layout;
}

export function gridColumnWidthPx(gridWidth, cols = GRID_COLS) {
    if (!gridWidth || gridWidth <= 0) return 0;
    return (gridWidth - (cols - 1) * GRID_GAP_PX) / cols;
}

export function gridColumnStridePx(gridWidth, cols = GRID_COLS) {
    return gridColumnWidthPx(gridWidth, cols) + GRID_GAP_PX;
}

export function layoutToGridStyle(item) {
    return {
        gridColumn: `${item.x + 1} / span ${item.w}`,
        gridRow: `${item.y + 1} / span ${item.h}`,
    };
}

function layoutToAbsoluteStyle(item) {
    return {
        '--dg-x': item.x,
        '--dg-y': item.y,
        '--dg-w': item.w,
        '--dg-h': item.h,
    };
}

/**
 * Estilo absoluto animable en píxeles (left/top/width/height interpolables).
 */
export function layoutToPixelStyle(item, gridWidth) {
    if (!gridWidth || gridWidth <= 0) {
        return layoutToAbsoluteStyle(item);
    }

    const colWidth = gridColumnWidthPx(gridWidth, GRID_COLS);
    const rowStride = ROW_HEIGHT_PX + GRID_GAP_PX;

    return {
        left: `${item.x * (colWidth + GRID_GAP_PX)}px`,
        top: `${item.y * rowStride}px`,
        width: `${item.w * colWidth + (item.w - 1) * GRID_GAP_PX}px`,
        height: `${item.h * ROW_HEIGHT_PX + (item.h - 1) * GRID_GAP_PX}px`,
    };
}

export function gridHeightFromLayout(layout) {
    if (layout.length === 0) return 0;
    const maxRow = Math.max(...layout.map((item) => item.y + item.h));
    return maxRow * ROW_HEIGHT_PX + Math.max(0, maxRow - 1) * GRID_GAP_PX;
}

export function pointerToGridCell(clientX, clientY, gridRect, cols = GRID_COLS) {
    const relX = clientX - gridRect.left;
    const relY = clientY - gridRect.top;
    const strideX = gridColumnStridePx(gridRect.width, cols);
    const rowStride = ROW_HEIGHT_PX + GRID_GAP_PX;

    const x = Math.max(0, Math.min(cols - 1, Math.floor(relX / strideX)));
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

export function layoutsEqual(a, b) {
    if (!a || !b || a.length !== b.length) return false;
    return a.every((item) => {
        const other = b.find((entry) => entry.i === item.i);
        if (!other) return false;
        return item.x === other.x && item.y === other.y && item.w === other.w && item.h === other.h;
    });
}
