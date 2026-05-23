import { useEffect, useState } from 'react';

export const DASHBOARD_MOBILE_BREAKPOINT = 1024;

export function useDashboardBreakpoint() {
    const [isMobile, setIsMobile] = useState(() => {
        if (typeof window === 'undefined') return false;
        return window.innerWidth < DASHBOARD_MOBILE_BREAKPOINT;
    });

    useEffect(() => {
        const media = window.matchMedia(`(max-width: ${DASHBOARD_MOBILE_BREAKPOINT - 1}px)`);
        const sync = () => setIsMobile(media.matches);
        sync();
        media.addEventListener('change', sync);
        return () => media.removeEventListener('change', sync);
    }, []);

    return { isMobile, isDesktop: !isMobile };
}
