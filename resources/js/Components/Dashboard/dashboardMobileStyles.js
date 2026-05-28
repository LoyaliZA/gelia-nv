/**
 * Estilos exclusivos para la vista móvil del dashboard.
 * Sin container queries ni reglas de escritorio — evita conflictos con el layout desktop.
 */
export const ESTILOS_DASHBOARD_MOBILE = `
    .dashboard-mobile-view {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        width: 100%;
    }

    .dashboard-mobile-view__section {
        width: 100%;
    }

    /* --- Panel móvil --- */
    .dashboard-panel-mobile {
        display: flex;
        flex-direction: column;
        width: 100%;
        border-radius: 1.25rem;
        border-width: 2px;
        border-style: solid;
        padding: 1rem;
        overflow: visible;
    }

    .dashboard-panel-mobile__header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom-width: 1px;
        border-bottom-style: solid;
        flex-shrink: 0;
    }

    .dashboard-panel-mobile__title {
        font-size: clamp(0.875rem, 2.8vw + 0.45rem, 1.0625rem);
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        line-height: 1.2;
        margin: 0;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .dashboard-panel-mobile__body {
        margin-top: 0.875rem;
        overflow: visible;
    }

    .dashboard-panel-mobile__grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
        width: 100%;
    }

    @media (min-width: 480px) {
        .dashboard-panel-mobile__grid {
            grid-template-columns: repeat(auto-fill, minmax(6.25rem, 1fr));
            gap: 0.625rem;
        }
    }

    /* --- Tarjeta móvil --- */
    .dashboard-module-card-mobile {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: flex-start;
        min-height: 5.75rem;
        padding: 0.75rem;
        border-radius: 0.875rem;
        border-width: 2px;
        border-style: solid;
        text-decoration: none;
        outline: none;
        transition: border-color 0.2s ease, transform 0.2s ease;
    }

    .dashboard-module-card-mobile:active {
        transform: scale(0.97);
    }

    .dashboard-module-card-mobile__icon-wrap {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.625rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.5rem;
        flex-shrink: 0;
        border-width: 1px;
        border-style: solid;
    }

    .dashboard-module-card-mobile__icon {
        width: 1.125rem;
        height: 1.125rem;
    }

    .dashboard-module-card-mobile__title {
        font-size: clamp(0.6875rem, 2.2vw + 0.35rem, 0.8125rem);
        font-weight: 900;
        font-style: italic;
        text-transform: uppercase;
        letter-spacing: -0.02em;
        line-height: 1.2;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        width: 100%;
        text-align: left;
    }

    @media (min-width: 480px) {
        .dashboard-module-card-mobile {
            min-height: 6.5rem;
            padding: 0.875rem;
        }

        .dashboard-module-card-mobile__title {
            font-size: clamp(0.75rem, 1.5vw + 0.4rem, 0.875rem);
        }
    }

    /* --- Widget móvil --- */
    .dashboard-widget-mobile {
        display: flex;
        flex-direction: column;
        width: 100%;
        min-height: 14rem;
        border-radius: 1.25rem;
        border-width: 2px;
        border-style: solid;
        padding: 1rem;
        overflow: hidden;
    }

    .dashboard-widget-mobile__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.875rem;
        flex-shrink: 0;
    }

    .dashboard-widget-mobile__title {
        font-size: clamp(0.875rem, 2.5vw + 0.4rem, 1rem);
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        line-height: 1.2;
    }

    .dashboard-widget-mobile__body {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .dashboard-widget-mobile__footer {
        margin-top: 0.875rem;
        padding-top: 0.875rem;
        border-top-width: 1px;
        border-top-style: solid;
        flex-shrink: 0;
    }
`;
