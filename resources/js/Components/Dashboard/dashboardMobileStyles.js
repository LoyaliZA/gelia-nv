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

    /* --- Tarjeta móvil: solo icono (nombres en aria-label / title nativo) --- */
    .dashboard-module-card-mobile {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        aspect-ratio: 1;
        width: 100%;
        min-height: 0;
        padding: 0.5rem;
        border-radius: 0.875rem;
        border-width: 2px;
        border-style: solid;
        text-decoration: none;
        outline: none;
        transition: border-color 0.2s ease, transform 0.2s ease;
        box-sizing: border-box;
    }

    .dashboard-module-card-mobile:hover,
    .dashboard-module-card-mobile:focus-visible {
        border-color: var(--color-primario);
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
        flex-shrink: 0;
        border-width: 1px;
        border-style: solid;
    }

    .dashboard-module-card-mobile__icon {
        width: 1.125rem;
        height: 1.125rem;
    }

    @media (min-width: 480px) {
        .dashboard-module-card-mobile {
            padding: 0.625rem;
        }

        .dashboard-module-card-mobile__icon-wrap {
            width: 2.5rem;
            height: 2.5rem;
        }

        .dashboard-module-card-mobile__icon {
            width: 1.25rem;
            height: 1.25rem;
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
