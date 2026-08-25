import { describe, it, expect } from 'vitest';
import { SIDEBAR_LAYOUT_IDS, isProfessionalSidebarLayout } from '../../config/sidebarLayouts';

/** Contrato AppLayout: professional_left monta V2; el resto legacy. */
export function resolveSidebarVariant(layout) {
    return isProfessionalSidebarLayout(layout) ? 'professional' : 'legacy';
}

export function resolveShellSidebarLayout({ layout, isMobile, mobileLayout }) {
    if (isMobile) {
        if (isProfessionalSidebarLayout(layout) || mobileLayout === 'mobile_topbar') {
            return 'mobile-topbar';
        }
        return 'mobile-bottom';
    }
    if (isProfessionalSidebarLayout(layout)) return 'professional-left';
    if (layout === 'fixed') return 'fixed';
    if (layout === 'floating_right') return 'float-right';
    return 'float-left';
}

describe('AppLayout sidebar switch contract', () => {
    it('professional_left usa variante professional', () => {
        expect(resolveSidebarVariant('professional_left')).toBe('professional');
        for (const id of SIDEBAR_LAYOUT_IDS.filter((x) => x !== 'professional_left')) {
            expect(resolveSidebarVariant(id)).toBe('legacy');
        }
    });

    it('shell insets usan professional-left en escritorio', () => {
        expect(resolveShellSidebarLayout({
            layout: 'professional_left',
            isMobile: false,
        })).toBe('professional-left');
        expect(resolveShellSidebarLayout({
            layout: 'floating_left',
            isMobile: false,
        })).toBe('float-left');
    });

    it('profesional en móvil fuerza topbar drawer', () => {
        expect(resolveShellSidebarLayout({
            layout: 'professional_left',
            isMobile: true,
            mobileLayout: 'mobile_bottom',
        })).toBe('mobile-topbar');
    });
});
