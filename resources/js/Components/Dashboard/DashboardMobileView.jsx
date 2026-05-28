import React from 'react';
import { orderPanelsByLayout } from './dashboardLayoutUtils';
import { ESTILOS_DASHBOARD_MOBILE } from './dashboardMobileStyles';

/**
 * Vista móvil independiente: sin grilla desktop, sin container queries, sin h-full.
 */
export default function DashboardMobileView({ layout, visiblePanelIds, panels }) {
    const orderedIds = orderPanelsByLayout(layout, visiblePanelIds);

    return (
        <>
            <style>{ESTILOS_DASHBOARD_MOBILE}</style>
            <div className="dashboard-mobile-view">
                {orderedIds.map((id) => (
                    <div key={id} className="dashboard-mobile-view__section">
                        {panels[id]}
                    </div>
                ))}
            </div>
        </>
    );
}
