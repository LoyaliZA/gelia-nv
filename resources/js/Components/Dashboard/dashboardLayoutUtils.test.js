import {
    applyLayoutChange,
    buildDefaultLayout,
    compactLayout,
    getCollisions,
    layoutHasCollisions,
    optimizeLayout,
    PANEL_GRID_MIN_H,
    PANEL_GRID_MIN_W,
    PANEL_IDS,
    rectsOverlap,
    resolveCollisions,
    resolveLayout,
} from './dashboardLayoutUtils.js';

describe('rectsOverlap', () => {
    const a = { i: 'a', x: 0, y: 0, w: 4, h: 4 };

    it('detecta solapamiento cuando los rectángulos se intersectan', () => {
        const b = { i: 'b', x: 2, y: 2, w: 4, h: 4 };
        expect(rectsOverlap(a, b)).toBe(true);
    });

    it('no detecta solapamiento cuando están separados', () => {
        const b = { i: 'b', x: 5, y: 0, w: 3, h: 3 };
        expect(rectsOverlap(a, b)).toBe(false);
    });

    it('no detecta solapamiento consigo mismo', () => {
        expect(rectsOverlap(a, a)).toBe(false);
    });
});

describe('getCollisions', () => {
    const layout = [
        { i: 'a', x: 0, y: 0, w: 6, h: 6 },
        { i: 'b', x: 4, y: 4, w: 4, h: 4 },
        { i: 'c', x: 8, y: 0, w: 4, h: 4 },
    ];

    it('devuelve items que solapan con el item dado', () => {
        const collisions = getCollisions(layout, 'a');
        expect(collisions.map((item) => item.i)).toEqual(['b']);
    });

    it('devuelve array vacío si no hay colisiones', () => {
        expect(getCollisions(layout, 'c')).toEqual([]);
    });
});

describe('resolveCollisions', () => {
    it('empuja paneles solapados hacia abajo', () => {
        const layout = [
            { i: 'a', x: 0, y: 0, w: 6, h: 6, minW: 2, minH: 2 },
            { i: 'b', x: 2, y: 2, w: 4, h: 4, minW: 2, minH: 2 },
        ];

        const resolved = resolveCollisions(layout, 'a');
        const b = resolved.find((item) => item.i === 'b');

        expect(b.y).toBeGreaterThanOrEqual(6);
        expect(layoutHasCollisions(resolved)).toBe(false);
    });
});

describe('compactLayout', () => {
    it('sube paneles para eliminar huecos verticales', () => {
        const layout = [
            { i: 'a', x: 0, y: 10, w: 6, h: 4, minW: 2, minH: 2 },
            { i: 'b', x: 6, y: 0, w: 6, h: 4, minW: 2, minH: 2 },
        ];

        const compacted = compactLayout(layout);
        const a = compacted.find((item) => item.i === 'a');

        expect(a.y).toBe(0);
        expect(layoutHasCollisions(compacted)).toBe(false);
    });

    it('preserva x al compactar verticalmente', () => {
        const layout = [
            { i: 'a', x: 3, y: 8, w: 4, h: 4, minW: 2, minH: 2 },
        ];

        const compacted = compactLayout(layout);
        expect(compacted[0].x).toBe(3);
        expect(compacted[0].y).toBe(0);
    });
});
describe('optimizeLayout', () => {
    const defaultLayout = [
        { i: 'a', x: 0, y: 0, w: 7, h: 12, minW: 2, minH: 2 },
        { i: 'b', x: 7, y: 0, w: 5, h: 6, minW: 2, minH: 2 },
        { i: 'c', x: 0, y: 8, w: 12, h: 4, minW: 2, minH: 2 },
    ];

    it('genera disposición sin solapamientos', () => {
        const layout = [
            { i: 'a', x: 0, y: 0, w: 6, h: 6, minW: 2, minH: 2 },
            { i: 'b', x: 3, y: 3, w: 6, h: 4, minW: 2, minH: 2 },
            { i: 'c', x: 0, y: 8, w: 12, h: 4, minW: 2, minH: 2 },
        ];

        const optimized = optimizeLayout(layout, defaultLayout);
        expect(layoutHasCollisions(optimized)).toBe(false);
        expect(optimized).toHaveLength(3);
    });

    it('restaura dimensiones por defecto al optimizar', () => {
        const layout = [
            { i: 'a', x: 5, y: 5, w: 3, h: 3, minW: 2, minH: 2 },
        ];
        const optimized = optimizeLayout(layout, defaultLayout);
        const a = optimized.find((item) => item.i === 'a');
        expect(a.w).toBe(7);
        expect(a.h).toBe(12);
    });
});

describe('resolveLayout — mínimo 4×4 en todos los paneles', () => {
    it('eleva w/h guardados por debajo del mínimo al cargar', () => {
        const defaultLayout = buildDefaultLayout({
            hasModulos: true,
            hasFunciones: false,
            hasWidgetSolicitudes: true,
            hasWidgetCancelaciones: false,
            hasWidgetActivos: false,
        });
        const saved = [{ i: PANEL_IDS.SOLICITUDES, x: 7, y: 0, w: 2, h: 3, minW: 2, minH: 2 }];
        const layout = resolveLayout(saved, [PANEL_IDS.MODULOS, PANEL_IDS.SOLICITUDES], defaultLayout);
        const solicitudes = layout.find((item) => item.i === PANEL_IDS.SOLICITUDES);
        expect(solicitudes.w).toBeGreaterThanOrEqual(PANEL_GRID_MIN_W);
        expect(solicitudes.h).toBeGreaterThanOrEqual(PANEL_GRID_MIN_H);
        expect(solicitudes.minW).toBe(PANEL_GRID_MIN_W);
        expect(solicitudes.minH).toBe(PANEL_GRID_MIN_H);
    });

    it('define minW/minH 4 en el layout por defecto', () => {
        const layout = buildDefaultLayout({
            hasModulos: true,
            hasFunciones: true,
            hasWidgetSolicitudes: true,
            hasWidgetCancelaciones: true,
            hasWidgetActivos: true,
        });
        layout.forEach((item) => {
            expect(item.minW).toBe(PANEL_GRID_MIN_W);
            expect(item.minH).toBe(PANEL_GRID_MIN_H);
            expect(item.w).toBeGreaterThanOrEqual(PANEL_GRID_MIN_W);
            expect(item.h).toBeGreaterThanOrEqual(PANEL_GRID_MIN_H);
        });
    });
});

describe('applyLayoutChange', () => {
    it('resuelve colisiones y compacta tras mover un panel', () => {
        const layout = [
            { i: 'a', x: 0, y: 0, w: 6, h: 6, minW: 2, minH: 2 },
            { i: 'b', x: 6, y: 6, w: 6, h: 4, minW: 2, minH: 2 },
        ];

        const moved = layout.map((item) =>
            item.i === 'a' ? { ...item, x: 4, y: 4 } : item
        );

        const result = applyLayoutChange(moved, 'a');
        expect(layoutHasCollisions(result)).toBe(false);
    });
});
