export const ESTILOS_DASHBOARD_ADAPTIVE = `
    /* --- Panel shell --- */
    .dashboard-panel-shell {
        container-type: size;
        container-name: panel-shell;
    }

    .dashboard-panel-shell__title {
        font-size: clamp(0.8125rem, 0.5rem + 1.2vw, 1rem);
        line-height: 1.2;
    }

    .dashboard-panel-shell__header {
        min-height: 2.5rem;
    }

    @container panel-shell (max-height: 22rem) {
        .dashboard-panel-shell__header {
            padding-bottom: 0.75rem;
        }
        .dashboard-panel-shell__body {
            margin-top: 0.75rem;
        }
    }

    /* --- Grid de tarjetas dentro del panel (mobile-first) --- */
    .dashboard-panel-cards {
        container-type: size;
        container-name: panel-cards;
        width: 100%;
        min-width: 0;
        min-height: 0;
        height: 100%;
        --card-min: 3.75rem;
    }

    .dashboard-panel-cards__grid {
        display: grid;
        width: 100%;
        height: 100%;
        gap: 0.375rem;
        align-content: stretch;
        align-items: stretch;
        grid-template-columns: repeat(auto-fill, minmax(min(100%, var(--card-min)), 1fr));
        grid-auto-rows: minmax(var(--card-min), 1fr);
    }

    @container panel-cards (min-width: 14rem) {
        .dashboard-panel-cards {
            --card-min: 4.25rem;
        }
        .dashboard-panel-cards__grid {
            gap: 0.5rem;
        }
    }

    @container panel-cards (min-width: 18rem) {
        .dashboard-panel-cards {
            --card-min: 5rem;
        }
        .dashboard-panel-cards__grid {
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 7rem), 1fr));
        }
    }

    @container panel-cards (min-width: 28rem) {
        .dashboard-panel-cards {
            --card-min: 7rem;
        }
        .dashboard-panel-cards__grid {
            gap: 1rem;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 10rem), 1fr));
            grid-auto-rows: minmax(9rem, 1fr);
        }
    }

    /* Panel bajo: forzar cubos aunque las celdas sean anchas */
    @container panel-shell (max-height: 20rem) {
        .dashboard-panel-cards {
            --card-min: 4.25rem;
        }
        .dashboard-panel-cards__grid {
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 4.25rem), 1fr));
            grid-auto-rows: minmax(4.25rem, 4.25rem);
            gap: 0.375rem;
            align-content: start;
        }
    }

    @container panel-cards (max-height: 14rem) {
        .dashboard-panel-cards {
            --card-min: 4.25rem;
        }
        .dashboard-panel-cards__grid {
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 4.25rem), 1fr));
            grid-auto-rows: minmax(4.25rem, 4.25rem);
            gap: 0.375rem;
            align-content: start;
        }
    }

    /* --- Slot de cada tarjeta --- */
    .dashboard-card-slot {
        container-type: size;
        container-name: module-card;
        min-width: 0;
        min-height: 0;
        width: 100%;
        height: 100%;
        max-height: 100%;
        overflow: hidden;
    }

    /* --- Tarjeta de módulo: default = compacto/cúbico --- */
    .dashboard-module-card {
        display: flex;
        flex-direction: column;
        height: 100%;
        width: 100%;
        min-height: 0;
        min-width: 0;
        max-height: 100%;
        padding: 0.5rem;
        border-radius: 0.875rem;
        border-width: 2px;
        border-style: solid;
        overflow: hidden;
        transition: border-color 0.2s;
        aspect-ratio: 1;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        position: relative;
    }

    .dashboard-module-card:hover {
        border-color: var(--color-primario);
    }

    .dashboard-module-card__tooltip {
        position: absolute;
        inset: 0;
        z-index: 50;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.15s ease, visibility 0.15s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.375rem;
        border-radius: inherit;
        font-size: 0.5rem;
        font-weight: 900;
        font-style: italic;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        line-height: 1.2;
        text-align: center;
        background: color-mix(in srgb, var(--color-primario) 92%, transparent);
        color: white;
    }

    @container module-card (max-width: 6.99rem), module-card (max-height: 5.49rem) {
        .dashboard-module-card:hover .dashboard-module-card__tooltip,
        .dashboard-module-card:focus-visible .dashboard-module-card__tooltip {
            opacity: 1;
            visibility: visible;
        }
    }

    @container module-card (min-width: 7rem) and (min-height: 5.5rem) {
        .dashboard-module-card__tooltip {
            display: none;
        }
    }

    .dashboard-module-card__icon-wrap {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.625rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0;
        flex-shrink: 0;
        border-width: 1px;
        border-style: solid;
    }

    .dashboard-module-card__icon {
        width: 1.125rem;
        height: 1.125rem;
        flex-shrink: 0;
    }

    .dashboard-module-card__title {
        display: none;
        font-weight: 900;
        font-style: italic;
        text-transform: uppercase;
        letter-spacing: -0.025em;
        line-height: 1.15;
        margin: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: left;
        width: 100%;
    }

    .dashboard-module-card__subtitle {
        display: none;
        font-size: 0.6875rem;
        font-weight: 700;
        font-style: italic;
        line-height: 1.35;
        margin: 0;
        overflow: hidden;
    }

    /* Mini card: celda mediana */
    @container module-card (min-width: 7rem) and (min-height: 5.5rem) {
        .dashboard-module-card {
            aspect-ratio: auto;
            align-items: flex-start;
            justify-content: flex-start;
            padding: 0.75rem;
            border-radius: 1rem;
        }
        .dashboard-module-card__icon-wrap {
            width: 2rem;
            height: 2rem;
            margin-bottom: 0.5rem;
        }
        .dashboard-module-card__icon {
            width: 1rem;
            height: 1rem;
        }
        .dashboard-module-card__title {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            font-size: clamp(0.6875rem, 0.5rem + 0.8cqw, 0.8125rem);
        }
    }

    /* Card completa: celda grande */
    @container module-card (min-width: 10rem) and (min-height: 8.5rem) {
        .dashboard-module-card {
            padding: 1rem;
            border-radius: 1.25rem;
        }
        .dashboard-module-card__icon-wrap {
            width: 2.75rem;
            height: 2.75rem;
            margin-bottom: 0.75rem;
            border-radius: 0.875rem;
        }
        .dashboard-module-card__icon {
            width: 1.25rem;
            height: 1.25rem;
        }
        .dashboard-module-card__title {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            font-size: clamp(0.875rem, 0.6rem + 1.5cqw, 1.125rem);
            margin-bottom: 0.375rem;
        }
        .dashboard-module-card__subtitle {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            font-size: clamp(0.625rem, 0.5rem + 0.5cqw, 0.75rem);
        }
    }

    /* Panel bajo: forzar cubos desde el contenedor padre */
    @container panel-shell (max-height: 20rem) {
        .dashboard-module-card {
            aspect-ratio: 1;
            align-items: center;
            justify-content: center;
            padding: 0.375rem;
        }
        .dashboard-module-card__icon-wrap {
            width: 2rem;
            height: 2rem;
            margin-bottom: 0;
        }
        .dashboard-module-card__title,
        .dashboard-module-card__subtitle {
            display: none !important;
        }
    }

    /* --- Widget adaptativo --- */
    .dashboard-adaptive-widget {
        container-type: size;
        container-name: dashboard-widget;
        height: 100%;
        width: 100%;
        min-height: 0;
        min-width: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-radius: 1.5rem;
        border-width: 2px;
        border-style: solid;
        padding: 0.875rem;
        position: relative;
        transition: border-color 0.3s;
        box-sizing: border-box;
    }

    @media (min-width: 640px) {
        .dashboard-adaptive-widget {
            padding: 1.25rem;
            border-radius: 2rem;
        }
    }

    @media (min-width: 768px) {
        .dashboard-adaptive-widget {
            padding: 1.5rem;
            border-radius: 2.5rem;
        }
    }

    .dashboard-adaptive-widget:hover {
        border-color: var(--color-primario);
    }

    .dashboard-widget__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 1rem;
        flex-shrink: 0;
        min-width: 0;
    }

    .dashboard-widget__header-title {
        font-size: clamp(0.8125rem, 0.6rem + 0.8cqw, 1rem);
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dashboard-widget__summary {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        flex-shrink: 0;
    }

    .dashboard-widget__body {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .dashboard-widget__footer {
        margin-top: auto;
        padding-top: 1rem;
        border-top: 1px solid;
        flex-shrink: 0;
    }

    .dashboard-widget__minimal-link {
        display: none;
        flex: 1;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        text-align: center;
        min-height: 0;
        outline: none;
    }

    .dashboard-widget__item--extra {
        display: block;
    }

    @container dashboard-widget (max-width: 13rem), dashboard-widget (max-height: 15rem) {
        .dashboard-adaptive-widget {
            padding: 0.75rem 1rem;
        }
        .dashboard-widget__header {
            margin-bottom: 0.5rem;
        }
        .dashboard-widget__header-title {
            font-size: 0.75rem;
        }
        .dashboard-widget__summary {
            display: none;
        }
        .dashboard-widget__item--extra {
            display: none;
        }
        .dashboard-widget__footer {
            padding-top: 0.5rem;
        }
        .dashboard-widget__footer-link {
            padding-top: 0.625rem;
            padding-bottom: 0.625rem;
            font-size: 0.5625rem;
        }
    }

    @container dashboard-widget (max-width: 9rem), dashboard-widget (max-height: 9rem) {
        .dashboard-widget__body,
        .dashboard-widget__footer,
        .dashboard-widget__summary {
            display: none;
        }
        .dashboard-widget__header {
            margin-bottom: 0;
            flex: 1;
            justify-content: center;
        }
        .dashboard-widget__minimal-link {
            display: flex;
        }
        .dashboard-adaptive-widget {
            padding: 0.75rem;
        }
    }

    .dashboard-grid-size-badge {
        position: absolute;
        top: 0.375rem;
        right: 0.375rem;
        z-index: 40;
        pointer-events: none;
        font-size: 0.5625rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.125rem 0.375rem;
        border-radius: 0.375rem;
        background: color-mix(in srgb, var(--color-primario) 15%, transparent);
        color: var(--color-primario);
        border: 1px solid color-mix(in srgb, var(--color-primario) 30%, transparent);
    }
`;
